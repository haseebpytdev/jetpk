import { PnrsPageContent } from "@/features/pnrs/pnrs-page-content";

export const metadata = {
  title: "PNRs & Orders — JetPakistan Dashboard",
};

export default function PnrsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <PnrsPageContent searchParams={searchParams} />;
}
