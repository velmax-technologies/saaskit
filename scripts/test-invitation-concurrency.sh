#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

APP_ID="$(docker compose ps -q app)"
if [[ -z "$APP_ID" ]]; then
    echo "FAIL: the app container is not running."
    exit 1
fi

NETWORK="$(docker inspect "$APP_ID" | python3 -c '
import json, sys
networks = json.load(sys.stdin)[0]["NetworkSettings"]["Networks"]
matches = [name for name in networks if "proxy" not in name.lower()]
if not matches:
    raise SystemExit("Could not identify the internal Compose network.")
print(matches[0])
')"

SUFFIX="$$"
DB_CONTAINER="saaskit-invite-race-db-$SUFFIX"
HTTP_CONTAINER="saaskit-invite-race-http-$SUFFIX"
DB_NAME="saaskit_invite_race"
DB_USER="saaskit_invite_race"
DB_PASSWORD="$(openssl rand -hex 24)"
ROOT_PASSWORD="$(openssl rand -hex 24)"
TMP_DIR="$(mktemp -d /tmp/saaskit-invite-race.XXXXXX)"
MARKER="/tmp/saaskit-invite-race-ready-$SUFFIX"
LOCK_LOG="$TMP_DIR/lock.log"
SEED_LOG="$TMP_DIR/seed.log"
VERIFY_LOG="$TMP_DIR/verify.log"
STATUS1_FILE="$TMP_DIR/status1"
STATUS2_FILE="$TMP_DIR/status2"
BODY1_FILE="$TMP_DIR/body1.json"
BODY2_FILE="$TMP_DIR/body2.json"
CURL1_LOG="$TMP_DIR/curl1.log"
CURL2_LOG="$TMP_DIR/curl2.log"

DB_STARTED=0
HTTP_STARTED=0
LOCK_PID=""
CURL1_PID=""
CURL2_PID=""

cleanup() {
    local original_status=$?
    trap - EXIT INT TERM

    echo
    echo "========== CLEANUP =========="

    for pid in "$CURL1_PID" "$CURL2_PID" "$LOCK_PID"; do
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
            wait "$pid" 2>/dev/null || true
        fi
    done

    if (( HTTP_STARTED )); then
        docker rm -f "$HTTP_CONTAINER" >/dev/null 2>&1 || true
    fi

    if (( DB_STARTED )); then
        docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
    fi

    docker compose exec -T app rm -f "$MARKER" >/dev/null 2>&1 || true
    rm -rf "$TMP_DIR"

    echo "Temporary database, HTTP container, marker and local temporary files cleaned up."
    return "$original_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "========== INVITATION CONCURRENCY REGRESSION =========="
echo "Docker network: $NETWORK"
echo "Persistent application database will not be used."

docker run -d --rm \
    --name "$DB_CONTAINER" \
    --network "$NETWORK" \
    --network-alias "$DB_CONTAINER" \
    --tmpfs /var/lib/mysql:rw,size=512m \
    -e MARIADB_DATABASE="$DB_NAME" \
    -e MARIADB_USER="$DB_USER" \
    -e MARIADB_PASSWORD="$DB_PASSWORD" \
    -e MARIADB_ROOT_PASSWORD="$ROOT_PASSWORD" \
    --health-cmd='healthcheck.sh --connect --innodb_initialized' \
    --health-interval=2s \
    --health-timeout=3s \
    --health-retries=30 \
    mariadb:11.4 >/dev/null
DB_STARTED=1

echo "Waiting for disposable MariaDB..."
DB_READY=NO
for _ in $(seq 1 45); do
    HEALTH="$(docker inspect -f '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null || true)"
    if [[ "$HEALTH" == healthy ]]; then
        DB_READY=YES
        break
    fi
    sleep 2
done

if [[ "$DB_READY" != YES ]]; then
    echo "FAIL: disposable MariaDB did not become healthy."
    docker logs "$DB_CONTAINER" 2>&1 | tail -40 || true
    exit 1
fi

db_env=(
    -e DB_CONNECTION=mysql
    -e DB_HOST="$DB_CONTAINER"
    -e DB_PORT=3306
    -e DB_DATABASE="$DB_NAME"
    -e DB_USERNAME="$DB_USER"
    -e DB_PASSWORD="$DB_PASSWORD"
    -e DB_URL=
    -e MAIL_MAILER=log
)

echo "Verifying Laravel database target..."
docker compose exec -T "${db_env[@]}" \
    -e EXPECTED_DB_HOST="$DB_CONTAINER" \
    -e EXPECTED_DB_NAME="$DB_NAME" \
    app php artisan tinker --execute='
    $connection = \DB::connection();
    $config = $connection->getConfig();

    if (
        $connection->getDriverName() !== "mysql"
        || ($config["host"] ?? null) !== getenv("EXPECTED_DB_HOST")
        || $connection->getDatabaseName() !== getenv("EXPECTED_DB_NAME")
    ) {
        fwrite(STDERR, "FAIL: unexpected database connection." . PHP_EOL);
        exit(1);
    }

    echo "Disposable database connection verified." . PHP_EOL;
'

echo "Running migrations only against the disposable database..."
docker compose exec -T "${db_env[@]}" app php artisan migrate --force

echo "Creating a verified owner, organization and temporary token..."
if ! docker compose exec -T "${db_env[@]}" app php artisan tinker --execute='
    $owner = \App\Models\User::factory()->create([
        "email_verified_at" => now(),
    ]);

    $organization = \App\Models\Organization::factory()->create();

    $organization->users()->attach($owner->getKey(), [
        "role" => \App\Models\Organization::ROLE_OWNER,
    ]);

    echo "INVITE_RACE_FIXTURE=" . json_encode([
        "organization_id" => $organization->getKey(),
        "organization_public_id" => $organization->public_id,
        "token" => $owner->createToken("temporary-invitation-race")->plainTextToken,
    ]) . PHP_EOL;
' >"$SEED_LOG" 2>&1; then
    echo "FAIL: fixture creation failed."
    tail -40 "$SEED_LOG"
    exit 1
fi

FIXTURE_JSON="$(sed -n 's/^INVITE_RACE_FIXTURE=//p' "$SEED_LOG" | tail -1)"
if [[ -z "$FIXTURE_JSON" ]]; then
    echo "FAIL: fixture data was not returned."
    tail -40 "$SEED_LOG"
    exit 1
fi

ORG_PK="$(printf '%s' "$FIXTURE_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["organization_id"])')"
ORG_PUBLIC_ID="$(printf '%s' "$FIXTURE_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["organization_public_id"])')"
TOKEN="$(printf '%s' "$FIXTURE_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')"

