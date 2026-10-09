# SaaSKit Application

This directory contains the Laravel application used by SaaSKit.

For installation instructions, Docker configuration, environment setup, database migrations, API access, and security guidance, see the [project README](../README.md).

## Useful commands

Run these commands from the repository root:

```bash
docker compose exec app php artisan route:list --path=api/v1
docker compose exec app php artisan test
docker compose logs --tail=100 app
```

The automated test suite uses an in-memory SQLite database. The running application uses the database configured in `app/.env`.
