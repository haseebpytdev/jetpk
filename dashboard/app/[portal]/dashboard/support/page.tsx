import { SupportOperationalWorkspace } from "@/features/support/support-operational-workspace";
import { PageHeader } from "@/components/ui/page-layout";

export const metadata = {
  title: "Support — JetPakistan Dashboard",
};

export default function SupportPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        title="Support tickets"
        description="Live Laravel support queue. Assign, reply, and resolve real tickets."
      />
      <SupportOperationalWorkspace />
    </div>
  );
}
