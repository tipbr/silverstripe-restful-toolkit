export type PrimitiveFilter = string | number;
export type Identifier = string | number;
export type AuthMode = 'jwt' | 'session';

export interface PaginatedMeta {
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
}

export interface ApiError {
  error: string;
  status?: number;
}

export interface ApiSuccess {
  success?: boolean;
  message: string;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  session_id: Identifier;
}

export interface SessionInfo {
  id: Identifier;
  device_name: string;
  ip: string;
  last_used: string;
  created: string;
}

export interface MemberProfile {
  id: Identifier;
  email: string;
  first_name: string;
  last_name: string;
}

export interface LoginRequest {
  email: string;
  password: string;
  device_name?: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  first_name: string;
  last_name: string;
  device_name?: string;
}

export interface RefreshRequest {
  refresh_token: string;
  session_id?: Identifier;
}

export interface ResetPasswordRequest {
  token: string;
  email: string;
  password: string;
}

export interface ChangePasswordRequest {
  current_password: string;
  new_password: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface CheckEmailRequest {
  email: string;
}

export interface CheckEmailResponse {
  email: string;
  format_valid: boolean;
  mx_valid: boolean;
  mx_check_available: boolean;
  mx_checked: boolean;
  valid: boolean;
}

export interface PasswordStrength {
  score: number;
  label: 'weak' | 'medium' | 'strong';
}

export interface CheckPasswordRequest {
  password: string;
}

export interface CheckPasswordResponse {
  valid: boolean;
  errors: string[];
  strength: PasswordStrength;
}

export type LoginResponse = AuthTokens;
export type RegisterResponse = AuthTokens;
export type RefreshResponse = Partial<AuthTokens> & Pick<AuthTokens, 'access_token'>;

export interface PaginatedQueryParams {
  page?: number;
  per_page?: number;
  filters?: Record<string, PrimitiveFilter>;
}
