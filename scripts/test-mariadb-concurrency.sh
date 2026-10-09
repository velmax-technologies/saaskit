#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

APP_ID="$(docker compose ps -q app)"
if [[ -z "$APP_ID" ]]; then
    echo "FAIL: the normal app container is not running."
    exit 1
fi

NETWORK="$(docker inspect "$APP_ID" | python3 -c '
import json, sys
networks = json.load(sys.stdin)[0]["NetworkSettings"]["Networks"]
matches = [n for n in networks if "proxy" not in n.lower()]
if not matches:
    raise SystemExit("Could not identify the internal Compose network.")
print(matches[0])
')"

SUFFIX="$$"
DB_CONTAINER="saaskit-race-db-$SUFFIX"
HTTP_CONTAINER="saaskit-race-http-$SUFFIX"
DB_HOST="$DB_CONTAINER"
DB_NAME="saaskit_test"
DB_USER="saaskit_test"
DB_PASSWORD="$(openssl rand -hex 24)"
ROOT_PASSWORD="$(openssl rand -hex 24)"
TMP_DIR="$(mktemp -d /tmp/saaskit-race.XXXXXX)"
MARKER="/tmp/saaskit-race-lock-ready-$SUFFIX"
LOCK_LOG="$TMP_DIR/lock.log"
STATUS_FILE="$TMP_DIR/http-status"
BODY_FILE="$TMP_DIR/http-body"
CURL_LOG="$TMP_DIR/curl.log"
LOCK_PID=""
CURL_PID=""
HTTP_STARTED=0
DB_STARTED=0

