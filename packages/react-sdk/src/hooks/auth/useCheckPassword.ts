import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, CheckPasswordRequest, CheckPasswordResponse } from '../../types';

export const useCheckPassword = (
  options?: UseMutationOptions<CheckPasswordResponse, AxiosError<ApiError>, CheckPasswordRequest>,
): UseMutationResult<CheckPasswordResponse, AxiosError<ApiError>, CheckPasswordRequest> =>
  useMutation<CheckPasswordResponse, AxiosError<ApiError>, CheckPasswordRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<CheckPasswordResponse>('/api/v1/auth/checkpassword', payload);
      return response.data;
    },
    ...options,
  });
