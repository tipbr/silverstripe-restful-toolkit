import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import type { ApiError, ApiSuccess } from '../../types';

export const useDeleteSession = (
  id: number,
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, void>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, void> =>
  useMutation<ApiSuccess, AxiosError<ApiError>, void>({
    mutationFn: async () => {
      const response = await getApiClient().delete<ApiSuccess>(`/api/v1/auth/sessions/${id}`);
      return response.data;
    },
    ...options,
  });
