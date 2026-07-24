import { cn } from "@/lib/utils";
import type {
  BookingStatus,
  PaymentStatus,
  TicketingStatus,
} from "@/types/booking";
import type {
  LedgerPaymentStatus,
  ReconciliationState,
  TransactionStatus,
  TransactionType,
} from "@/types/payment";

const bookingStatusStyles: Record<BookingStatus, string> = {
  confirmed: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  failed: "bg-red-50 text-red-800 ring-red-600/20",
  cancelled: "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const paymentStatusStyles: Record<PaymentStatus, string> = {
  paid: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  unpaid: "bg-red-50 text-red-800 ring-red-600/20",
  partial: "bg-amber-50 text-amber-900 ring-amber-600/20",
  pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
};

const ticketingStatusStyles: Record<TicketingStatus, string> = {
  ticketed: "bg-blue-50 text-blue-800 ring-blue-600/20",
  unticketed: "bg-gray-100 text-gray-800 ring-gray-500/20",
  pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
};

function formatStatusLabel(value: string): string {
  return value.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function StatusPill({
  label,
  tone,
  className,
}: {
  label: string;
  tone: string;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset",
        tone,
        className,
      )}
    >
      <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70" aria-hidden />
      {label}
    </span>
  );
}

export function BookingStatusBadge({ status }: { status: BookingStatus }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={bookingStatusStyles[status]} />
  );
}

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={paymentStatusStyles[status]} />
  );
}

export function TicketingStatusBadge({ status }: { status: TicketingStatus }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={ticketingStatusStyles[status]} />
  );
}

const ledgerPaymentStatusStyles: Record<LedgerPaymentStatus, string> = {
  paid: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  unpaid: "bg-red-50 text-red-800 ring-red-600/20",
  partial: "bg-amber-50 text-amber-900 ring-amber-600/20",
  pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  failed: "bg-red-50 text-red-800 ring-red-600/20",
  reversed: "bg-orange-50 text-orange-900 ring-orange-600/20",
  refunded: "bg-blue-50 text-blue-800 ring-blue-600/20",
  partially_refunded: "bg-blue-50 text-blue-800 ring-blue-600/20",
};

const transactionStatusStyles: Record<TransactionStatus, string> = {
  succeeded: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  failed: "bg-red-50 text-red-800 ring-red-600/20",
  pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  cancelled: "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const reconciliationStatusStyles: Record<ReconciliationState, string> = {
  reconciled: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  unreconciled: "bg-amber-50 text-amber-900 ring-amber-600/20",
  disputed: "bg-red-50 text-red-800 ring-red-600/20",
  pending_review: "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const transactionTypeStyles: Record<TransactionType, string> = {
  payment: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  refund: "bg-blue-50 text-blue-800 ring-blue-600/20",
  reversal: "bg-orange-50 text-orange-900 ring-orange-600/20",
  fee: "bg-gray-100 text-gray-800 ring-gray-500/20",
  adjustment: "bg-violet-50 text-violet-800 ring-violet-600/20",
};

export function LedgerPaymentStatusBadge({ status }: { status: LedgerPaymentStatus }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={ledgerPaymentStatusStyles[status]} />
  );
}

export function TransactionStatusBadge({ status }: { status: TransactionStatus }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={transactionStatusStyles[status]} />
  );
}

export function ReconciliationStatusBadge({ status }: { status: ReconciliationState }) {
  return (
    <StatusPill label={formatStatusLabel(status)} tone={reconciliationStatusStyles[status]} />
  );
}

export function TransactionTypeBadge({ type }: { type: TransactionType }) {
  return <StatusPill label={formatStatusLabel(type)} tone={transactionTypeStyles[type]} />;
}
