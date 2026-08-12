export type AccountStatus = "active" | "invited" | "suspended" | "inactive";

export type PortalType = "customer" | "agent" | "admin" | "staff" | "agency_admin" | "none";

export type AgencyRole = "owner" | "staff" | null;

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

export type SessionLogoutContract = {
  method: "POST";
  path: string;
};

export type SessionBootstrap = {
  authenticated: boolean;
  user?: AuthenticatedUser;
  role?: string | null;
  portal_type?: PortalType;
  agency_id?: string | null;
  agency_role?: AgencyRole;
  permissions?: string[];
  dashboard_url?: string;
  landing_route?: string;
  requires_otp?: boolean;
  requires_password_change?: boolean;
  requires_email_verification?: boolean;
  account_status?: AccountStatus;
  email_verified?: boolean;
  session_usable?: boolean;
  session_expired?: boolean;
  csrf_ready?: boolean;
  logout?: SessionLogoutContract;
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
  | {
      ok: false;
      message: string;
      fieldErrors?: Record<string, string[]>;
      status?: number;
      code?: string;
    };

export type OtpVerifyPayload = {
  otp: string;
  client_slug?: string;
};

export type OtpVerifyResponse =
  | { ok: true; redirect: string; dashboard_url?: string }
  | {
      ok: false;
      message: string;
      fieldErrors?: Record<string, string[]>;
      status?: number;
      code?: string;
    };

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
