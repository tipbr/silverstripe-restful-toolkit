export { SilverstripeApiProvider, useApiConfig } from './src/provider';
export { createCrudHooks } from './src/hooks/crud/createCrudHooks';

export { useLogin } from './src/hooks/auth/useLogin';
export { useRegister } from './src/hooks/auth/useRegister';
export { useLogout } from './src/hooks/auth/useLogout';
export { useLogoutAll } from './src/hooks/auth/useLogoutAll';
export { useRefreshToken } from './src/hooks/auth/useRefreshToken';
export { useMe } from './src/hooks/auth/useMe';
export { useUpdateMe } from './src/hooks/auth/useUpdateMe';
export { useChangePassword } from './src/hooks/auth/useChangePassword';
export { useForgotPassword } from './src/hooks/auth/useForgotPassword';
export { useResetPassword } from './src/hooks/auth/useResetPassword';
export { useSessions } from './src/hooks/auth/useSessions';
export { useDeleteSession } from './src/hooks/auth/useDeleteSession';
export { useIsAuthenticated } from './src/hooks/auth/useIsAuthenticated';

export type { TokenStorage } from './src/storage';
export type { IdMappingConfig } from './src/idMapping';
export * from './src/types';
