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

const accountStatusStyles: Record<string, string> = {
  Active: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Inactive: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Suspended: "bg-red-50 text-red-800 ring-red-600/20",
  "Review Required": "bg-amber-50 text-amber-900 ring-amber-600/20",
};

const verificationStatusStyles: Record<string, string> = {
  Verified: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  Incomplete: "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Not Required": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const operationalStatusStyles: Record<string, string> = {
  Active: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Inactive: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Maintenance: "bg-blue-50 text-blue-800 ring-blue-600/20",
  Restricted: "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Review Required": "bg-amber-50 text-amber-900 ring-amber-600/20",
};

const integrationStatusStyles: Record<string, string> = {
  Connected: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Mock Only": "bg-violet-50 text-violet-800 ring-violet-600/20",
  Manual: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Degraded: "bg-amber-50 text-amber-900 ring-amber-600/20",
  Disabled: "bg-red-50 text-red-800 ring-red-600/20",
};

const credentialStatusStyles: Record<string, string> = {
  Configured: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Missing: "bg-red-50 text-red-800 ring-red-600/20",
  "Expiring Soon": "bg-amber-50 text-amber-900 ring-amber-600/20",
  Invalid: "bg-red-50 text-red-800 ring-red-600/20",
  "Not Required": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const commercialStatusStyles: Record<string, string> = {
  Standard: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Preferred: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Credit Enabled": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Prepaid Only": "bg-violet-50 text-violet-800 ring-violet-600/20",
  "On Hold": "bg-amber-50 text-amber-900 ring-amber-600/20",
};

const settlementStatusStyles: Record<string, string> = {
  Current: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Due: "bg-amber-50 text-amber-900 ring-amber-600/20",
  Overdue: "bg-red-50 text-red-800 ring-red-600/20",
  "Reconciliation Required": "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

export function AccountStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={accountStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function VerificationStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={verificationStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function OperationalStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={operationalStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function IntegrationStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={integrationStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function CredentialStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={credentialStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function CommercialStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={commercialStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function SettlementStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={settlementStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

const documentTypeStyles: Record<string, string> = {
  "E-Ticket": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "NDC Fulfilment Document": "bg-violet-50 text-violet-800 ring-violet-600/20",
  EMD: "bg-cyan-50 text-cyan-900 ring-cyan-600/20",
  "Manual Ticket Record": "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Refund Document": "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Void Record": "bg-red-50 text-red-800 ring-red-600/20",
};

const issueStatusStyles: Record<string, string> = {
  Pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  Issued: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Partially Issued": "bg-blue-50 text-blue-800 ring-blue-600/20",
  Blocked: "bg-red-50 text-red-800 ring-red-600/20",
  Failed: "bg-red-50 text-red-800 ring-red-600/20",
  Voided: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Refunded: "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const refundEligibilityStyles: Record<string, string> = {
  Eligible: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Not Eligible": "bg-red-50 text-red-800 ring-red-600/20",
  "Airline Review Required": "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Fare Rules Required": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Already Refunded": "bg-orange-50 text-orange-900 ring-orange-600/20",
  Unknown: "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const exchangeEligibilityStyles: Record<string, string> = {
  Eligible: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Not Eligible": "bg-red-50 text-red-800 ring-red-600/20",
  "Fare Rules Required": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Airline Review Required": "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Already Exchanged": "bg-orange-50 text-orange-900 ring-orange-600/20",
  Unknown: "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const voidStatusStyles: Record<string, string> = {
  "Within Window": "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Window Expired": "bg-amber-50 text-amber-900 ring-amber-600/20",
  Voided: "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
  Unknown: "bg-gray-100 text-gray-800 ring-gray-500/20",
};

export function DocumentTypeBadge({ type }: { type: string }) {
  return (
    <StatusPill
      label={type}
      tone={documentTypeStyles[type] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function IssueStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={issueStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function RefundEligibilityBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={refundEligibilityStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function ExchangeEligibilityBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={exchangeEligibilityStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function VoidStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={voidStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

const referenceTypeStyles: Record<string, string> = {
  "GDS PNR": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "NDC Order": "bg-violet-50 text-violet-800 ring-violet-600/20",
  "One API Order": "bg-cyan-50 text-cyan-900 ring-cyan-600/20",
  "Manual Reference": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const channelStyles: Record<string, string> = {
  "Sabre GDS": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Sabre NDC": "bg-violet-50 text-violet-800 ring-violet-600/20",
  "One API": "bg-cyan-50 text-cyan-900 ring-cyan-600/20",
  Manual: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Mock: "bg-amber-50 text-amber-900 ring-amber-600/20",
};

const lifecycleStatusStyles: Record<string, string> = {
  Active: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Confirmed: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "On Hold": "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Pending Supplier": "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Partially Confirmed": "bg-blue-50 text-blue-800 ring-blue-600/20",
  Cancelled: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Expired: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Failed: "bg-red-50 text-red-800 ring-red-600/20",
  "Review Required": "bg-orange-50 text-orange-900 ring-orange-600/20",
};

const fulfilmentStatusStyles: Record<string, string> = {
  "Not Required": "bg-gray-100 text-gray-800 ring-gray-500/20",
  Pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Partially Fulfilled": "bg-blue-50 text-blue-800 ring-blue-600/20",
  Fulfilled: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Failed: "bg-red-50 text-red-800 ring-red-600/20",
  Refunded: "bg-blue-50 text-blue-800 ring-blue-600/20",
};

const pnrTicketingStatusStyles: Record<string, string> = {
  "Not Ticketed": "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Ready for Ticketing": "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Ticketing Blocked": "bg-orange-50 text-orange-900 ring-orange-600/20",
  "Partially Ticketed": "bg-amber-50 text-amber-900 ring-amber-600/20",
  Ticketed: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Failed: "bg-red-50 text-red-800 ring-red-600/20",
  Voided: "bg-gray-100 text-gray-800 ring-gray-500/20",
  Refunded: "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

const cancellationEligibilityStyles: Record<string, string> = {
  Eligible: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  "Not Eligible": "bg-red-50 text-red-800 ring-red-600/20",
  "Supplier Review Required": "bg-amber-50 text-amber-900 ring-amber-600/20",
  "Already Cancelled": "bg-gray-100 text-gray-800 ring-gray-500/20",
  Unknown: "bg-gray-100 text-gray-800 ring-gray-500/20",
  "Not Applicable": "bg-gray-100 text-gray-800 ring-gray-500/20",
};

export function ReferenceTypeBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={referenceTypeStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function ChannelBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={channelStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function LifecycleStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={lifecycleStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function FulfilmentStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={fulfilmentStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function PnrTicketingStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={pnrTicketingStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function CancellationEligibilityBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={status}
      tone={cancellationEligibilityStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

const reportTrendStyles: Record<string, string> = {
  positive: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  negative: "bg-red-50 text-red-800 ring-red-600/20",
  neutral: "bg-gray-100 text-gray-800 ring-gray-500/20",
  warning: "bg-amber-50 text-amber-900 ring-amber-600/20",
  unavailable: "bg-violet-50 text-violet-800 ring-violet-600/20",
};

const cmsStatusStyles: Record<string, string> = {
  draft: "bg-gray-100 text-gray-800 ring-gray-500/20",
  inReview: "bg-blue-50 text-blue-800 ring-blue-600/20",
  approved: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  scheduled: "bg-cyan-50 text-cyan-900 ring-cyan-600/20",
  published: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  expired: "bg-orange-50 text-orange-900 ring-orange-600/20",
  archived: "bg-gray-100 text-gray-800 ring-gray-500/20",
  valid: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  warning: "bg-amber-50 text-amber-900 ring-amber-600/20",
  blocked: "bg-red-50 text-red-800 ring-red-600/20",
  unapproved: "bg-red-50 text-red-800 ring-red-600/20",
};

export function ReportTrendBadge({ trend }: { trend: string }) {
  return (
    <StatusPill
      label={formatStatusLabel(trend)}
      tone={reportTrendStyles[trend] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function CmsStatusBadge({ status, label }: { status: string; label?: string }) {
  return (
    <StatusPill
      label={label ?? formatStatusLabel(status)}
      tone={cmsStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

const userStatusStyles: Record<string, string> = {
  active: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  invited: "bg-blue-50 text-blue-800 ring-blue-600/20",
  pendingVerification: "bg-amber-50 text-amber-900 ring-amber-600/20",
  suspended: "bg-red-50 text-red-800 ring-red-600/20",
  locked: "bg-orange-50 text-orange-900 ring-orange-600/20",
  disabled: "bg-gray-100 text-gray-800 ring-gray-500/20",
  archived: "bg-gray-100 text-gray-600 ring-gray-500/20",
};

const mfaStatusStyles: Record<string, string> = {
  enabled: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  disabled: "bg-gray-100 text-gray-800 ring-gray-500/20",
  required: "bg-amber-50 text-amber-900 ring-amber-600/20",
  pendingSetup: "bg-blue-50 text-blue-800 ring-blue-600/20",
};

const validationStateStyles: Record<string, string> = {
  valid: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  warning: "bg-amber-50 text-amber-900 ring-amber-600/20",
  blocked: "bg-red-50 text-red-800 ring-red-600/20",
  review: "bg-blue-50 text-blue-800 ring-blue-600/20",
};

export function UserStatusBadge({ status, label }: { status: string; label?: string }) {
  return (
    <StatusPill
      label={label ?? formatStatusLabel(status)}
      tone={userStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function MfaStatusBadge({ status, label }: { status: string; label?: string }) {
  return (
    <StatusPill
      label={label ?? formatStatusLabel(status)}
      tone={mfaStatusStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function AccessValidationBadge({ status, label }: { status: string; label?: string }) {
  return (
    <StatusPill
      label={label ?? formatStatusLabel(status)}
      tone={validationStateStyles[status] ?? "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}

export function AccessRiskBadge({ highRisk, label }: { highRisk: boolean; label?: string }) {
  return (
    <StatusPill
      label={label ?? (highRisk ? "High risk" : "Standard")}
      tone={highRisk ? "bg-red-50 text-red-800 ring-red-600/20" : "bg-gray-100 text-gray-800 ring-gray-500/20"}
    />
  );
}
