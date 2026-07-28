import { AgentsPageContent } from "@/features/agents/agents-page-content";

export const metadata = {
  title: "Agents — JetPakistan Admin Preview",
};

export default function AgentsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <AgentsPageContent searchParams={searchParams} />;
}
