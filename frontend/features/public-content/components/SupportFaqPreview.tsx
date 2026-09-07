import { PublicFaq, type PublicFaqItem } from "@/features/public-visual";

type SupportFaqPreviewProps = {
  items: PublicFaqItem[];
};

const SAFE_FALLBACK_FAQS: PublicFaqItem[] = [
  {
    id: "support-manage-booking",
    question: "How can I find or manage my booking?",
    answer:
      "Use Manage Booking or the booking lookup option with your booking reference and the requested verification details. If you cannot access the booking, contact JetPakistan support and include your booking reference.",
  },
  {
    id: "support-eticket",
    question: "Where can I find my e-ticket or booking confirmation?",
    answer:
      "After a booking is confirmed, use your booking area or the confirmation details sent by JetPakistan. If the document is not available yet, check the booking status or contact support before making another payment.",
  },
  {
    id: "support-baggage",
    question: "How do I check my baggage allowance?",
    answer:
      "Baggage allowance depends on the airline, route, cabin and fare you selected. Review the baggage information shown with your fare and booking rather than assuming a fixed allowance.",
  },
  {
    id: "support-change-cancel",
    question: "Can I change or cancel my flight?",
    answer:
      "Change and cancellation eligibility depends on the airline and fare rules attached to your booking. JetPakistan can help you review the applicable rules and any airline charges before you proceed.",
  },
  {
    id: "support-refund",
    question: "How does a flight refund work?",
    answer:
      "Refund eligibility and timing depend on the airline, fare conditions and payment status. Submit your request with the booking reference so the applicable rules and current booking state can be reviewed.",
  },
  {
    id: "support-payment-pending",
    question: "What should I do if payment was deducted but my booking is not confirmed?",
    answer:
      "Do not immediately pay again. Keep your booking or payment reference and contact JetPakistan support so the transaction and booking status can be checked first.",
  },
];

export function SupportFaqPreview({ items }: SupportFaqPreviewProps) {
  const resolved = items.length > 0 ? items : SAFE_FALLBACK_FAQS;

  return (
    <div className="mt-jp-lg" data-testid="support-faq-preview">
      <PublicFaq items={resolved.slice(0, 6)} allowMultiple />
    </div>
  );
}
