import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, MemberProfile } from '../../types';

interface UpdateMeRequest {
  first_name?: string;
  last_name?: string;
}

interface MeEnvelope {
  data: MemberProfile;
}

export const useUpdateMe = (
  options?: UseMutationOptions<MemberProfile, AxiosError<ApiError>, UpdateMeRequest>,
): UseMutationResult<MemberProfile, AxiosError<ApiError>, UpdateMeRequest> =>
  useMutation<MemberProfile, AxiosError<ApiError>, UpdateMeRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().put<MeEnvelope>('/api/v1/auth/me', payload);
      return response.data.data;
    },
    ...options,
  });
