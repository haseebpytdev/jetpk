import { AuthShell, AgentRegistrationForm } from "@/features/auth";

export default async function AgentRegisterPage() {
  return (
    <AuthShell
      eyebrow="Partner with us"
      headline="Apply as a"
      headlineHighlight="JetPakistan agent"
      panelDescription="Submit your agency application for review. Portal access is granted after approval."
      title="Agent application"
      description="Approved agents receive portal access after manual review."
    >
      <AgentRegistrationForm />
    </AuthShell>
  );
}
