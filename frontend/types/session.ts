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
};

export type PublicSession = AnonymousSession | AuthenticatedSession;

export type SessionPreviewMode = "logged-out" | "logged-in";

export type SessionAdapter = {
  getSession: () => Promise<PublicSession>;
};
