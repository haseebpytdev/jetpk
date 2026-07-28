import { AuthShell } from "@/features/auth";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { PublicShell } from "@/components/layout/PublicShell";
import { cookies } from "next/headers";

export default async function AgentRegisterSubmittedPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <AuthShell title="Application received" description="Your agent application is pending review.">
        <p className="text-jp-sm text-jp-muted">
          Thank you for applying. Our team will review your submission and contact you by email. You can sign in once your
          application is approved and your account is activated.
        </p>
        <p className="mt-4 text-jp-sm">
          <a href="/login" className="font-semibold text-jp-primary hover:underline">
            Return to sign in
          </a>
        </p>
      </AuthShell>
    </PublicShell>
  );
}
