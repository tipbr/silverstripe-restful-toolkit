import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, ApiSuccess, ResetPasswordRequest } from '../../types';

export const useResetPassword = (
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, ResetPasswordRequest>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, ResetPasswordRequest> =>
  useMutation<ApiSuccess, AxiosError<ApiError>, ResetPasswordRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<ApiSuccess>('/api/v1/auth/reset-password', payload);
      return response.data;
    },
    ...options,
  });
