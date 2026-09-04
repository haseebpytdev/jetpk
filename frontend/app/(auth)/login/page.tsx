import { LoginPageClient } from "@/features/auth/components/LoginPageClient";

/**
 * Login route is a thin server wrapper around a client page so soft-nav does
 * not await searchParams / commerce gates on the RSC critical path.
 */
export default function LoginPage() {
  return <LoginPageClient />;
}
