import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { configureApiClient } from './client';
import { createDefaultTokenStorage, type TokenStorage } from './storage';
import type { AuthMode } from './types';

interface ApiConfigContextValue {
  baseUrl: string;
  tokenStorage: TokenStorage;
  authMode: AuthMode;
  withCredentials: boolean;
  onAuthFailure?: () => void;
}

const ApiConfigContext = createContext<ApiConfigContextValue | null>(null);

export interface SilverstripeApiProviderProps {
  baseUrl: string;
  queryClient?: QueryClient;
  tokenStorage?: TokenStorage;
  authMode?: AuthMode;
  withCredentials?: boolean;
  onAuthFailure?: () => void;
  children: React.ReactNode;
}

export const SilverstripeApiProvider = ({
  baseUrl,
  queryClient,
  tokenStorage,
  authMode = 'jwt',
  withCredentials,
  onAuthFailure,
  children,
}: SilverstripeApiProviderProps): React.ReactElement => {
  const [client] = useState<QueryClient>(() => queryClient ?? new QueryClient());
  const storage = useMemo<TokenStorage>(() => tokenStorage ?? createDefaultTokenStorage(), [tokenStorage]);
  const resolvedWithCredentials = withCredentials ?? authMode === 'session';

  useEffect(() => {
    configureApiClient({
      baseUrl,
      tokenStorage: storage,
      authMode,
      withCredentials: resolvedWithCredentials,
      onAuthFailure,
    });
  }, [baseUrl, storage, authMode, resolvedWithCredentials, onAuthFailure]);

  const contextValue = useMemo<ApiConfigContextValue>(
    () => ({
      baseUrl,
      tokenStorage: storage,
      authMode,
      withCredentials: resolvedWithCredentials,
      onAuthFailure,
    }),
    [baseUrl, storage, authMode, resolvedWithCredentials, onAuthFailure],
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
