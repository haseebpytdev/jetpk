import { SupportOperationalWorkspace } from "@/features/support/support-operational-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { mockSupportTickets } from "@/mocks/support-fixtures";

export const metadata = {
  title: "Support — JetPakistan Dashboard",
};

export default function SupportPage() {
  return (
    <div className="space-y-6">
      <PageHeader title="Support tickets" description="Assign, reply, and resolve support cases." />
      <SupportOperationalWorkspace tickets={mockSupportTickets} />
    </div>
  );
}
