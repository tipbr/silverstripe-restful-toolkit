import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { getAuthTokens, setAuthTokens } from '../../storage';
import type { ApiError, RefreshRequest, RefreshResponse } from '../../types';

export const useRefreshToken = (
  options?: UseMutationOptions<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void>,
): UseMutationResult<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void> => {
  const { tokenStorage } = useApiConfig();

  return useMutation<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void>({
    mutationFn: async (payload) => {
      const tokens = await getAuthTokens(tokenStorage);
      const body: RefreshRequest = payload ?? {
        refresh_token: tokens.refresh_token ?? '',
        session_id: tokens.session_id ?? undefined,
      };

      const response = await getApiClient().post<RefreshResponse>('/api/v1/auth/refresh', body);
      return response.data;
    },
    ...options,
    onSuccess: async (data, variables, context) => {
      await setAuthTokens(tokenStorage, data);
      await options?.onSuccess?.(data, variables, context);
    },
  });
};
