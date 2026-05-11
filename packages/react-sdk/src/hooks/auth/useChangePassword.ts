import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, ApiSuccess, ChangePasswordRequest } from '../../types';

export const useChangePassword = (
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, ChangePasswordRequest>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, ChangePasswordRequest> =>
  useMutation<ApiSuccess, AxiosError<ApiError>, ChangePasswordRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<ApiSuccess>('/api/v1/auth/change-password', payload);
      return response.data;
    },
    ...options,
  });
