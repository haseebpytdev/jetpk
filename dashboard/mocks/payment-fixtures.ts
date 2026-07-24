import { getBookingById, mockBookings } from "@/mocks/booking-fixtures";
import type {
  LedgerPaymentStatus,
  PaymentChannel,
  PaymentMethod,
  ReconciliationState,
  TransactionRecord,
  TransactionStatus,
  TransactionType,
} from "@/types/payment";
import type { BookingRecord } from "@/types/booking";

function feeFor(method: PaymentMethod, gross: number): number {
  if (gross <= 0) return 0;
  switch (method) {
    case "card":
      return Math.round(gross * 0.025);
    case "bank_transfer":
      return 150;
    case "wallet":
      return Math.round(gross * 0.01);
    default:
      return 0;
  }
}

function channelFromSource(source: string): PaymentChannel {
  if (source.toLowerCase().includes("agent")) return "agent";
  if (source.toLowerCase().includes("mobile")) return "mobile";
  if (source.toLowerCase().includes("api")) return "api";
  return "web";
}

function methodForBooking(booking: BookingRecord, index: number): PaymentMethod {
  const methods: PaymentMethod[] = ["card", "bank_transfer", "cash", "wallet", "office"];
  if (booking.agentOrSource.toLowerCase().includes("agent")) {
    return index % 2 === 0 ? "bank_transfer" : "cash";
  }
  return methods[index % methods.length];
}

function ledgerStatusForBooking(booking: BookingRecord): LedgerPaymentStatus {
  if (booking.paymentStatus === "paid") return "paid";
  if (booking.paymentStatus === "partial") return "partial";
  if (booking.paymentStatus === "pending") return "pending";
  return "unpaid";
}

function reconciliationFor(index: number, succeeded: boolean): ReconciliationState {
  if (!succeeded) return "pending_review";
  const states: ReconciliationState[] = ["reconciled", "reconciled", "unreconciled", "pending_review"];
  return states[index % states.length];
}

type TxDraft = {
  bookingId: string;
  transactionType: TransactionType;
  grossAmount: number;
  paymentMethod: PaymentMethod;
  transactionStatus: TransactionStatus;
  paymentStatus: LedgerPaymentStatus;
  reconciliationStatus?: ReconciliationState;
  transactionDate: string;
  gatewayReference?: string | null;
  bankReference?: string | null;
  manualReference?: string | null;
  refundedAmount?: number;
  auditNote: string;
};

function buildTransaction(
  txId: string,
  payId: string,
  draft: TxDraft,
  booking: BookingRecord,
): TransactionRecord {
  const fee = draft.transactionType === "payment" ? feeFor(draft.paymentMethod, draft.grossAmount) : 0;
  const net =
    draft.transactionType === "refund"
      ? -draft.grossAmount
      : draft.transactionType === "payment"
        ? draft.grossAmount - fee
        : draft.grossAmount;

  const outstanding = Math.max(0, booking.totalAmount - booking.amountPaid);

  return {
    transactionId: txId,
    paymentId: payId,
    bookingId: booking.id,
    pnr: booking.pnr,
    supplierReference: booking.supplierReference,
    customerName: booking.customerName,
    customerEmail: booking.customerEmail,
    customerPhone: booking.customerPhone,
    transactionDate: draft.transactionDate,
    bookingDate: booking.bookingDate,
    currency: booking.currency,
    grossAmount: draft.grossAmount,
    paidAmount: booking.amountPaid,
    outstandingAmount: draft.transactionType === "payment" ? outstanding : 0,
    refundedAmount: draft.refundedAmount ?? 0,
    feeAmount: fee,
    netAmount: net,
    paymentMethod: draft.paymentMethod,
    paymentChannel: channelFromSource(booking.agentOrSource),
    transactionType: draft.transactionType,
    paymentStatus: draft.paymentStatus,
    transactionStatus: draft.transactionStatus,
    reconciliationStatus:
      draft.reconciliationStatus ??
      reconciliationFor(parseInt(txId.slice(-2), 10), draft.transactionStatus === "succeeded"),
    gatewayReference: draft.gatewayReference ?? null,
    bankReference: draft.bankReference ?? null,
    manualReference: draft.manualReference ?? null,
    sourceOrAgent: booking.agentOrSource,
    createdAt: `${draft.transactionDate}T10:00:00Z`,
    updatedAt: booking.lastUpdated,
    auditNote: draft.auditNote,
  };
}

