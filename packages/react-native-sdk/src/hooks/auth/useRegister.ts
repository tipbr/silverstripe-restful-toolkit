import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { setAuthTokens } from '../../storage';
import type { ApiError, RegisterRequest, RegisterResponse } from '../../types';

export const useRegister = (
  options?: UseMutationOptions<RegisterResponse, AxiosError<ApiError>, RegisterRequest>,
): UseMutationResult<RegisterResponse, AxiosError<ApiError>, RegisterRequest> => {
  const { tokenStorage } = useApiConfig();

  return useMutation<RegisterResponse, AxiosError<ApiError>, RegisterRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<RegisterResponse>('/api/v1/auth/register', payload);
      return response.data;
    },
    ...options,
    onSuccess: async (data, variables, context) => {
      await setAuthTokens(tokenStorage, data);
      await options?.onSuccess?.(data, variables, context);
    },
  });
};
