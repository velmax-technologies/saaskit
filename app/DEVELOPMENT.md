# SaaSKit Development Guidelines

## Project scope
SaaSKit is a Laravel REST API starter kit. The free edition uses Sanctum
authentication, MariaDB, and a simple Tailwind CSS interface. Do not add
paid-edition features, other authentication drivers, or other database
engines unless explicitly requested.

## Docker workflow
Run commands from the repository root. The Laravel service is `app`,
with working directory `/var/www/html` inside the container.

- Artisan: `docker compose exec -T app php artisan ...`
- Composer: `docker compose exec -T app composer ...`
- Tests: `docker compose exec -T app php artisan test`
- Pint: `docker compose exec -T app vendor/bin/pint --dirty --format agent`

Use Docker services rather than assuming host PHP, Composer, or database
access. Never expose or commit real environment secrets.

## Laravel and API conventions
- Inspect existing code and tests before making changes.
- Preserve existing `/api/v1` conventions and response formats.
- Use Sanctum for free-edition API authentication.
- Validate input and authorize sensitive operations explicitly.
- Follow existing Form Request, API Resource, model, and controller patterns.
- Check installed package versions before relying on version-specific APIs.
- Avoid unnecessary dependencies and abstractions.
- Protect API compatibility unless a breaking change is approved.

## Security and tests
Authentication, authorization, tenant isolation, rate limiting, validation,
and secret handling require particular care.

Add focused PHPUnit regression tests for meaningful behavior changes,
including important failure cases. Reuse existing factories and conventions.
Run the narrowest relevant tests, then broader tests when appropriate.
Run Pint after PHP changes. Report tests or checks that were not run.

Do not run destructive database, migration, reset, or cleanup commands
without explaining their impact and obtaining approval.

## Frontend
The frontend uses Tailwind CSS v4 and Vite. Inspect existing components
and styling first, preserve established visual conventions, and run the
appropriate frontend build when frontend assets change.

## Safe workflow
1. Inspect repository status and relevant files before editing.
2. Preserve unrelated changes and untracked files.
3. Make small, focused changes.
4. Review the diff for unintended changes and secrets.
5. Run relevant tests and checks.
6. Do not stage, commit, revert, or delete files without approval.