function primaryDraftForBooking(booking: BookingRecord, index: number): TxDraft {
  const method = methodForBooking(booking, index);
  const baseDate = booking.bookingDate;

  if (booking.paymentStatus === "paid") {
    return {
      bookingId: booking.id,
      transactionType: "payment",
      grossAmount: booking.amountPaid,
      paymentMethod: method,
      transactionStatus: "succeeded",
      paymentStatus: "paid",
      transactionDate: baseDate,
      gatewayReference: method === "card" ? `GW-JP-${10000 + index}` : null,
      bankReference: method === "bank_transfer" ? `NBP-TRF-${20000 + index}` : null,
      manualReference: method === "cash" || method === "office" ? `RCPT-${30000 + index}` : null,
      auditNote: "Successful payment captured against booking total.",
    };
  }

  if (booking.paymentStatus === "partial") {
    return {
      bookingId: booking.id,
      transactionType: "payment",
      grossAmount: booking.amountPaid,
      paymentMethod: method,
      transactionStatus: "succeeded",
      paymentStatus: "partial",
      transactionDate: baseDate,
      gatewayReference: method === "card" ? `GW-JP-${10000 + index}` : null,
      bankReference: method === "bank_transfer" ? `NBP-TRF-${20000 + index}` : null,
      auditNote: "Partial payment received; balance remains outstanding on booking.",
    };
  }

  if (booking.paymentStatus === "pending") {
    return {
      bookingId: booking.id,
      transactionType: "payment",
      grossAmount: booking.totalAmount,
      paymentMethod: method,
      transactionStatus: "pending",
      paymentStatus: "pending",
      transactionDate: baseDate,
      gatewayReference: method === "card" ? `GW-JP-PND-${10000 + index}` : null,
      auditNote: "Payment authorization pending — awaiting customer or gateway confirmation.",
    };
  }

  return {
    bookingId: booking.id,
    transactionType: "payment",
    grossAmount: booking.totalAmount,
    paymentMethod: method,
    transactionStatus: "failed",
    paymentStatus: "failed",
    transactionDate: baseDate,
    gatewayReference: method === "card" ? `GW-JP-FAIL-${10000 + index}` : null,
    auditNote: "Payment attempt failed — insufficient funds or gateway decline (preview).",
  };
}

