import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, CheckEmailRequest, CheckEmailResponse } from '../../types';

export const useCheckEmail = (
  options?: UseMutationOptions<CheckEmailResponse, AxiosError<ApiError>, CheckEmailRequest>,
): UseMutationResult<CheckEmailResponse, AxiosError<ApiError>, CheckEmailRequest> =>
  useMutation<CheckEmailResponse, AxiosError<ApiError>, CheckEmailRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<CheckEmailResponse>('/api/v1/auth/checkemail', payload);
      return response.data;
    },
    ...options,
  });
