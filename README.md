# silverstripe-restful-toolkit

A reusable Silverstripe 6 module that provides:

- CRUD API scaffolding for `DataObject` classes
- JWT access token authentication
- Refresh-token sessions backed by `ApiSession`
- Auth endpoints for registration, login, profile, sessions, and password flows
- Configurable object-sharing invitations with read/write permissions

## Installation

```bash
composer require tipbr/silverstripe-restful-toolkit
```

Requirements:

- PHP 8.2+
- Silverstripe Framework 6+
- `firebase/php-jwt`

## Configuration

Configure values in YAML (for example in your host app):

```yml
App\Api\Services\JwtService:
  secret: '`SS_JWT_SECRET`'
  access_token_expiry: 900
  refresh_token_expiry: 2592000
  rotate_refresh_tokens: true

App\Api\Services\IdObfuscationService:
  enabled: false
  uuid_type: v4

App\Api\Controllers\ApiController:
  max_page_size: 100

App\Api\Controllers\AuthController:
  min_password_length: 8

App\Api\Services\SharingService:
  default_share_expiry: 604800
  block_reinvite_after_decline: true
  allow_self_invite: false
  allowed_permissions:
    - read
    - write
  default_permission: read
  resources:
    posts: App\Model\Post
  default_resource_namespace: 'App\\Model\\'

App\Api\Middleware\CorsMiddleware:
  allowed_origins:
    - 'http://localhost:8081'

App\Api\Controllers\CrudApiController:
  resources:
    posts: App\Model\Post
    comments: App\Model\Comment

App\Model\Post:
  extensions:
    - App\Api\Extensions\ShareableObjectExtension
  share_owner_field: OwnerID
```

Security note: avoid `'*'` in production `allowed_origins` unless the API is intentionally public for all origins.
If `allowed_origins` is omitted or empty, CORS headers are not added (all cross-origin browser requests are effectively blocked).

## CRUD Scaffolding

Expose resources under `/api/v1/{resource}` and `/api/v1/{resource}/{id}`.
When `App\Api\Services\IdObfuscationService.enabled` is true, API IDs are UUIDs (configured by `uuid_type`) instead of incremental DB integers.

By default, the CRUD controller resolves resource names to `App\Model\{StudlyCaseResource}` and only serializes:

1. `private static array $api_fields` if defined on the `DataObject`
2. otherwise, `db` fields only

```php
private static array $api_fields = [
    'Title',
    'Body',
    'Created',
];
```

### Restricting writable fields

By default the same fields that are serialized in GET responses (`api_fields`) are also accepted on
POST / PUT / PATCH. If you want to expose read-only fields (e.g. `Slug`, `Created`) in responses
without allowing clients to set them, define `api_write_fields` on the DataObject:

```php
private static array $api_fields = [
    'Title',
    'Body',
    'Slug',    // returned on reads …
    'Created', // … but not writable
];

private static array $api_write_fields = [
    'Title',
    'Body',    // only these fields are accepted on POST/PUT/PATCH
];
```

When `api_write_fields` is absent, write access falls back to `api_fields` (existing behaviour).

Permissions are enforced with `canView`, `canCreate`, `canEdit`, and `canDelete`.

## Rate Limiting

Silverstripe Framework ships with `SilverStripe\Control\Middleware\RateLimitMiddleware`.
Apply it to sensitive endpoints (login, register, forgot-password, refresh) in your host app's
`_config/middleware.yml`:

```yml
SilverStripe\Control\Director:
  middlewares:
    AuthRateLimit: '%$SilverStripe\Control\Middleware\RateLimitMiddleware'

SilverStripe\Control\Middleware\RateLimitMiddleware:
  max_attempts: 10
  decay_seconds: 60
  urls:
    - 'api/v1/auth/login'
    - 'api/v1/auth/register'
    - 'api/v1/auth/refresh'
    - 'api/v1/auth/forgot-password'
```

Adjust `max_attempts` and `decay_seconds` to suit your threat model.


## Auth Endpoints

All endpoints are under `/api/v1/auth/`.

- `POST /register` — create account (`email`, `password`, `first_name`, `last_name`, optional `device_name`)
- `POST /login` — authenticate (`email`, `password`, optional `device_name`)
- `POST /checkemail` — pre-validate an email address (format + MX lookup); safe to call unauthenticated
- `POST /checkpassword` — pre-validate a password against server policy and return strength metadata; safe to call unauthenticated
- `POST /refresh` — exchange a refresh token for a new access token
- `POST /logout` — revoke a specific session (`session_id`)
- `POST /logout-all` — revoke all sessions for the authenticated member
- `GET /sessions` — list active sessions for the authenticated member
- `DELETE /sessions/:id` — revoke a session by ID
- `POST /forgot-password` — send a password-reset email (`email`)
- `POST /reset-password` — set a new password via reset token (`token`, `email`, `password`)
- `POST /change-password` — change password for the authenticated member (`current_password`, `new_password`)
- `GET /profile` — return the authenticated member's profile
- `PUT /profile` — update the authenticated member's profile (`first_name`, `last_name`)

### `POST /checkemail` response

```json
{
  "email": "user@example.com",
  "format_valid": true,
  "mx_valid": true,
  "mx_check_available": true,
  "mx_checked": true,
  "valid": true
}
```

`valid` is `true` when the format is valid and (if MX checking was performed) an MX record was found. When `mx_check_available` is `false` (e.g. on some hosting environments), `valid` reflects format validity only.

### `POST /checkpassword` response

```json
{
  "valid": true,
  "errors": [],
  "strength": {
    "score": 3,
    "label": "strong"
  }
}
```

`strength.label` is one of `"weak"`, `"medium"`, or `"strong"`. `strength.score` ranges from `0` (no criteria met) to `4` (all criteria met). `errors` contains any policy violation messages when `valid` is `false`.

## Sharing Endpoints

All endpoints are under `/api/v1/shares/`.

- `POST /invite` — create invitation (`resource`, `object_id`, `invitee_email`, optional `permission`, `expires_in_seconds`, `message`)
- `POST /{id}/accept` — accept invitation
- `POST /{id}/decline` — decline invitation (optional `reason`)
- `DELETE /{id}` — revoke invitation
- `GET /mine` — list invitations for current member
- `GET /object?resource=posts&object_id=123` — list invitations for one shareable object

If `block_reinvite_after_decline` is enabled, a previously declined invite cannot be re-created for the same object/member pair.

### Example Login

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password","device_name":"iPhone"}'
```

## JWT Middleware

`App\Api\Middleware\JwtAuthMiddleware` validates `Authorization: Bearer <token>` and injects the member into route params.

Use `RequiresJwtAuth` in controllers for:

- `getCurrentMember()`
- `requireAuth()`

## API Session Model

`ApiSession` fields:

- `RefreshToken`
- `UserAgent`
- `IPAddress`
- `DeviceName`
- `LastUsed`
- `ExpiresAt`
- `MemberID`

## Notes

- Access token is stateless and short-lived (default 15 min)
- Refresh token is long-lived and persisted (default 30 days)
- Refresh token rotation is configurable
