export type PublicUser = {
  id: string;
  displayName: string;
  email: string;
  initials: string;
  avatarUrl?: string;
};

export type AnonymousSession = {
  status: "anonymous";
};

export type AuthenticatedSession = {
  status: "authenticated";
  user: PublicUser;
  dashboardUrl: string;
  landingRoute: string;
  accountType: string | null;
  role: string | null;
  portalType: string | null;
  agencyId: string | null;
  agencyRole: "owner" | "staff" | null;
  permissions: string[];
  accountStatus: string;
  emailVerified: boolean;
  sessionUsable: boolean;
  requiresPasswordChange: boolean;
  requiresEmailVerification: boolean;
};

export type PublicSession = AnonymousSession | AuthenticatedSession;

export type SessionPreviewMode = "logged-out" | "logged-in";

export type SessionAdapter = {
  getSession: () => Promise<PublicSession>;
};
