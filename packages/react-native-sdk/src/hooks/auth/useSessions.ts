import { useQuery, type UseQueryOptions, type UseQueryResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import type { ApiError, SessionInfo } from '../../types';

interface SessionsEnvelope {
  data: SessionInfo[];
}

export const useSessions = (
  options?: Omit<UseQueryOptions<SessionInfo[], AxiosError<ApiError>>, 'queryKey' | 'queryFn'>,
): UseQueryResult<SessionInfo[], AxiosError<ApiError>> => {
  const { idMapper } = useApiConfig();

  return useQuery<SessionInfo[], AxiosError<ApiError>>({
    queryKey: ['auth', 'sessions'],
    queryFn: async () => {
      const response = await getApiClient().get<SessionsEnvelope>('/api/v1/auth/sessions');
      return idMapper.mapValueForClient(response.data.data);
    },
    ...options,
  });
};
