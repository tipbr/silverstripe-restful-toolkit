# react-native-sdk

TypeScript SDK for React Native apps consuming the `silverstripe-api` module.

## Features

- Provider for API configuration and React Query setup
- Configurable token storage (`TokenStorage`) with Expo Secure Store fallback
- Axios client with auth header + automatic refresh/retry on 401
- Fully typed auth hooks
- Generic `createCrudHooks<T>()` factory for CRUD resources

## Installation

```bash
npm install react-native-silverstripe-sdk axios @tanstack/react-query
```

## Provider Setup

```tsx
import React from 'react';
import { SilverstripeApiProvider } from 'react-native-silverstripe-sdk';
import { QueryClient } from '@tanstack/react-query';

const queryClient = new QueryClient();

export const App = () => (
  <SilverstripeApiProvider
    baseUrl="https://api.example.com"
    queryClient={queryClient}
    idMapping={{
      enabled: true,
      shortIds: true,
      shortIdLength: 8,
    }}
    onAuthFailure={() => {
      // Redirect to login screen
    }}
  >
    {/* app */}
  </SilverstripeApiProvider>
);
```

## Login Example

```tsx
import React from 'react';
import { Button } from 'react-native';
import { useLogin } from 'react-native-silverstripe-sdk';

export const LoginScreen = () => {
  const login = useLogin();

  return (
    <Button
      title="Login"
      onPress={() =>
        login.mutate({
          email: 'user@example.com',
          password: 'secret',
          device_name: 'iPhone 15',
        })
      }
    />
  );
};
```

## ID Mapping Options

The provider supports optional ID mapping for obfuscated backend IDs:

- `idMapping.enabled` (default `false`) enables client-side ID mapping support
- `idMapping.shortIds` (default `false`) shortens UUID IDs in hook results/query keys
- `idMapping.shortIdLength` (default `8`) controls generated short ID length

When enabled, hooks convert short IDs back to full UUIDs before API requests.

## CRUD Hooks Factory Example

```tsx
import React from 'react';
import { FlatList, Text, View } from 'react-native';
import { createCrudHooks } from 'react-native-silverstripe-sdk';

type Todo = {
  ID: string | number;
  Title: string;
  Body: string;
};

const todoHooks = createCrudHooks<Todo>('/todos');

export const TodoListScreen = () => {
  const list = todoHooks.useList({ page: 1, per_page: 20 });
  const createTodo = todoHooks.useCreate();

  if (list.isLoading) {
    return <Text>Loading...</Text>;
  }

  return (
    <View>
      <FlatList
        data={list.data?.data ?? []}
        keyExtractor={(item) => String(item.ID)}
        renderItem={({ item }) => <Text>{item.Title}</Text>}
      />

      <Text
        onPress={() =>
          createTodo.mutate({
            Title: 'New Todo',
            Body: 'Created from SDK',
          })
        }
      >
        Create Todo
      </Text>
    </View>
  );
};
```

## Exports

- `SilverstripeApiProvider`, `useApiConfig`
- Auth hooks (`useLogin`, `useRegister`, `useMe`, etc.)
- `createCrudHooks`
- Full API and request/response types from `types.ts`
- `TokenStorage` interface
