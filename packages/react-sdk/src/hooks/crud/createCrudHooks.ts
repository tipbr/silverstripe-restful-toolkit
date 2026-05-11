import {
  useMutation,
  useQuery,
  useQueryClient,
  type QueryKey,
  type UseMutationOptions,
  type UseMutationResult,
  type UseQueryOptions,
  type UseQueryResult,
} from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { getApiClient } from '../../client';
import { useApiConfig } from '../../provider';
import type { ApiError, Identifier, PaginatedQueryParams, PaginatedResponse } from '../../types';
type CreatePayload<T> = Partial<T>;

type UpdatePayload<T> = {
  id: Identifier;
  data: Partial<T>;
};

const normalizePath = (resourcePath: string): string => {
  const path = resourcePath.startsWith('/') ? resourcePath : `/${resourcePath}`;
  return `/api/v1${path}`;
};

const toQueryParams = (params?: PaginatedQueryParams): Record<string, string | number> => {
  if (!params) {
    return {};
  }

  const serialized: Record<string, string | number> = {};

  if (typeof params.page === 'number') {
    serialized.page = params.page;
  }

  if (typeof params.per_page === 'number') {
    serialized.per_page = params.per_page;
  }

  if (params.filters) {
    Object.entries(params.filters).forEach(([key, value]) => {
      serialized[key] = value;
    });
  }

  return serialized;
};

export const createCrudHooks = <T>(resourcePath: string) => {
  const endpoint = normalizePath(resourcePath);
  const listKey = [resourcePath] as const;

  const useList = (
    params?: PaginatedQueryParams,
    options?: Omit<UseQueryOptions<PaginatedResponse<T>, AxiosError<ApiError>>, 'queryKey' | 'queryFn'>,
  ): UseQueryResult<PaginatedResponse<T>, AxiosError<ApiError>> => {
    const { idMapper } = useApiConfig();

    return useQuery<PaginatedResponse<T>, AxiosError<ApiError>>({
      queryKey: [...listKey, params] as QueryKey,
      queryFn: async () => {
        const response = await getApiClient().get<PaginatedResponse<T>>(endpoint, {
          params: toQueryParams(params),
        });
        return idMapper.mapValueForClient(response.data);
      },
      ...options,
    });
  };

  const useGet = (
    id: Identifier,
    options?: Omit<UseQueryOptions<T, AxiosError<ApiError>>, 'queryKey' | 'queryFn'>,
  ): UseQueryResult<T, AxiosError<ApiError>> => {
    const { idMapper } = useApiConfig();

    return useQuery<T, AxiosError<ApiError>>({
      queryKey: [...listKey, idMapper.toClientId(id)] as QueryKey,
      queryFn: async () => {
        const response = await getApiClient().get<{ data: T }>(`${endpoint}/${idMapper.toApiId(id)}`);
        return idMapper.mapValueForClient(response.data.data);
      },
      ...options,
    });
  };

  const useCreate = (
    options?: UseMutationOptions<T, AxiosError<ApiError>, CreatePayload<T>>,
  ): UseMutationResult<T, AxiosError<ApiError>, CreatePayload<T>> => {
    const queryClient = useQueryClient();
    const { idMapper } = useApiConfig();

    return useMutation<T, AxiosError<ApiError>, CreatePayload<T>>({
      mutationFn: async (payload) => {
        const response = await getApiClient().post<{ data: T }>(endpoint, payload);
        return idMapper.mapValueForClient(response.data.data);
      },
      ...options,
      onSuccess: async (data, variables, onMutateResult, context) => {
        await queryClient.invalidateQueries({ queryKey: listKey as unknown as QueryKey });
        await options?.onSuccess?.(data, variables, onMutateResult, context);
      },
    });
  };

  const useUpdate = (
    options?: UseMutationOptions<T, AxiosError<ApiError>, UpdatePayload<T>>,
  ): UseMutationResult<T, AxiosError<ApiError>, UpdatePayload<T>> => {
    const queryClient = useQueryClient();
    const { idMapper } = useApiConfig();

    return useMutation<T, AxiosError<ApiError>, UpdatePayload<T>>({
      mutationFn: async ({ id, data }) => {
        const response = await getApiClient().put<{ data: T }>(`${endpoint}/${idMapper.toApiId(id)}`, data);
        return idMapper.mapValueForClient(response.data.data);
      },
      ...options,
      onSuccess: async (data, variables, onMutateResult, context) => {
        await queryClient.invalidateQueries({ queryKey: listKey as unknown as QueryKey });
        await options?.onSuccess?.(data, variables, onMutateResult, context);
      },
    });
  };

  const useDelete = (
    options?: UseMutationOptions<void, AxiosError<ApiError>, Identifier>,
  ): UseMutationResult<void, AxiosError<ApiError>, Identifier> => {
    const queryClient = useQueryClient();
    const { idMapper } = useApiConfig();

    return useMutation<void, AxiosError<ApiError>, Identifier>({
      mutationFn: async (id) => {
        await getApiClient().delete(`${endpoint}/${idMapper.toApiId(id)}`);
      },
      ...options,
      onSuccess: async (data, variables, onMutateResult, context) => {
        await queryClient.invalidateQueries({ queryKey: listKey as unknown as QueryKey });
        await options?.onSuccess?.(data, variables, onMutateResult, context);
      },
    });
  };

  return {
    useList,
    useGet,
    useCreate,
    useUpdate,
    useDelete,
  };
};