if [[ -z "$ORG_PK" || -z "$ORG_PUBLIC_ID" || -z "$TOKEN" ]]; then
    echo "FAIL: incomplete fixture data."
    exit 1
fi

echo "Starting a separate Laravel HTTP container..."
docker compose run -d --no-deps \
    --name "$HTTP_CONTAINER" \
    "${db_env[@]}" \
    app php artisan serve --host=0.0.0.0 --port=8001 --no-reload >/dev/null
HTTP_STARTED=1

HTTP_READY=NO
for _ in $(seq 1 30); do
    PROBE="$(docker exec "$HTTP_CONTAINER" curl --max-time 3 -sS \
        -o /dev/null -w '%{http_code}' \
        -H 'Accept: application/json' \
        http://127.0.0.1:8001/api/v1/organizations 2>/dev/null || true)"

    if [[ "$PROBE" == 401 || "$PROBE" == 200 || "$PROBE" == 403 ]]; then
        HTTP_READY=YES
        break
    fi
    sleep 1
done

if [[ "$HTTP_READY" != YES ]]; then
    echo "FAIL: isolated Laravel HTTP server did not become ready."
    docker logs "$HTTP_CONTAINER" 2>&1 | tail -40 || true
    exit 1
fi

echo "Holding the organization row lock for 15 seconds..."
LOCK_SCRIPT='
    \DB::transaction(function () {
        \DB::table("organizations")
            ->where("id", (int) getenv("RACE_ORG_PK"))
            ->lockForUpdate()
            ->first();

        file_put_contents(getenv("RACE_MARKER"), "ready");
        sleep(15);
    });

    echo "INVITATION_RACE_LOCK_COMMITTED" . PHP_EOL;
'

docker compose exec -T \
    "${db_env[@]}" \
    -e RACE_ORG_PK="$ORG_PK" \
    -e RACE_MARKER="$MARKER" \
    app php artisan tinker --execute="$LOCK_SCRIPT" >"$LOCK_LOG" 2>&1 &
LOCK_PID=$!

LOCK_READY=NO
for _ in $(seq 1 15); do
    if docker compose exec -T app test -f "$MARKER" >/dev/null 2>&1; then
        LOCK_READY=YES
        break
    fi

    if ! kill -0 "$LOCK_PID" 2>/dev/null; then
        break
    fi
    sleep 1
done

if [[ "$LOCK_READY" != YES ]]; then
    echo "FAIL: could not establish the organization lock."
    tail -40 "$LOCK_LOG" || true
    exit 1
fi

echo "Launching two invitation requests for the same email..."
EMAIL="race-invitee@example.com"

