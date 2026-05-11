import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, ApiSuccess, ForgotPasswordRequest } from '../../types';

export const useForgotPassword = (
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, ForgotPasswordRequest>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, ForgotPasswordRequest> =>
  useMutation<ApiSuccess, AxiosError<ApiError>, ForgotPasswordRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<ApiSuccess>('/api/v1/auth/forgot-password', payload);
      return response.data;
    },
    ...options,
  });
