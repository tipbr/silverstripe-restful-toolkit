import { useQuery, type UseQueryOptions, type UseQueryResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, MemberProfile } from '../../types';

interface MeEnvelope {
  data: MemberProfile;
}

export const useMe = (
  options?: Omit<UseQueryOptions<MemberProfile, AxiosError<ApiError>>, 'queryKey' | 'queryFn'>,
): UseQueryResult<MemberProfile, AxiosError<ApiError>> =>
  useQuery<MemberProfile, AxiosError<ApiError>>({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      const response = await getApiClient().get<MeEnvelope>('/api/v1/auth/me');
      return response.data.data;
    },
    ...options,
  });
