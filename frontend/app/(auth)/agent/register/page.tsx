import { AuthShell, AgentRegistrationForm } from "@/features/auth";

export default async function AgentRegisterPage() {
  return (
    <AuthShell
      title="Apply as a JetPakistan Agent"
      description="Submit your agency application for review. Approved agents receive portal access after manual review."
    >
      <AgentRegistrationForm />
    </AuthShell>
  );
}
