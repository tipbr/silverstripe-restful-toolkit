import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { Buffer } from 'buffer';
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
    const normalized = payload.replace(/-/g, '+').replace(/_/g, '/');
    const decoded = Buffer.from(normalized, 'base64').toString('utf-8');
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
  const { tokenStorage } = useApiConfig();

  return useQuery<boolean, Error>({
    queryKey: ['auth', 'isAuthenticated'],
    queryFn: async () => {
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
