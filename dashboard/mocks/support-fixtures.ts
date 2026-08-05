export type SupportTicketRecord = {
  id: string;
  subject: string;
  status: string;
  assignedTo?: string | null;
};

export const mockSupportTickets: SupportTicketRecord[] = [
  {
    id: "501",
    subject: "PNR change request",
    status: "open",
    assignedTo: null,
  },
];
