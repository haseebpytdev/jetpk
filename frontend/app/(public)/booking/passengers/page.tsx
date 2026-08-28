import { PassengerDetailsPage } from "@/features/standard-booking/components/PassengerDetailsPage";

type PageProps = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export default async function Page({ searchParams }: PageProps) {
  const raw = await searchParams;
  const normalized: Record<string, string | undefined> = {};
  Object.entries(raw).forEach(([key, value]) => {
    normalized[key] = Array.isArray(value) ? value[0] : value;
  });

  return <PassengerDetailsPage searchParams={normalized} />;
}
