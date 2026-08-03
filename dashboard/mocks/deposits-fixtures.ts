import type { DepositRecord } from "@/services/deposit-service";

export const mockDeposits: DepositRecord[] = [
  {
    id: "101",
    reference: "DEP-101",
    status: "submitted",
    amount: 250000,
    currency: "PKR",
    agencyName: "Skyline Travels",
    agentName: "Adeel Khan",
    submittedAt: "2026-08-01T10:00:00+00:00",
    capabilities: { can_approve: true, can_reject: true, already_processed: false },
  },
];
