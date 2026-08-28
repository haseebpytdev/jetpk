import { GroupPackageDetailsPage } from "@/features/group-ticketing";
import { fetchGroupPackageServer } from "@/features/group-ticketing/services/group-ticketing-api";

type PageProps = {
  params: Promise<{ packageId: string }>;
};

export default async function Page({ params }: PageProps) {
  const { packageId } = await params;
  const initialPayload = await fetchGroupPackageServer(packageId);
  return <GroupPackageDetailsPage packageId={packageId} initialPayload={initialPayload} />;
}
