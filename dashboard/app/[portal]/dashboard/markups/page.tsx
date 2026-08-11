import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Markups — JetPakistan Dashboard" };

export default function MarkupsPage() {
  return (
    <PageContainer>
      <PageHeader
        title="Markups"
        description="Operator markup rules workspace. Domain mutations remain on Laravel intake APIs."
      />
      <EmptyState
        title="Markup rules"
        description="Live markup inventory is served through the Next shell. Commercial rule mutation stays backend-authorized."
      />
    </PageContainer>
  );
}
