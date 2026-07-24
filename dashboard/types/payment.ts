export type PaymentMethod = "card" | "bank_transfer" | "cash" | "wallet" | "office";

export type PaymentChannel = "web" | "agent" | "mobile" | "api";

export type TransactionType = "payment" | "refund" | "reversal" | "fee" | "adjustment";

export type LedgerPaymentStatus =
  | "paid"
  | "unpaid"
  | "partial"
  | "pending"
  | "failed"
  | "reversed"
  | "refunded"
  | "partially_refunded";

export type TransactionStatus = "succeeded" | "failed" | "pending" | "cancelled";

export type ReconciliationState = "reconciled" | "unreconciled" | "disputed" | "pending_review";

export type PaymentSource = "web_direct" | "agent" | "mobile" | "api" | "office";

export type TransactionRecord = {
  transactionId: string;
  paymentId: string;
  bookingId: string;
  pnr: string;
  supplierReference: string | null;
  customerName: string;
  customerEmail: string;
  customerPhone: string;
  transactionDate: string;
  bookingDate: string;
  currency: string;
  grossAmount: number;
  paidAmount: number;
  outstandingAmount: number;
  refundedAmount: number;
  feeAmount: number;
  netAmount: number;
  paymentMethod: PaymentMethod;
  paymentChannel: PaymentChannel;
  transactionType: TransactionType;
  paymentStatus: LedgerPaymentStatus;
  transactionStatus: TransactionStatus;
  reconciliationStatus: ReconciliationState;
  gatewayReference: string | null;
  bankReference: string | null;
  manualReference: string | null;
  sourceOrAgent: string;
  createdAt: string;
  updatedAt: string;
  auditNote: string;
};

export type PaymentSortField =
  | "transactionDate"
  | "paymentId"
  | "booking"
  | "customer"
  | "grossAmount"
  | "netAmount"
  | "outstandingAmount"
  | "paymentStatus"
  | "reconciliationStatus"
  | "lastUpdated";

export type SortDirection = "asc" | "desc";

export type PaymentsQuery = {
  q: string;
  paymentStatus: LedgerPaymentStatus | "all";
  transactionStatus: TransactionStatus | "all";
  type: TransactionType | "all";
  method: PaymentMethod | "all";
  channel: PaymentChannel | "all";
  reconciliation: ReconciliationState | "all";
  currency: string;
  dateFrom: string;
  dateTo: string;
  minAmount: string;
  maxAmount: string;
  page: number;
  pageSize: number;
  sort: PaymentSortField;
  direction: SortDirection;
  selectedTransactionId: string | null;
  previewError: boolean;
};

export type PaymentsSummaryMetrics = {
  totalTransactions: number;
  grossCollected: number;
  netCollected: number;
  outstandingAmount: number;
  refundedAmount: number;
  failedOrPendingCount: number;
  unreconciledCount: number;
  currency: string;
};

export type PaymentsPageResult = {
  transactions: TransactionRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: PaymentsSummaryMetrics;
  facets: {
    currencies: string[];
    methods: PaymentMethod[];
    channels: PaymentChannel[];
  };
};
