import Link from "next/link";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Group ticketing — JetPakistan Dashboard" };

export default function GroupTicketingPage() {
  return (
    <PageContainer>
      <PageHeader
        title="Group ticketing"
        description="Catalog sync remains Al-Haider. Manual/local QA inventory is managed on the Laravel admin inventory screen."
      />
      <EmptyState
        title="Inventory management"
        description="Use Admin inventory to sync Al-Haider packages or create MANUAL_LOCAL QA groups (hidden from public search unless allowlisted)."
      />
      <p className="mt-4">
        <Link
          href="/admin/group-ticketing/inventory"
          className="text-sm font-semibold text-jp-primary underline"
          data-testid="admin-group-inventory-laravel-link"
        >
          Open group inventory (Laravel)
        </Link>
      </p>
    </PageContainer>
  );
}
