# SaaSKit

Laravel SaaS application platform.

This repository contains the SaaSKit application and its application-level Docker deployment configuration.

Infrastructure configuration for the host is maintained separately in the dev-01-infrastructure repository.

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
