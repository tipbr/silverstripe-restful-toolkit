import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { getAuthTokens } from '../../storage';

interface JwtPayload {
  exp?: number;
}

const decodePayload = (token: string): JwtPayload | null => {
  const [, payload] = token.split('.');
  if (!payload) {
    return null;
  }

  try {
    if (typeof globalThis.atob !== 'function') {
      return null;
    }

    const normalized = payload.replace(/-/g, '+').replace(/_/g, '/');
    const decoded = globalThis.atob(normalized);
    const parsed: unknown = JSON.parse(decoded);

    if (typeof parsed === 'object' && parsed !== null) {
      return parsed as JwtPayload;
    }

    return null;
  } catch {
    return null;
  }
};

export const useIsAuthenticated = (): UseQueryResult<boolean, Error> => {
  const { tokenStorage, authMode } = useApiConfig();

  return useQuery<boolean, Error>({
    queryKey: ['auth', 'isAuthenticated'],
    queryFn: async () => {
      if (authMode === 'session') {
        try {
          await getApiClient().get('/api/v1/auth/me');
          return true;
        } catch {
          return false;
        }
      }

      const { access_token: accessToken } = await getAuthTokens(tokenStorage);
      if (!accessToken) {
        return false;
      }

      const payload = decodePayload(accessToken);
      if (!payload?.exp) {
        return false;
      }

      return payload.exp * 1000 > Date.now();
    },
    staleTime: 15_000,
  });
};
