import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import { clearAuthTokens, getAuthTokens, setAuthTokens, type TokenStorage } from './storage';
import type { ApiError, RefreshRequest, RefreshResponse } from './types';

interface ApiRuntimeConfig {
  baseUrl: string;
  tokenStorage: TokenStorage;
  onAuthFailure?: () => void;
}

interface RetriableRequestConfig extends InternalAxiosRequestConfig {
  _retry?: boolean;
}

let runtimeConfig: ApiRuntimeConfig | null = null;
let apiClient: AxiosInstance | null = null;
let refreshPromise: Promise<void> | null = null;

const ensureConfigured = (): ApiRuntimeConfig => {
  if (!runtimeConfig) {
    throw new Error('API client has not been configured. Wrap your app with SilverstripeApiProvider.');
  }

  return runtimeConfig;
};

const runRefreshFlow = async (): Promise<void> => {
  const config = ensureConfigured();
  const tokens = await getAuthTokens(config.tokenStorage);

  if (!tokens.refresh_token) {
    await clearAuthTokens(config.tokenStorage);
    config.onAuthFailure?.();
    throw new Error('No refresh token available');
  }

  const payload: RefreshRequest = {
    refresh_token: tokens.refresh_token,
    session_id: tokens.session_id ?? undefined,
  };

  const response = await axios.post<RefreshResponse>(`${config.baseUrl}/api/v1/auth/refresh`, payload);
  await setAuthTokens(config.tokenStorage, response.data);
};

const setupInterceptors = (client: AxiosInstance): void => {
  client.interceptors.request.use(async (request) => {
    const config = ensureConfigured();
    const { access_token: accessToken } = await getAuthTokens(config.tokenStorage);

    if (accessToken) {
      request.headers.Authorization = `Bearer ${accessToken}`;
    }

    return request;
  });

  client.interceptors.response.use(
    (response) => response,
    async (error: AxiosError<ApiError>) => {
      const config = ensureConfigured();
      const originalRequest = error.config as RetriableRequestConfig | undefined;
      const requestUrl = originalRequest?.url ?? '';

      if (!originalRequest || originalRequest._retry || error.response?.status !== 401 || requestUrl.includes('/api/v1/auth/refresh')) {
        throw error;
      }

      originalRequest._retry = true;

      try {
        if (!refreshPromise) {
          refreshPromise = runRefreshFlow().finally(() => {
            refreshPromise = null;
          });
        }

        await refreshPromise;

        const { access_token: accessToken } = await getAuthTokens(config.tokenStorage);
        if (accessToken) {
          originalRequest.headers.Authorization = `Bearer ${accessToken}`;
        }

        return client.request(originalRequest);
      } catch (refreshError) {
        await clearAuthTokens(config.tokenStorage);
        config.onAuthFailure?.();
        throw refreshError;
      }
    },
  );
};

export const configureApiClient = (config: ApiRuntimeConfig): AxiosInstance => {
  runtimeConfig = config;
  apiClient = axios.create({
    baseURL: config.baseUrl,
  });

  setupInterceptors(apiClient);
  return apiClient;
};

export const getApiClient = (): AxiosInstance => {
  if (!apiClient) {
    throw new Error('API client has not been configured. Wrap your app with SilverstripeApiProvider.');
  }

  return apiClient;
};
