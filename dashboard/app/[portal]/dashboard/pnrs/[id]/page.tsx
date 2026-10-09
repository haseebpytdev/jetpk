import { PnrDetailPageContent } from "@/features/pnrs/pnr-detail-page-content";

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return {
    title: `PNR ${id} — JetPakistan Dashboard`,
  };
}

export default async function PnrDetailPage({
  params,
}: {
  params: Promise<{ id: string; portal: string }>;
}) {
  const { id } = await params;
  return <PnrDetailPageContent pnrId={decodeURIComponent(id)} />;
}
