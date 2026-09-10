import { LoginPageClient } from "@/features/auth/components/LoginPageClient";

/** Cacheable auth shell — no cookies()/session on RSC path (client redirect only). */
export const revalidate = 300;
export const dynamic = "force-static";

/**
 * Login route is a thin server wrapper around a client page so soft-nav does
 * not await searchParams / commerce gates on the RSC critical path.
 */
export default function LoginPage() {
  return <LoginPageClient />;
}
