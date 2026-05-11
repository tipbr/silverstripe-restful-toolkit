# silverstripe-api

A reusable Silverstripe 6 module that provides:

- CRUD API scaffolding for `DataObject` classes
- JWT access token authentication
- Refresh-token sessions backed by `ApiSession`
- Auth endpoints for registration, login, profile, sessions, and password flows

## Installation

```bash
composer require app/silverstripe-api
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

App\Api\Middleware\CorsMiddleware:
  allowed_origins:
    - 'http://localhost:8081'

App\Api\Controllers\CrudApiController:
  resources:
    posts: App\Model\Post
    comments: App\Model\Comment
```

## CRUD Scaffolding

Expose resources under `/api/v1/{resource}` and `/api/v1/{resource}/{id}`.

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

Permissions are enforced with `canView`, `canCreate`, `canEdit`, and `canDelete`.

## Auth Endpoints

All endpoints are under `/api/v1/auth/`.

- `POST /register`
- `POST /login`
- `POST /refresh`
- `POST /logout`
- `POST /logout-all`
- `GET /sessions`
- `DELETE /sessions/:id`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /change-password`
- `GET /me`
- `PUT /me`

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
