# SaaSKit

SaaSKit is a Laravel-based SaaS REST API starter kit for building applications with authentication, organizations, membership management, and invitations.

This repository contains the SaaSKit application and its application-level Docker deployment configuration.

Infrastructure configuration for the host is maintained separately in the `dev-01-infrastructure` repository.

## Features

- Versioned REST API under `/api/v1`
- Laravel Sanctum authentication
- User registration, login, logout, and API token management
- Organization and membership management
- Organization invitation workflows
- Password-reset API
- MariaDB database
- Redis services
- Docker Compose deployment with PHP-FPM, Nginx, queue worker, and scheduler

## Requirements

- Git
- Docker Engine and the Docker Compose plugin
- Permission to create or use Docker networks
- Node.js and npm if frontend assets need to be built

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/velmax-technologies/saaskit.git
cd saaskit
```

### 2. Configure Docker Compose

Create the root environment file:

```bash
cp .env.example .env
```

Edit the root `.env` file and configure the database credentials. Set strong, unique values for `DB_PASSWORD` and `DB_ROOT_PASSWORD`.

Do not commit environment files or production secrets to source control.

### 3. Configure Laravel

Create the Laravel environment file:

```bash
cp app/.env.example app/.env
```

Edit `app/.env` and ensure its database configuration matches the root `.env` settings:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=saaskit
DB_USERNAME=saaskit
DB_PASSWORD=change-me
```

Replace `change-me` with the same database password configured in the root `.env`. Ensure the database name and username match as well.

The root `.env` configures Docker Compose, while `app/.env` configures Laravel.

For production, use the real application URL, set `APP_DEBUG=false`, and configure appropriate secrets and services.

### 4. Configure the Docker network

The Compose configuration requires an external Docker network named `saaskit-proxy`.

Check whether it already exists:

```bash
docker network inspect saaskit-proxy
```

If it does not exist, create it:

```bash
docker network create saaskit-proxy
```

If your infrastructure already manages this network, reuse it rather than creating a duplicate.

### 5. Build and start the services

Validate the Compose configuration:

```bash
docker compose config --quiet
```

Build and start the containers:

```bash
docker compose up -d --build
docker compose ps
```

Check the logs if a service fails to start:

```bash
docker compose logs --tail=100
```

### 6. Initialize the application

After verifying that `app/.env` points to the intended database, generate the application key:

```bash
docker compose exec app php artisan key:generate
```

Run database migrations:

```bash
docker compose exec app php artisan migrate --force
```

Back up any existing database before applying migrations.

### 7. Build frontend assets when required

The PHP container does not include Node.js or npm. If you need to build frontend assets, use a host environment with Node.js and npm installed:

```bash
cd app
npm install
npm run build
cd ..
```

The repository currently has no committed npm lockfile, so dependency versions may vary between installations.

### 8. Access the API

The default Compose configuration exposes Nginx to Docker networks but does not publish its port directly to the host.

Access the API through a reverse proxy connected to `saaskit-proxy`, or configure an appropriate local development port mapping.

Health endpoint:

`GET /api/v1/health`

Once a local port mapping or reverse proxy is configured, you can test the endpoint with:

```bash
curl -i http://localhost/api/v1/health
```

The example assumes the configured proxy or port mapping makes Nginx available at `localhost`.

## Development commands

Run the automated test suite:

```bash
docker compose exec app php artisan test
```

List API routes:

```bash
docker compose exec app php artisan route:list --path=api/v1
```

View application logs:

```bash
docker compose logs --tail=100 app
```

The test suite uses the in-memory SQLite database configured in `app/phpunit.xml`, so it does not require the development MariaDB database.

Stop the services without deleting persistent data:

```bash
docker compose down
```

Avoid `docker compose down -v` unless you intentionally want to remove the named volumes and their stored data.

## Deployment and security

- Never commit `.env` files or production secrets.
- Set `APP_DEBUG=false` in production.
- Configure production email delivery; the default log mailer does not deliver messages to users.
- Configure and verify backups for persistent application data.
- Configure the reverse proxy, TLS, DNS, firewall, and external Docker network for your deployment.

---

## Password Reset API

Password reset is available through the versioned JSON API. Both endpoints
are public and do not require a Sanctum token.

### Request a reset link

`POST /api/v1/auth/forgot-password`

Request:

```json
{
  "email": "user@example.com"
}
```

Success response (200 OK):

```json
{
  "success": true,
  "message": "If an account exists for this email, a password reset link has been sent."
}
```

The response is intentionally the same for known and unknown email addresses
to reduce account enumeration. The email must be valid and no longer than
255 characters.

### Reset the password

POST /api/v1/auth/reset-password

Request:

```json
{
  "email": "user@example.com",
  "token": "TOKEN_FROM_RESET_EMAIL",
  "password": "new-password-123",
  "password_confirmation": "new-password-123"
}
```

Success response (200 OK):

```json
{
  "success": true,
  "message": "Password has been reset successfully."
}
```

The email and token are required. The password must be at least eight
characters and match password_confirmation. Invalid or expired tokens
produce a validation error.

After a successful reset, SaaSKit updates the password, rotates the
remember token, deletes the user's existing API tokens, and dispatches
Laravel's PasswordReset event. The user must authenticate again to obtain
a new API token.

### Rate limits and expiry

Each endpoint allows five requests per minute per client IP. Excess
requests receive HTTP 429 Too Many Requests.

Password-reset tokens expire after 60 minutes by default.

The password broker limits token generation to one attempt per email
every 60 seconds by default.

These settings are defined in the route middleware and
config/auth.php.

### Frontend reset URL

Set PASSWORD_RESET_URL to the absolute URL of the frontend page that
handles password resets. The generated link includes the token and
URL-encoded email query parameters.

For local development, `.env.example` uses:

```dotenv
PASSWORD_RESET_URL=http://localhost:3000/reset-password
```

Replace this with the actual frontend URL for each environment. The frontend
should read both query parameters and submit them to the reset endpoint.

### Email delivery

Laravel's mail configuration defaults to the log mailer. This is useful
for development, but it does not deliver messages to users.

For production, configure a supported mail transport and sender address in
the application's environment. For example, SMTP settings may include:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

These are illustrative values, not working credentials. Use the settings
provided by your email provider, keep credentials out of source control,
and verify delivery before enabling password resets for real users.
