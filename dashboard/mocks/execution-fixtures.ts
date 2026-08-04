export type ExecutionCapabilities = {
  can_process?: boolean;
  can_mark_paid?: boolean;
  can_issue_ticket?: boolean;
  already_processed?: boolean;
  already_ticketed?: boolean;
  pending_reconciliation?: boolean;
};

export type CancellationExecutionRecord = {
  id: string;
  bookingId: string;
  status: string;
  pnr: string;
  capabilities?: ExecutionCapabilities | null;
};

export type RefundExecutionRecord = {
  id: string;
  bookingId: string;
  status: string;
  amount: number;
  currency: string;
  capabilities?: ExecutionCapabilities | null;
};

export type TicketingExecutionRecord = {
  bookingId: string;
  pnr: string;
  status: string;
  ticketingStatus: string;
  capabilities?: ExecutionCapabilities | null;
};

export const mockCancellationExecutions: CancellationExecutionRecord[] = [
  {
    id: "901",
    bookingId: "12045",
    status: "approved",
    pnr: "ABC123",
    capabilities: { can_process: true },
  },
];

export const mockRefundExecutions: RefundExecutionRecord[] = [
  {
    id: "701",
    bookingId: "12045",
    status: "approved",
    amount: 15000,
    currency: "PKR",
    capabilities: { can_mark_paid: true },
  },
];

export const mockTicketingExecutions: TicketingExecutionRecord[] = [
  {
    bookingId: "12046",
    pnr: "XYZ789",
    status: "paid",
    ticketingStatus: "pending",
    capabilities: { can_issue_ticket: true },
  },
];
