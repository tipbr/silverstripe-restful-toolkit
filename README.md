# silverstripe-restful-toolkit

Monorepo containing three packages:

- `packages/silverstripe-api` — Silverstripe 6 module for CRUD API scaffolding + JWT auth
- `packages/react-native-sdk` — TypeScript React Native SDK for consuming the API
- `packages/react-sdk` — TypeScript React SDK for SPAs and browser clients

## Packages

### 1) silverstripe-api

A Composer-installable module that provides:

- Base API controller helpers (`apiResponse`, pagination, body parsing, required-field validation)
- CRUD scaffolding controller with DataObject permission checks
- CORS middleware
- JWT access-token middleware and auth helpers
- Full auth controller endpoints with refresh sessions in DB

See: [`packages/silverstripe-api/README.md`](packages/silverstripe-api/README.md)

### 2) react-native-sdk

A strict TypeScript SDK that provides:

- API provider + React Query integration
- Typed token storage abstraction
- Axios auth + refresh interceptors
- Auth hooks and generic CRUD hooks factory

See: [`packages/react-native-sdk/README.md`](packages/react-native-sdk/README.md)

### 3) react-sdk

A strict TypeScript SDK for browser-based React apps that provides:

- API provider + React Query integration
- Browser token storage abstraction (with injectable custom storage)
- Axios auth/refresh interceptors
- Optional session-cookie auth mode for Silverstripe session-based auth
- Auth hooks and generic CRUD hooks factory

See: [`packages/react-sdk/README.md`](packages/react-sdk/README.md)

## Repository Strategy

This monorepo is structured to support an eventual split into separate repositories while keeping APIs aligned during initial co-development.

Proposed split plan:

1. Extract `packages/silverstripe-api` to its own repository and publish as a Composer package.
2. Extract `packages/react-native-sdk` to its own repository and publish to npm.
3. Extract `packages/react-sdk` to its own repository and publish to npm.
4. Keep shared contract compatibility via versioned API docs and CI contract tests.
