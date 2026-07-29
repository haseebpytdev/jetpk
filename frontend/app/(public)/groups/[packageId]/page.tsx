import { GroupPackageDetailsPage } from "@/features/group-ticketing";

type PageProps = {
  params: Promise<{ packageId: string }>;
};

export default async function Page({ params }: PageProps) {
  const { packageId } = await params;
  return <GroupPackageDetailsPage packageId={packageId} />;
}
