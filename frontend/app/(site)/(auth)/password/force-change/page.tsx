import { AuthShell, ForcePasswordChangeForm } from "@/features/auth";
import { requireForcePasswordPageAccess } from "@/features/auth/server/force-password-access";

export default async function ForcePasswordChangePage() {
  await requireForcePasswordPageAccess();

  return (
    <AuthShell
      title="Change your password"
      description="For security, set a new password before continuing to your account."
      headline="Account security"
      headlineHighlight="update"
      panelDescription="Choose a strong password to protect your JetPakistan account."
    >
      <ForcePasswordChangeForm />
    </AuthShell>
  );
}
