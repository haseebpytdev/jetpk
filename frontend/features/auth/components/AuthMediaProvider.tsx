"use client";

import { createContext, useContext, type ReactNode } from "react";
import { AUTH_ILLUSTRATION_FALLBACK } from "../constants/auth-media";
import type { PageMediaImage } from "../services/auth-page-media";

const AuthMediaContext = createContext<PageMediaImage>({
  url: AUTH_ILLUSTRATION_FALLBACK,
  alt: "",
});

export function AuthMediaProvider({
  illustration,
  children,
}: {
  illustration: PageMediaImage;
  children: ReactNode;
}) {
  return <AuthMediaContext.Provider value={illustration}>{children}</AuthMediaContext.Provider>;
}

export function useAuthIllustration(): PageMediaImage {
  return useContext(AuthMediaContext);
}
