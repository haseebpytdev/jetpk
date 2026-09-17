import { PassengerDetailsPage } from "@/features/standard-booking";
import { PASSENGERS_EARLY_FETCH_INLINE } from "@/features/standard-booking/utils/passengers-early-fetch-inline";

type PageProps = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export default async function Page({ searchParams }: PageProps) {
  const raw = await searchParams;
  const normalized: Record<string, string | undefined> = {};
  Object.entries(raw).forEach(([key, value]) => {
    normalized[key] = Array.isArray(value) ? value[0] : value;
  });

  return (
    <>
      <script
        id="jp-passengers-early-fetch"
        dangerouslySetInnerHTML={{ __html: PASSENGERS_EARLY_FETCH_INLINE }}
      />
      <PassengerDetailsPage searchParams={normalized} />
    </>
  );
}
