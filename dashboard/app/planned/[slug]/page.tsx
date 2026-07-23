import Link from "next/link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";

const labels: Record<string, { title: string; laravelRoute: string }> = {
  bookings: { title: "Bookings", laravelRoute: "admin.bookings" },
  customers: { title: "Customers", laravelRoute: "admin.customers.index" },
  agents: { title: "Agents", laravelRoute: "admin.agents" },
  users: { title: "Users & staff", laravelRoute: "admin.users.index" },
  flights: { title: "Flight search", laravelRoute: "flights.search" },
  suppliers: { title: "Suppliers", laravelRoute: "admin.api-settings" },
  markups: { title: "Markups", laravelRoute: "admin.markups" },
  "page-settings": { title: "Page settings", laravelRoute: "admin.page-settings.index" },
  reports: { title: "Reports", laravelRoute: "admin.reports" },
  communications: { title: "Communications", laravelRoute: "admin.settings.communications.index" },
  diagnostics: { title: "Diagnostics", laravelRoute: "admin.system-health" },
  settings: { title: "Settings", laravelRoute: "admin.settings.index" },
  support: { title: "Support", laravelRoute: "admin.support.tickets.index" },
  "group-ticketing": { title: "Group ticketing", laravelRoute: "admin.group-ticketing.index" },
  accounting: { title: "Accounting", laravelRoute: "admin.ledger.index" },
};

export default async function PlannedPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const meta = labels[slug] ?? { title: slug, laravelRoute: "admin.dashboard" };

  return (
    <Card className="max-w-xl">
      <CardTitle>{meta.title}</CardTitle>
      <CardDescription className="mt-2">
        Planned module — not implemented in JETPK-DASH-01. Legacy Laravel route:{" "}
        <code className="rounded bg-gray-100 px-1">{meta.laravelRoute}</code>
      </CardDescription>
      <Link
        href="/"
        className="mt-6 inline-flex min-h-11 items-center rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
      >
        Back to overview
      </Link>
    </Card>
  );
}
