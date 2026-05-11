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

## CRUD Hook Factory

```tsx
import { createCrudHooks } from 'react-silverstripe-sdk';

type Todo = { ID: number; Title: string; Body: string };

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
- Auth hooks (`useLogin`, `useRegister`, `useMe`, etc.)
- `createCrudHooks`
- API types and request/response types
- `TokenStorage` interface
