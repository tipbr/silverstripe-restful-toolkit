# react-sdk

TypeScript SDK for React single-page apps consuming the `silverstripe-api` module.

## Features

- Provider for API config + TanStack Query setup
- Browser `localStorage` token store fallback (or inject your own `TokenStorage`)
- Axios client with JWT auth header and refresh retry on `401`
- Optional session-cookie mode for Silverstripe session auth
- Fully typed auth hooks and generic CRUD hook factory

## Installation

```bash
npm install react-silverstripe-sdk axios @tanstack/react-query
```

## Provider Setup

```tsx
import React from 'react';
import { QueryClient } from '@tanstack/react-query';
import { SilverstripeApiProvider } from 'react-silverstripe-sdk';

const queryClient = new QueryClient();

export const App = () => (
  <SilverstripeApiProvider
    baseUrl="https://api.example.com"
    queryClient={queryClient}
    authMode="jwt"
    idMapping={{
      enabled: true,
      shortIds: true,
      shortIdLength: 8,
    }}
    onAuthFailure={() => {
      // e.g. redirect to login route
    }}
  >
    {/* app */}
  </SilverstripeApiProvider>
);
```

## Session-based Silverstripe Auth

If your backend uses Silverstripe cookie/session auth (instead of JWT access tokens), use:

```tsx
<SilverstripeApiProvider
  baseUrl="https://api.example.com"
  authMode="session"
  withCredentials
>
  {/* app */}
</SilverstripeApiProvider>
```

This mode sends cookies (`withCredentials`) and disables token refresh flow. `useIsAuthenticated` checks `/api/v1/auth/me` to determine session state.

## ID Mapping Options

The provider supports optional ID mapping for obfuscated backend IDs:

- `idMapping.enabled` (default `false`) enables client-side ID mapping support
- `idMapping.shortIds` (default `false`) shortens UUID IDs in hook results/query keys
- `idMapping.shortIdLength` (default `8`) controls generated short ID length

When enabled, hooks convert short IDs back to full UUIDs before API requests.

## Login Example (JWT mode)

```tsx
import { useLogin } from 'react-silverstripe-sdk';

const LoginButton = () => {
  const login = useLogin();

  return (
    <button
      onClick={() =>
        login.mutate({
          email: 'user@example.com',
          password: 'secret',
        })
      }
    >
      Login
    </button>
  );
};
```

## Email Pre-validation (onBlur)

`useCheckEmail` calls `POST /api/v1/auth/checkemail` and validates the email format as well as the domain MX record server-side.

```tsx
import { useCheckEmail } from 'react-silverstripe-sdk';

const EmailField = () => {
  const checkEmail = useCheckEmail();

  return (
    <input
      type="email"
      onBlur={(e) => checkEmail.mutate({ email: e.target.value })}
    />
  );
};
// checkEmail.data?.valid — overall validity
// checkEmail.data?.format_valid — email syntax only
// checkEmail.data?.mx_valid — MX record found
// checkEmail.data?.mx_checked — whether MX lookup was performed
```

## Password Strength (debounce recommended)

`useCheckPassword` calls `POST /api/v1/auth/checkpassword` and validates the password against the server-side policy while also returning a strength score. Fire it on every keystroke with a short debounce (e.g. 300 ms) to avoid unnecessary network requests.

```tsx
import { useCheckPassword } from 'react-silverstripe-sdk';

const PasswordField = () => {
  const checkPassword = useCheckPassword();

  return (
    <>
      <input
        type="password"
        onKeyUp={(e) => checkPassword.mutate({ password: e.currentTarget.value })}
      />
      {checkPassword.data && (
        <span>Strength: {checkPassword.data.strength.label}</span>
      )}
    </>
  );
};
// checkPassword.data?.valid — passes server policy
// checkPassword.data?.errors — policy violation messages
// checkPassword.data?.strength.score — 0-4
// checkPassword.data?.strength.label — 'weak' | 'medium' | 'strong'
```

## CRUD Hook Factory

```tsx
import { createCrudHooks } from 'react-silverstripe-sdk';

type Todo = { ID: string | number; Title: string; Body: string };

const todoHooks = createCrudHooks<Todo>('/todos');

const Todos = () => {
  const list = todoHooks.useList({ page: 1, per_page: 20 });

  return (
    <ul>
      {(list.data?.data ?? []).map((item) => (
        <li key={item.ID}>{item.Title}</li>
      ))}
    </ul>
  );
};
```

## Exports

- `SilverstripeApiProvider`, `useApiConfig`
- Auth hooks (`useLogin`, `useRegister`, `useCheckEmail`, `useCheckPassword`, `useMe`, etc.)
- `createCrudHooks`
- API types and request/response types
- `TokenStorage` interface
