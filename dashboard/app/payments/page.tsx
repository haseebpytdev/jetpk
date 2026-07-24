import { PaymentsPageContent } from "@/features/payments/payments-page-content";

export const metadata = {
  title: "Payments — JetPakistan Admin Preview",
};

export default function PaymentsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <PaymentsPageContent searchParams={searchParams} />;
}