const EXTRA_DRAFTS: Array<{ txId: string; payId: string; bookingId: string; draft: Omit<TxDraft, "bookingId"> }> = [
  {
    txId: "JP-TX-20026",
    payId: "JP-PAY-30005",
    bookingId: "JP-BK-10005",
    draft: {
      transactionType: "refund",
      grossAmount: 78500,
      paymentMethod: "card",
      transactionStatus: "succeeded",
      paymentStatus: "refunded",
      reconciliationStatus: "reconciled",
      transactionDate: "2026-01-16",
      gatewayReference: "GW-REF-50005",
      refundedAmount: 78500,
      auditNote: "Full refund issued after booking cancellation.",
    },
  },
  {
    txId: "JP-TX-20027",
    payId: "JP-PAY-30004",
    bookingId: "JP-BK-10004",
    draft: {
      transactionType: "payment",
      grossAmount: 460000,
      paymentMethod: "bank_transfer",
      transactionStatus: "succeeded",
      paymentStatus: "partial",
      reconciliationStatus: "unreconciled",
      transactionDate: "2026-01-13",
      bankReference: "HBL-DEP-44012",
      auditNote: "Second instalment bank transfer pending reconciliation.",
    },
  },
  {
    txId: "JP-TX-20028",
    payId: "JP-PAY-30003",
    bookingId: "JP-BK-10003",
    draft: {
      transactionType: "payment",
      grossAmount: 398000,
      paymentMethod: "card",
      transactionStatus: "failed",
      paymentStatus: "failed",
      reconciliationStatus: "pending_review",
      transactionDate: "2026-01-10",
      gatewayReference: "GW-JP-FAIL-10003",
      auditNote: "Card declined on initial booking attempt.",
    },
  },
  {
    txId: "JP-TX-20029",
    payId: "JP-PAY-30007",
    bookingId: "JP-BK-10007",
    draft: {
      transactionType: "payment",
      grossAmount: 168000,
      paymentMethod: "card",
      transactionStatus: "failed",
      paymentStatus: "failed",
      reconciliationStatus: "unreconciled",
      transactionDate: "2026-01-18",
      gatewayReference: "GW-JP-FAIL-10007",
      auditNote: "Failed card payment before booking moved to unpaid state.",
    },
  },
  {
    txId: "JP-TX-20030",
    payId: "JP-PAY-30001",
    bookingId: "JP-BK-10001",
    draft: {
      transactionType: "fee",
      grossAmount: 7125,
      paymentMethod: "card",
      transactionStatus: "succeeded",
      paymentStatus: "paid",
      reconciliationStatus: "reconciled",
      transactionDate: "2026-01-06",
      gatewayReference: "GW-FEE-10001",
      auditNote: "Gateway processing fee recorded for card payment.",
    },
  },
  {
    txId: "JP-TX-20031",
    payId: "JP-PAY-30012",
    bookingId: "JP-BK-10012",
    draft: {
      transactionType: "refund",
      grossAmount: 45000,
      paymentMethod: "card",
      transactionStatus: "succeeded",
      paymentStatus: "partially_refunded",
      reconciliationStatus: "reconciled",
      transactionDate: "2026-02-05",
      gatewayReference: "GW-REF-50012",
      refundedAmount: 45000,
      auditNote: "Partial refund after schedule change (preview).",
    },
  },
  {
    txId: "JP-TX-20032",
    payId: "JP-PAY-30015",
    bookingId: "JP-BK-10015",
    draft: {
      transactionType: "reversal",
      grossAmount: 125000,
      paymentMethod: "card",
      transactionStatus: "succeeded",
      paymentStatus: "reversed",
      reconciliationStatus: "disputed",
      transactionDate: "2026-02-10",
      gatewayReference: "GW-REV-50015",
      auditNote: "Payment reversed due to duplicate charge detection.",
    },
  },
  {
    txId: "JP-TX-20033",
    payId: "JP-PAY-30018",
    bookingId: "JP-BK-10018",
    draft: {
      transactionType: "payment",
      grossAmount: 95000,
      paymentMethod: "wallet",
      transactionStatus: "succeeded",
      paymentStatus: "paid",
      reconciliationStatus: "reconciled",
      transactionDate: "2026-02-12",
      manualReference: "WLT-PRV-10018",
      auditNote: "Agent wallet balance debit (preview data only).",
    },
  },
  {
    txId: "JP-TX-20034",
    payId: "JP-PAY-30020",
    bookingId: "JP-BK-10020",
    draft: {
      transactionType: "adjustment",
      grossAmount: 2500,
      paymentMethod: "office",
      transactionStatus: "succeeded",
      paymentStatus: "paid",
      reconciliationStatus: "reconciled",
      transactionDate: "2026-02-15",
      manualReference: "ADJ-OFF-10020",
      auditNote: "Manual office adjustment for service fee waiver.",
    },
  },
  {
    txId: "JP-TX-20035",
    payId: "JP-PAY-30022",
    bookingId: "JP-BK-10022",
    draft: {
      transactionType: "payment",
      grossAmount: 210000,
      paymentMethod: "bank_transfer",
      transactionStatus: "pending",
      paymentStatus: "pending",
      reconciliationStatus: "unreconciled",
      transactionDate: "2026-02-20",
      bankReference: "MCB-PND-88221",
      auditNote: "Bank transfer initiated — awaiting settlement confirmation.",
    },
  },
];

function buildMockTransactions(): TransactionRecord[] {
  const transactions: TransactionRecord[] = [];

  mockBookings.forEach((booking, index) => {
    const txId = `JP-TX-${20001 + index}`;
    const payId = `JP-PAY-${30001 + index}`;
    const draft = primaryDraftForBooking(booking, index);
    transactions.push(buildTransaction(txId, payId, draft, booking));
  });

  for (const extra of EXTRA_DRAFTS) {
    const booking = getBookingById(extra.bookingId);
    if (!booking) continue;
    transactions.push(
      buildTransaction(extra.txId, extra.payId, { ...extra.draft, bookingId: extra.bookingId }, booking),
    );
  }

  return transactions.sort((a, b) => b.transactionDate.localeCompare(a.transactionDate));
}

/** Deterministic preview transactions — not production data. */
export const mockTransactions: TransactionRecord[] = buildMockTransactions();

export function getTransactionById(id: string): TransactionRecord | undefined {
  return mockTransactions.find((t) => t.transactionId === id);
}
