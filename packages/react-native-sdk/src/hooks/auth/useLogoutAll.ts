import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { clearAuthTokens } from '../../storage';
import type { ApiError, ApiSuccess } from '../../types';

export const useLogoutAll = (
  options?: UseMutationOptions<ApiSuccess, AxiosError<ApiError>, void>,
): UseMutationResult<ApiSuccess, AxiosError<ApiError>, void> => {
  const { tokenStorage } = useApiConfig();

  return useMutation<ApiSuccess, AxiosError<ApiError>, void>({
    mutationFn: async () => {
      const response = await getApiClient().post<ApiSuccess>('/api/v1/auth/logout-all');
      return response.data;
    },
    ...options,
    onSuccess: async (data, variables, onMutateResult, context) => {
      await clearAuthTokens(tokenStorage);
      await options?.onSuccess?.(data, variables, onMutateResult, context);
    },
  });
};
