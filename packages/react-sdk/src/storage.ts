import type { AuthTokens } from './types';

const ACCESS_TOKEN_KEY = 'access_token';
const REFRESH_TOKEN_KEY = 'refresh_token';
const SESSION_ID_KEY = 'session_id';

export interface TokenStorage {
  getItem(key: string): Promise<string | null>;
  setItem(key: string, value: string): Promise<void>;
  removeItem(key: string): Promise<void>;
}

class InMemoryStorage implements TokenStorage {
  private readonly store = new Map<string, string>();

  async getItem(key: string): Promise<string | null> {
    return this.store.get(key) ?? null;
  }

  async setItem(key: string, value: string): Promise<void> {
    this.store.set(key, value);
  }

  async removeItem(key: string): Promise<void> {
    this.store.delete(key);
  }
}

class BrowserLocalStorageTokenStorage implements TokenStorage {
  constructor(private readonly namespace: string) {}

  private getKey(key: string): string {
    return `${this.namespace}:${key}`;
  }

  async getItem(key: string): Promise<string | null> {
    return globalThis.localStorage.getItem(this.getKey(key));
  }

  async setItem(key: string, value: string): Promise<void> {
    globalThis.localStorage.setItem(this.getKey(key), value);
  }

  async removeItem(key: string): Promise<void> {
    globalThis.localStorage.removeItem(this.getKey(key));
  }
}

export const createDefaultTokenStorage = (): TokenStorage => {
  if (typeof globalThis.localStorage !== 'undefined') {
    return new BrowserLocalStorageTokenStorage('silverstripe-api');
  }

  return new InMemoryStorage();
};

export const setAuthTokens = async (storage: TokenStorage, tokens: Partial<AuthTokens>): Promise<void> => {
  if (tokens.access_token) {
    await storage.setItem(ACCESS_TOKEN_KEY, tokens.access_token);
  }

  if (tokens.refresh_token) {
    await storage.setItem(REFRESH_TOKEN_KEY, tokens.refresh_token);
  }

  if (typeof tokens.session_id === 'number') {
    await storage.setItem(SESSION_ID_KEY, tokens.session_id.toString());
  }
};

export const getAuthTokens = async (
  storage: TokenStorage,
): Promise<{ access_token: string | null; refresh_token: string | null; session_id: number | null }> => {
  const [accessToken, refreshToken, sessionIdRaw] = await Promise.all([
    storage.getItem(ACCESS_TOKEN_KEY),
    storage.getItem(REFRESH_TOKEN_KEY),
    storage.getItem(SESSION_ID_KEY),
  ]);

  return {
    access_token: accessToken,
    refresh_token: refreshToken,
    session_id: sessionIdRaw ? Number(sessionIdRaw) : null,
  };
};

export const clearAuthTokens = async (storage: TokenStorage): Promise<void> => {
  await Promise.all([
    storage.removeItem(ACCESS_TOKEN_KEY),
    storage.removeItem(REFRESH_TOKEN_KEY),
    storage.removeItem(SESSION_ID_KEY),
  ]);
};
