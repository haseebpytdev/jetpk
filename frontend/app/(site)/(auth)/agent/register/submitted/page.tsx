import Link from "next/link";
import { AuthShell } from "@/features/auth";

export default async function AgentRegisterSubmittedPage() {
  return (
    <AuthShell title="Application received" description="Your agent application is pending review.">
      <p className="text-jp-sm text-jp-muted">
        Thank you for applying. Our team will review your submission and contact you by email. You can sign in once your
        application is approved and your account is activated.
      </p>
      <p className="mt-4 text-jp-sm">
        <Link href="/login" className="font-semibold text-jp-brand hover:underline">
          Return to sign in
        </Link>
      </p>
    </AuthShell>
  );
}