cleanup() {
    local original_status=$?
    trap - EXIT INT TERM

    echo
    echo "===== CLEANUP ====="

    if [[ -n "$CURL_PID" ]] && kill -0 "$CURL_PID" 2>/dev/null; then
        kill "$CURL_PID" 2>/dev/null || true
        wait "$CURL_PID" 2>/dev/null || true
    fi

    if [[ -n "$LOCK_PID" ]] && kill -0 "$LOCK_PID" 2>/dev/null; then
        kill "$LOCK_PID" 2>/dev/null || true
        wait "$LOCK_PID" 2>/dev/null || true
    fi

    if (( HTTP_STARTED )); then
        docker rm -f "$HTTP_CONTAINER" >/dev/null 2>&1 || true
    fi

    if (( DB_STARTED )); then
        docker stop "$DB_CONTAINER" >/dev/null 2>&1 || true
    fi

    docker compose exec -T app rm -f "$MARKER" >/dev/null 2>&1 || true
    rm -rf "$TMP_DIR"

    echo "Temporary test resources removed."
    return "$original_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "===== SAASKIT MARIADB CONCURRENCY REGRESSION ====="
echo "Internal Docker network: $NETWORK"
echo "The persistent application database will not be used."

docker run -d --rm \
    --name "$DB_CONTAINER" \
    --network "$NETWORK" \
    --network-alias "$DB_HOST" \
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
    if [[ "$HEALTH" == "healthy" ]]; then
        DB_READY=YES
        break
    fi
    sleep 2
done

if [[ "$DB_READY" != YES ]]; then
    echo "FAIL: disposable MariaDB did not become healthy."
    docker logs "$DB_CONTAINER" 2>&1 | tail -30 || true
    exit 1
fi

db_env=(
    -e DB_CONNECTION=mysql
    -e DB_HOST="$DB_HOST"
    -e DB_PORT=3306
    -e DB_DATABASE="$DB_NAME"
    -e DB_USERNAME="$DB_USER"
    -e DB_PASSWORD="$DB_PASSWORD"
    -e DB_URL=
)

echo "Verifying Laravel is connected to the disposable database..."
docker compose exec -T "${db_env[@]}" \
    -e EXPECTED_DB_HOST="$DB_HOST" \
    -e EXPECTED_DB_NAME="$DB_NAME" \
    app php artisan tinker --execute='
    $connection = \DB::connection();
    $config = $connection->getConfig();
    $expectedHost = getenv("EXPECTED_DB_HOST");
    $expectedDatabase = getenv("EXPECTED_DB_NAME");

    if (
        $connection->getDriverName() !== "mysql" ||
        ($config["host"] ?? null) !== $expectedHost ||
        $connection->getDatabaseName() !== $expectedDatabase
    ) {
        fwrite(STDERR, "FAIL: Laravel is not connected to the disposable database." . PHP_EOL);
        exit(1);
    }

    echo "Disposable database connection verified." . PHP_EOL;
'

echo "Running migrations against disposable database..."
docker compose exec -T "${db_env[@]}" app php artisan migrate --force

echo "Creating owner, admin, target, and temporary token..."
SEED_OUTPUT="$(docker compose exec -T "${db_env[@]}" app php artisan tinker --execute='
    $owner = \App\Models\User::factory()->create();
    $admin = \App\Models\User::factory()->create();
    $target = \App\Models\User::factory()->create();
    $organization = \App\Models\Organization::factory()->create();

    $organization->users()->attach($owner, ["role" => "owner"]);
    $organization->users()->attach($admin, ["role" => "admin"]);
    $organization->users()->attach($target, ["role" => "member"]);

    $adminMembership = \App\Models\Membership::query()
        ->where("organization_id", $organization->id)
        ->where("user_id", $admin->id)
        ->firstOrFail();

    $targetMembership = \App\Models\Membership::query()
        ->where("organization_id", $organization->id)
        ->where("user_id", $target->id)
        ->firstOrFail();

    echo "SAASKIT_RACE_RESULT=" . json_encode([
        "organization_id" => $organization->id,
        "organization_public_id" => $organization->public_id,
        "admin_membership_id" => $adminMembership->id,
        "target_membership_public_id" => $targetMembership->public_id,
        "token" => $admin->createToken("temporary-concurrency-test")->plainTextToken,
    ]) . PHP_EOL;
' 2>&1)"

RESULT_JSON="$(printf '%s\n' "$SEED_OUTPUT" | sed -n 's/^SAASKIT_RACE_RESULT=//p' | tail -n 1)"
if [[ -z "$RESULT_JSON" ]] || ! printf '%s' "$RESULT_JSON" | python3 -c '
import json, sys
d = json.load(sys.stdin)
assert all(d.get(k) for k in (
    "organization_id", "organization_public_id", "admin_membership_id",
    "target_membership_public_id", "token"
))
' >/dev/null 2>&1; then
    echo "FAIL: test fixture creation failed. Diagnostic lines:"
    printf '%s\n' "$SEED_OUTPUT" | grep -E 'ERROR|Exception|Error|failed' | tail -20 || true
    exit 1
fi

printf '%s' "$RESULT_JSON" > "$TMP_DIR/fixtures.json"
ORG_PK="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["organization_id"])' "$TMP_DIR/fixtures.json")"
ORG_PUBLIC_ID="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["organization_public_id"])' "$TMP_DIR/fixtures.json")"
ADMIN_MEMBERSHIP_PK="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["admin_membership_id"])' "$TMP_DIR/fixtures.json")"
TARGET_PUBLIC_ID="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["target_membership_public_id"])' "$TMP_DIR/fixtures.json")"
TOKEN="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["token"])' "$TMP_DIR/fixtures.json")"

echo "Starting a separate Laravel HTTP container..."
docker compose run -d --no-deps \
    --name "$HTTP_CONTAINER" \
    "${db_env[@]}" \
    app php artisan serve --host=0.0.0.0 --port=8001 --no-reload >/dev/null
HTTP_STARTED=1

HTTP_READY=NO
LAST_PROBE="No HTTP response received yet."
for _ in $(seq 1 20); do
    LAST_PROBE="$(docker exec "$HTTP_CONTAINER" curl --max-time 3 -sS \
        -H "Accept: application/json" \
        -w '\nHTTP_STATUS=%{http_code}\n' \
        http://127.0.0.1:8001/api/v1/organizations 2>&1 || true)"
    PROBE_STATUS="$(printf '%s\n' "$LAST_PROBE" | sed -n 's/^HTTP_STATUS=//p' | tail -1)"

    if [[ "$PROBE_STATUS" == "401" || "$PROBE_STATUS" == "200" ]]; then
        HTTP_READY=YES
        echo "HTTP server responded during readiness check: $PROBE_STATUS"
        if [[ "$PROBE_STATUS" != "200" && "$PROBE_STATUS" != "401" ]]; then
            echo "Readiness response body and status:"
            printf '%s\n' "$LAST_PROBE"
        fi
        break
    fi
    sleep 1
done

if [[ "$HTTP_READY" != YES ]]; then
    echo "FAIL: isolated Laravel HTTP server did not return an HTTP status."
    echo "Last readiness probe output:"
    printf '%s\n' "$LAST_PROBE"
    echo "HTTP server logs:"
    docker logs "$HTTP_CONTAINER" 2>&1 | tail -30 || true
    exit 1
fi

echo "HTTP server ready; preparing the lock race."
LOCK_SCRIPT='
    \DB::transaction(function () {
        \DB::table("organizations")
            ->where("id", (int) getenv("RACE_ORG_PK"))
            ->lockForUpdate()->first();

        \DB::table("organization_user")
            ->where("id", (int) getenv("RACE_ADMIN_MEMBERSHIP_PK"))
            ->update(["role" => "member"]);

        file_put_contents(getenv("RACE_MARKER"), "ready");
        sleep(12);
    });
    echo "LOCK_TRANSACTION_COMMITTED" . PHP_EOL;
'

docker compose exec -T \
    "${db_env[@]}" \
    -e RACE_ORG_PK="$ORG_PK" \
    -e RACE_ADMIN_MEMBERSHIP_PK="$ADMIN_MEMBERSHIP_PK" \
    -e RACE_MARKER="$MARKER" \
    app php artisan tinker --execute="$LOCK_SCRIPT" >"$LOCK_LOG" 2>&1 &
LOCK_PID=$!

LOCK_READY=NO
for _ in $(seq 1 20); do
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
    echo "FAIL: lock transaction did not reach its ready marker."
    grep -E 'ERROR|Exception|Error|failed' "$LOCK_LOG" | tail -20 || true
    exit 1
fi

echo "Lock held; administrator demotion is uncommitted."
docker exec \
    -e RACE_TOKEN="$TOKEN" \
    -e RACE_ORG="$ORG_PUBLIC_ID" \
    -e RACE_TARGET="$TARGET_PUBLIC_ID" \
    "$HTTP_CONTAINER" sh -lc '
        curl --max-time 25 -sS \
            -o /tmp/saaskit-race-response.json \
            -w "%{http_code}" \
            -X PATCH \
            -H "Accept: application/json" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $RACE_TOKEN" \
            --data "{\"role\":\"admin\"}" \
            "http://127.0.0.1:8001/api/v1/organizations/$RACE_ORG/members/$RACE_TARGET"
    ' >"$STATUS_FILE" 2>"$CURL_LOG" &
CURL_PID=$!

sleep 3
if kill -0 "$CURL_PID" 2>/dev/null; then
    BLOCKED=YES
    echo "Request remained in progress while the lock was held."
else
    BLOCKED=NO
    echo "FAIL: request finished before the lock was released."
fi

if wait "$LOCK_PID"; then
    LOCK_EXIT=0
else
    LOCK_EXIT=$?
fi
LOCK_PID=""

if wait "$CURL_PID"; then
    CURL_EXIT=0
else
    CURL_EXIT=$?
fi
CURL_PID=""

STATUS="$(tr -cd '0-9' < "$STATUS_FILE" 2>/dev/null || true)"
docker cp "$HTTP_CONTAINER:/tmp/saaskit-race-response.json" "$BODY_FILE" >/dev/null 2>&1 || true

echo "Lock transaction exit: $LOCK_EXIT"
echo "HTTP command exit: $CURL_EXIT"
echo "HTTP status: ${STATUS:-missing}"
echo "Response body:"
cat "$BODY_FILE" 2>/dev/null || echo "(response body unavailable)"

echo
echo "Verifying final membership roles..."
VERIFY_OUTPUT="$(docker compose exec -T \
    "${db_env[@]}" \
    -e RACE_ADMIN_MEMBERSHIP_PK="$ADMIN_MEMBERSHIP_PK" \
    -e RACE_TARGET_PUBLIC_ID="$TARGET_PUBLIC_ID" \
    app php artisan tinker --execute='
        echo "SAASKIT_ROLES=" . json_encode([
            "admin_role" => \DB::table("organization_user")
                ->where("id", (int) getenv("RACE_ADMIN_MEMBERSHIP_PK"))
                ->value("role"),
            "target_role" => \DB::table("organization_user")
                ->where("public_id", getenv("RACE_TARGET_PUBLIC_ID"))
                ->value("role"),
        ]) . PHP_EOL;
    ' 2>&1)"
ROLES_JSON="$(printf '%s\n' "$VERIFY_OUTPUT" | sed -n 's/^SAASKIT_ROLES=//p' | tail -n 1)"

if [[ -z "$ROLES_JSON" ]]; then
    echo "FAIL: could not verify final roles."
    printf '%s\n' "$VERIFY_OUTPUT" | grep -E 'ERROR|Exception|Error' | tail -20 || true
    exit 1
fi

python3 -c '
import json, sys

try:
    data = json.loads(sys.argv[1])
except (json.JSONDecodeError, TypeError) as exc:
    print(f"FAIL: final role verification returned invalid JSON: {exc}", file=sys.stderr)
    sys.exit(1)

required = ("admin_role", "target_role")
missing = [key for key in required if key not in data or data[key] is None]

if missing:
    print(
        "FAIL: final role verification is missing required fields: "
        + ", ".join(missing),
        file=sys.stderr,
    )
    print("Received:", repr(data), file=sys.stderr)
    sys.exit(1)

print("Admin role:", data["admin_role"])
print("Target role:", data["target_role"])

unexpected = {
    key: data[key]
    for key in required
    if data[key] != "member"
}

if unexpected:
    print(
        "FAIL: final membership roles differ from expected values: "
        + json.dumps(unexpected, sort_keys=True),
        file=sys.stderr,
    )
    sys.exit(1)
' "$ROLES_JSON"

if [[ "$BLOCKED" == YES && "$STATUS" == "403" &&
      "$LOCK_EXIT" -eq 0 && "$CURL_EXIT" -eq 0 ]] &&
   printf '%s' "$ROLES_JSON" | python3 -c '
import json, sys
d = json.load(sys.stdin)
sys.exit(0 if d.get("admin_role") == "member" and d.get("target_role") == "member" else 1)
'; then
    echo "MARIADB CONCURRENCY REGRESSION: PASS"
else
    echo "MARIADB CONCURRENCY REGRESSION: FAIL OR INCONCLUSIVE"
    [[ ! -s "$CURL_LOG" ]] || cat "$CURL_LOG"
    exit 1
fi
