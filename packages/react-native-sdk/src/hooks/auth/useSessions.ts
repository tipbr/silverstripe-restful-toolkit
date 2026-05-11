import { useQuery, type UseQueryOptions, type UseQueryResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, SessionInfo } from '../../types';

interface SessionsEnvelope {
  data: SessionInfo[];
}

export const useSessions = (
  options?: Omit<UseQueryOptions<SessionInfo[], AxiosError<ApiError>>, 'queryKey' | 'queryFn'>,
): UseQueryResult<SessionInfo[], AxiosError<ApiError>> =>
  useQuery<SessionInfo[], AxiosError<ApiError>>({
    queryKey: ['auth', 'sessions'],
    queryFn: async () => {
      const response = await getApiClient().get<SessionsEnvelope>('/api/v1/auth/sessions');
      return response.data.data;
    },
    ...options,
  });
