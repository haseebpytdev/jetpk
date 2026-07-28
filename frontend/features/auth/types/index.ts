export type AccountStatus = "active" | "invited" | "suspended" | "inactive";

export type AuthenticatedUser = {
  id: string;
  name: string;
  email: string;
  account_type: string | null;
};

export type OtpChallenge = {
  masked_email: string | null;
  resend_available_in: number;
};

export type SessionBootstrap = {
  authenticated: boolean;
  user?: AuthenticatedUser;
  role?: string | null;
  permissions?: string[];
  dashboard_url?: string;
  requires_otp?: boolean;
  requires_password_change?: boolean;
  requires_email_verification?: boolean;
  account_status?: AccountStatus;
  otp_challenge?: OtpChallenge;
};

export type LoginPayload = {
  login: string;
  password: string;
  remember?: boolean;
  client_slug?: string;
};

export type LoginResponse =
  | { ok: true; redirect: string; requires_otp?: boolean }
  | { ok: false; message: string; fieldErrors?: Record<string, string[]>; status?: number };

export type OtpVerifyPayload = {
  otp: string;
  client_slug?: string;
};

export type OtpVerifyResponse =
  | { ok: true; redirect: string; dashboard_url?: string }
  | { ok: false; message: string; fieldErrors?: Record<string, string[]>; status?: number };

export type CustomerRegistrationPayload = {
  first_name: string;
  last_name: string;
  email: string;
  mobile_country_code: string;
  mobile: string;
  password: string;
  password_confirmation: string;
  security_answer: string;
  terms: string;
};

export type AgentRegistrationPayload = {
  company_name: string;
  city: string;
  business_type: string;
  first_name: string;
  last_name?: string;
  email: string;
  mobile_country_code: string;
  mobile: string;
  country?: string;
  office_address?: string;
  notes?: string;
  terms: string;
};

export type PasswordResetRequestPayload = {
  email: string;
};

export type PasswordResetPayload = {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type DashboardDestination = string;
