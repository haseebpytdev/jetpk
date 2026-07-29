import { PaymentStatusPage } from "@/features/standard-booking/components/CardPaymentPage";

type PageProps = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export default async function Page({ searchParams }: PageProps) {
  const raw = await searchParams;
  const reference = Array.isArray(raw.reference) ? raw.reference[0] : raw.reference;

  return <PaymentStatusPage reference={reference} />;
}
