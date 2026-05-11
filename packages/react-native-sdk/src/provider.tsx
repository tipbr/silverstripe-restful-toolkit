import React, { createContext, useContext, useMemo, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { configureApiClient } from './client';
import { createDefaultTokenStorage, type TokenStorage } from './storage';

interface ApiConfigContextValue {
  baseUrl: string;
  tokenStorage: TokenStorage;
  onAuthFailure?: () => void;
}

const ApiConfigContext = createContext<ApiConfigContextValue | null>(null);

export interface SilverstripeApiProviderProps {
  baseUrl: string;
  queryClient?: QueryClient;
  tokenStorage?: TokenStorage;
  onAuthFailure?: () => void;
  children: React.ReactNode;
}

export const SilverstripeApiProvider = ({
  baseUrl,
  queryClient,
  tokenStorage,
  onAuthFailure,
  children,
}: SilverstripeApiProviderProps): JSX.Element => {
  const [client] = useState<QueryClient>(() => queryClient ?? new QueryClient());
  const storage = useMemo<TokenStorage>(() => tokenStorage ?? createDefaultTokenStorage(), [tokenStorage]);

  useMemo(() => {
    configureApiClient({
      baseUrl,
      tokenStorage: storage,
      onAuthFailure,
    });
  }, [baseUrl, storage, onAuthFailure]);

  const contextValue = useMemo<ApiConfigContextValue>(
    () => ({
      baseUrl,
      tokenStorage: storage,
      onAuthFailure,
    }),
    [baseUrl, storage, onAuthFailure],
  );

  return (
    <ApiConfigContext.Provider value={contextValue}>
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    </ApiConfigContext.Provider>
  );
};

export const useApiConfig = (): ApiConfigContextValue => {
  const context = useContext(ApiConfigContext);
  if (!context) {
    throw new Error('useApiConfig must be used inside SilverstripeApiProvider');
  }

  return context;
};
