import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import { getAuthTokens, setAuthTokens } from '../../storage';
import type { ApiError, RefreshRequest, RefreshResponse } from '../../types';

export const useRefreshToken = (
  options?: UseMutationOptions<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void>,
): UseMutationResult<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void> => {
  const { tokenStorage, idMapper } = useApiConfig();

  return useMutation<RefreshResponse, AxiosError<ApiError>, RefreshRequest | void>({
    mutationFn: async (payload) => {
      const tokens = await getAuthTokens(tokenStorage);
      const body: RefreshRequest = payload
        ? { ...payload }
        : {
            refresh_token: tokens.refresh_token ?? '',
            session_id: tokens.session_id ?? undefined,
          };
      if (body.session_id !== undefined) {
        body.session_id = idMapper.toApiId(body.session_id);
      }

      const response = await getApiClient().post<RefreshResponse>('/api/v1/auth/refresh', body);
      return response.data;
    },
    ...options,
    onSuccess: async (data, variables, mutateContext, context) => {
      await setAuthTokens(tokenStorage, data);
      await options?.onSuccess?.(data, variables, mutateContext, context);
    },
  });
};
