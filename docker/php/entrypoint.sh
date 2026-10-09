#!/bin/sh
set -eu

if [ "$(id -u)" -eq 0 ]; then
    for dir in storage bootstrap/cache; do
        if [ -d "$dir" ]; then
            host_gid="$(stat -c '%g' "$dir")"
            chown -R "www-data:${host_gid}" "$dir"
            chmod -R ug+rwX "$dir"
        fi
    done
fi

exec "$@"
