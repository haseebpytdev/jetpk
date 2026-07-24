import { TicketsPageContent } from "@/features/tickets/tickets-page-content";

export const metadata = {
  title: "Tickets & Documents — JetPakistan Admin Preview",
};

export default function TicketsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <TicketsPageContent searchParams={searchParams} />;
}
