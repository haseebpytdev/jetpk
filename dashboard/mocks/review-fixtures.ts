export type ReviewCapabilities = {
  can_approve?: boolean;
  can_reject?: boolean;
  already_processed?: boolean;
  pending_reconciliation?: boolean;
  denial_reason?: string;
};

export type CancellationReviewRecord = {
  id: string;
  bookingId: string;
  status: string;
  pnr: string;
  capabilities?: ReviewCapabilities | null;
};

export type RefundReviewRecord = {
  id: string;
  bookingId: string;
  status: string;
  amount: number;
  currency: string;
  capabilities?: ReviewCapabilities | null;
};

export const mockCancellationReviews: CancellationReviewRecord[] = [
  {
    id: "801",
    bookingId: "12045",
    status: "requested",
    pnr: "ABC123",
    capabilities: { can_approve: true, can_reject: true },
  },
];

export const mockRefundReviews: RefundReviewRecord[] = [
  {
    id: "601",
    bookingId: "12045",
    status: "pending",
    amount: 15000,
    currency: "PKR",
    capabilities: { can_approve: true, can_reject: true },
  },
];
