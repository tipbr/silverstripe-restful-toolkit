import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { setAuthTokens } from '../../storage';
import type { ApiError, LoginRequest, LoginResponse } from '../../types';

export const useLogin = (
  options?: UseMutationOptions<LoginResponse, AxiosError<ApiError>, LoginRequest>,
): UseMutationResult<LoginResponse, AxiosError<ApiError>, LoginRequest> => {
  const { tokenStorage } = useApiConfig();

  return useMutation<LoginResponse, AxiosError<ApiError>, LoginRequest>({
    mutationFn: async (payload) => {
      const response = await getApiClient().post<LoginResponse>('/api/v1/auth/login', payload);
      return response.data;
    },
    ...options,
    onSuccess: async (data, variables, onMutateResult, context) => {
      await setAuthTokens(tokenStorage, data);
      await options?.onSuccess?.(data, variables, onMutateResult, context);
    },
  });
};