run_invitation_request() {
    local body_path="$1"
    local status_path="$2"
    local curl_log="$3"

    docker exec \
        -e RACE_TOKEN="$TOKEN" \
        -e RACE_ORG="$ORG_PUBLIC_ID" \
        -e RACE_EMAIL="$EMAIL" \
        "$HTTP_CONTAINER" sh -lc '
            curl --max-time 35 -sS \
                -o "'"$body_path"'" \
                -w "%{http_code}" \
                -X POST \
                -H "Accept: application/json" \
                -H "Content-Type: application/json" \
                -H "Authorization: Bearer $RACE_TOKEN" \
                --data "{\"email\":\"$RACE_EMAIL\"}" \
                "http://127.0.0.1:8001/api/v1/organizations/$RACE_ORG/invitations"
        ' >"$status_path" 2>"$curl_log"
}

run_invitation_request /tmp/invite-race-response-1.json \
    "$STATUS1_FILE" "$CURL1_LOG" &
CURL1_PID=$!

run_invitation_request /tmp/invite-race-response-2.json \
    "$STATUS2_FILE" "$CURL2_LOG" &
CURL2_PID=$!

sleep 3

BOTH_WAITING=YES
if ! kill -0 "$CURL1_PID" 2>/dev/null || ! kill -0 "$CURL2_PID" 2>/dev/null; then
    BOTH_WAITING=NO
    echo "FAIL: one or both requests completed before the organization lock was released."
fi

echo "Releasing the lock by waiting for its transaction to finish..."
if wait "$LOCK_PID"; then
    LOCK_EXIT=0
else
    LOCK_EXIT=$?
fi
LOCK_PID=""

if wait "$CURL1_PID"; then CURL1_EXIT=0; else CURL1_EXIT=$?; fi
CURL1_PID=""
if wait "$CURL2_PID"; then CURL2_EXIT=0; else CURL2_EXIT=$?; fi
CURL2_PID=""

STATUS1="$(cat "$STATUS1_FILE" 2>/dev/null || true)"
STATUS2="$(cat "$STATUS2_FILE" 2>/dev/null || true)"

docker cp "$HTTP_CONTAINER:/tmp/invite-race-response-1.json" "$BODY1_FILE" >/dev/null 2>&1 || true
docker cp "$HTTP_CONTAINER:/tmp/invite-race-response-2.json" "$BODY2_FILE" >/dev/null 2>&1 || true

echo "Request 1 exit: $CURL1_EXIT; HTTP status: ${STATUS1:-missing}"
echo "Request 2 exit: $CURL2_EXIT; HTTP status: ${STATUS2:-missing}"
echo "Request 1 response:"
cat "$BODY1_FILE" 2>/dev/null || echo "(unavailable)"
echo
echo "Request 2 response:"
cat "$BODY2_FILE" 2>/dev/null || echo "(unavailable)"

echo
echo "Counting pending invitations in the disposable database..."
if ! docker compose exec -T \
    "${db_env[@]}" \
    -e RACE_ORG_PK="$ORG_PK" \
    -e RACE_EMAIL="$EMAIL" \
    app php artisan tinker --execute='
        $query = \App\Models\OrganizationInvitation::query()
            ->where("organization_id", (int) getenv("RACE_ORG_PK"))
            ->whereRaw("LOWER(email) = ?", [strtolower(getenv("RACE_EMAIL"))])
            ->whereNull("accepted_at")
            ->whereNull("revoked_at")
            ->where("expires_at", ">", now());

        echo "INVITATION_RACE_COUNT=" . $query->count() . PHP_EOL;
    ' >"$VERIFY_LOG" 2>&1; then
    echo "FAIL: could not verify invitation count."
    tail -40 "$VERIFY_LOG"
    exit 1
fi

COUNT="$(sed -n 's/^INVITATION_RACE_COUNT=//p' "$VERIFY_LOG" | tail -1)"
echo "Pending invitation count: ${COUNT:-missing}"

if [[ "$BOTH_WAITING" == YES \
      && "$LOCK_EXIT" -eq 0 \
      && "$CURL1_EXIT" -eq 0 \
      && "$CURL2_EXIT" -eq 0 \
      && "$COUNT" == "1" ]] &&
   { [[ "$STATUS1" == "201" && "$STATUS2" == "422" ]] ||
     [[ "$STATUS1" == "422" && "$STATUS2" == "201" ]]; }; then
    echo "INVITATION CONCURRENCY REGRESSION: PASS"
else
    echo "INVITATION CONCURRENCY REGRESSION: FAIL OR INCONCLUSIVE"
    [[ ! -s "$CURL1_LOG" ]] || cat "$CURL1_LOG"
    [[ ! -s "$CURL2_LOG" ]] || cat "$CURL2_LOG"
    tail -30 "$LOCK_LOG" 2>/dev/null || true
    exit 1
fi
