export type ProfileAccountMeta = {
  account_type: string;
  status: string;
  is_customer: boolean;
  is_platform_admin: boolean;
  is_staff: boolean;
  is_agent_portal: boolean;
};

export type ProfileUserFields = {
  name: string;
  email: string;
  username: string;
  email_verified?: boolean;
  email_verified_at?: string | null;
};

export type ProfileContactFields = {
  phone: string | null;
  whatsapp: string | null;
  country_code: string | null;
  city: string | null;
  date_of_birth?: string | null;
  gender?: string | null;
  nationality?: string | null;
  passport_number?: string | null;
  passport_issuing_country?: string | null;
  passport_expiry_date?: string | null;
  national_id?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  profile_photo_url?: string | null;
};

export type ProfileJsonPayload = {
  ok?: boolean;
  message?: string;
  user: ProfileUserFields;
  profile: ProfileContactFields;
  account?: ProfileAccountMeta;
  countries?: Array<{ code: string; name: string } | Record<string, string>>;
  update_url?: string;
  password_update_url?: string;
  supported_fields?: string[];
};

export type ProfileUpdateResponse = {
  ok?: boolean;
  message?: string;
  profile?: ProfileJsonPayload;
};
