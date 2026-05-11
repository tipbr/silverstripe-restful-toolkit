import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import type { ApiError, ApiSuccess, Identifier } from '../../types';

export const useDeleteSession = (
  id: Identifier,
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, void>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, void> => {
  const { idMapper } = useApiConfig();

  return useMutation<ApiSuccess, AxiosError<ApiError>, void>({
    mutationFn: async () => {
      const response = await getApiClient().delete<ApiSuccess>(`/api/v1/auth/sessions/${idMapper.toApiId(id)}`);
      return response.data;
    },
    ...options,
  });
};
