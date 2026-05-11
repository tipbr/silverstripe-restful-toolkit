import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { configureApiClient } from './client';
import { IdMapper, type IdMappingConfig } from './idMapping';
import { createDefaultTokenStorage, type TokenStorage } from './storage';

interface ApiConfigContextValue {
  baseUrl: string;
  tokenStorage: TokenStorage;
  idMapper: IdMapper;
  onAuthFailure?: () => void;
}

const ApiConfigContext = createContext<ApiConfigContextValue | null>(null);

export interface SilverstripeApiProviderProps {
  baseUrl: string;
  queryClient?: QueryClient;
  tokenStorage?: TokenStorage;
  idMapping?: IdMappingConfig;
  onAuthFailure?: () => void;
  children: React.ReactNode;
}

export const SilverstripeApiProvider = ({
  baseUrl,
  queryClient,
  tokenStorage,
  idMapping,
  onAuthFailure,
  children,
}: SilverstripeApiProviderProps): React.ReactElement => {
  const [client] = useState<QueryClient>(() => queryClient ?? new QueryClient());
  const storage = useMemo<TokenStorage>(() => tokenStorage ?? createDefaultTokenStorage(), [tokenStorage]);
  const idMapper = useMemo(
    () =>
      new IdMapper({
        enabled: idMapping?.enabled,
        shortIds: idMapping?.shortIds,
        shortIdLength: idMapping?.shortIdLength,
      }),
    [idMapping?.enabled, idMapping?.shortIds, idMapping?.shortIdLength],
  );

  useEffect(() => {
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
      idMapper,
      onAuthFailure,
    }),
    [baseUrl, storage, idMapper, onAuthFailure],
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
