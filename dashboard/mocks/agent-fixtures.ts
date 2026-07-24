import { mockBookings } from "@/mocks/booking-fixtures";
import { mockCustomers } from "@/mocks/customer-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import type {
  AccountStatus,
  AgentRecord,
  AgentType,
  CommercialStatus,
  SettlementStatus,
  VerificationStatus,
} from "@/types/agent";

const COMMISSION_RATES = [2.5, 3.0, 3.5, 4.0, 4.5, 5.0, 2.0, 3.25] as const;

type OfficeSeed = {
  id: string;
  agencyName: string;
  tradingName: string;
  agentSource: string;
  city: string;
  operatingRegion: string;
  primaryContact: string;
  email: string;
  phone: string;
  agentType: AgentType;
  accountStatus: AccountStatus;
  verificationStatus: VerificationStatus;
  commercialStatus: CommercialStatus;
  settlementStatus: SettlementStatus;
  createdDate: string;
  supportOwner: string;
  notesSummary: string;
  commissionRatePercent: number;
};

const OFFICE_AGENTS: OfficeSeed[] = [
  {
    id: "JP-AG-60001",
    agencyName: "Lahore Central Travel Agency",
    tradingName: "Lahore Central",
    agentSource: "Agent — Lahore Central",
    city: "Lahore",
    operatingRegion: "Punjab",
    primaryContact: "Imran Siddiqui",
    email: "lahore.central@agents-preview.example.com",
    phone: "+92 42 35771234",
    agentType: "Retail Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Preferred",
    settlementStatus: "Current",
    createdDate: "2024-04-12",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "High-volume retail desk linked to Lahore Central booking channel.",
    commissionRatePercent: 4.5,
  },
  {
    id: "JP-AG-60002",
    agencyName: "Karachi North Aviation Services",
    tradingName: "Karachi North",
    agentSource: "Agent — Karachi North",
    city: "Karachi",
    operatingRegion: "Sindh",
    primaryContact: "Sana Mirza",
    email: "karachi.north@agents-preview.example.com",
    phone: "+92 21 34567890",
    agentType: "Corporate Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Credit Enabled",
    settlementStatus: "Due",
    createdDate: "2024-05-20",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "Corporate and SME bookings via Karachi North channel.",
    commissionRatePercent: 3.5,
  },
  {
    id: "JP-AG-60003",
    agencyName: "Karachi South Travel Hub",
    tradingName: "Karachi South",
    agentSource: "Agent — Karachi South",
    city: "Karachi",
    operatingRegion: "Sindh",
    primaryContact: "Bilal Hussain",
    email: "karachi.south@agents-preview.example.com",
    phone: "+92 21 35678901",
    agentType: "Retail Agent",
    accountStatus: "Active",
    verificationStatus: "Pending",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2024-06-08",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "South Karachi walk-in and referral desk.",
    commissionRatePercent: 3.0,
  },
  {
    id: "JP-AG-60004",
    agencyName: "Islamabad Capital Air Desk",
    tradingName: "Islamabad",
    agentSource: "Agent — Islamabad",
    city: "Islamabad",
    operatingRegion: "Federal",
    primaryContact: "Hina Qureshi",
    email: "islamabad@agents-preview.example.com",
    phone: "+92 51 2345678",
    agentType: "Retail Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Preferred",
    settlementStatus: "Overdue",
    createdDate: "2024-07-15",
    supportOwner: "Partner Success — Federal",
    notesSummary: "Federal capital agency with mixed corporate and retail flow.",
    commissionRatePercent: 4.0,
  },
];

type ExtraAgentSeed = Omit<
  OfficeSeed,
  "agentSource" | "commissionRatePercent"
> & {
  commissionRatePercent?: number;
};

const EXTRA_AGENT_SEEDS: ExtraAgentSeed[] = [
  {
    id: "JP-AG-60005",
    agencyName: "Rawalpindi Express Travels",
    tradingName: "RWP Express",
    city: "Rawalpindi",
    operatingRegion: "Punjab",
    primaryContact: "Tariq Mahmood",
    email: "rwp.express@agents-preview.example.com",
    phone: "+92 51 4567890",
    agentType: "Sub-Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2024-08-01",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Sub-agent under Lahore Central network — no direct bookings yet.",
  },
  {
    id: "JP-AG-60006",
    agencyName: "Faisalabad Skyline Tours",
    tradingName: "FSD Skyline",
    city: "Faisalabad",
    operatingRegion: "Punjab",
    primaryContact: "Amjad Raza",
    email: "fsd.skyline@agents-preview.example.com",
    phone: "+92 41 8765432",
    agentType: "Retail Agent",
    accountStatus: "Inactive",
    verificationStatus: "Incomplete",
    commercialStatus: "Prepaid Only",
    settlementStatus: "Not Applicable",
    createdDate: "2024-09-10",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Dormant retail partner — onboarding incomplete.",
  },
  {
    id: "JP-AG-60007",
    agencyName: "Multan Heritage Travel",
    tradingName: "Multan Heritage",
    city: "Multan",
    operatingRegion: "Punjab",
    primaryContact: "Rukhsana Bibi",
    email: "multan.heritage@agents-preview.example.com",
    phone: "+92 61 1122334",
    agentType: "Referral Partner",
    accountStatus: "Active",
    verificationStatus: "Pending",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2024-10-05",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Referral-only partner with linked dormant customers.",
  },
  {
    id: "JP-AG-60008",
    agencyName: "Peshawar Gateway Aviation",
    tradingName: "Peshawar Gateway",
    city: "Peshawar",
    operatingRegion: "KPK",
    primaryContact: "Javed Afridi",
    email: "peshawar.gateway@agents-preview.example.com",
    phone: "+92 91 2233445",
    agentType: "Corporate Agent",
    accountStatus: "Review Required",
    verificationStatus: "Pending",
    commercialStatus: "On Hold",
    settlementStatus: "Reconciliation Required",
    createdDate: "2024-11-18",
    supportOwner: "Partner Success — KPK",
    notesSummary: "Flagged for routine commercial review — synthetic preview case.",
  },
  {
    id: "JP-AG-60009",
    agencyName: "Quetta Baloch Air Services",
    tradingName: "Quetta BAS",
    city: "Quetta",
    operatingRegion: "Balochistan",
    primaryContact: "Nasir Baloch",
    email: "quetta.bas@agents-preview.example.com",
    phone: "+92 81 3344556",
    agentType: "Retail Agent",
    accountStatus: "Suspended",
    verificationStatus: "Incomplete",
    commercialStatus: "On Hold",
    settlementStatus: "Overdue",
    createdDate: "2024-12-02",
    supportOwner: "Partner Success — Balochistan",
    notesSummary: "Suspended agency with overdue settlement — preview edge case.",
  },
  {
    id: "JP-AG-60010",
    agencyName: "Sialkot Trade Wings",
    tradingName: "Sialkot Wings",
    city: "Sialkot",
    operatingRegion: "Punjab",
    primaryContact: "Kamran Sheikh",
    email: "sialkot.wings@agents-preview.example.com",
    phone: "+92 52 4455667",
    agentType: "Corporate Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Credit Enabled",
    settlementStatus: "Due",
    createdDate: "2025-01-14",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Export-sector corporate travel desk.",
  },
  {
    id: "JP-AG-60011",
    agencyName: "Hyderabad Sindh Connect",
    tradingName: "Hyd Connect",
    city: "Hyderabad",
    operatingRegion: "Sindh",
    primaryContact: "Farah Soomro",
    email: "hyd.connect@agents-preview.example.com",
    phone: "+92 22 5566778",
    agentType: "Online Partner",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2025-02-20",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "Online partner portal — commission accrual pending first sale.",
  },
  {
    id: "JP-AG-60012",
    agencyName: "Gujranwala City Flyers",
    tradingName: "GJW Flyers",
    city: "Gujranwala",
    operatingRegion: "Punjab",
    primaryContact: "Shahid Anwar",
    email: "gjw.flyers@agents-preview.example.com",
    phone: "+92 55 6677889",
    agentType: "Walk-in Desk",
    accountStatus: "Active",
    verificationStatus: "Not Required",
    commercialStatus: "Prepaid Only",
    settlementStatus: "Not Applicable",
    createdDate: "2025-03-08",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Walk-in counter with prepaid-only commercial terms.",
  },
  {
    id: "JP-AG-60013",
    agencyName: "Abbottabad Highland Travel",
    tradingName: "Abbottabad HT",
    city: "Abbottabad",
    operatingRegion: "KPK",
    primaryContact: "Yasir Khan",
    email: "abbottabad.ht@agents-preview.example.com",
    phone: "+92 992 778899",
    agentType: "Retail Agent",
    accountStatus: "Inactive",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Not Applicable",
    createdDate: "2025-04-22",
    supportOwner: "Partner Success — KPK",
    notesSummary: "Seasonal inactive partner — verified but dormant.",
  },
  {
    id: "JP-AG-60014",
    agencyName: "Sukkur River Air Desk",
    tradingName: "Sukkur Air",
    city: "Sukkur",
    operatingRegion: "Sindh",
    primaryContact: "Asma Junejo",
    email: "sukkur.air@agents-preview.example.com",
    phone: "+92 71 889900",
    agentType: "Sub-Agent",
    accountStatus: "Active",
    verificationStatus: "Pending",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2025-05-30",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "Sub-agent onboarding in progress.",
  },
  {
    id: "JP-AG-60015",
    agencyName: "Bahawalpur Royal Routes",
    tradingName: "BWP Royal",
    city: "Bahawalpur",
    operatingRegion: "Punjab",
    primaryContact: "Naveed Chaudhry",
    email: "bwp.royal@agents-preview.example.com",
    phone: "+92 62 990011",
    agentType: "Referral Partner",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Preferred",
    settlementStatus: "Current",
    createdDate: "2025-06-15",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Preferred referral partner with linked preview customers.",
  },
  {
    id: "JP-AG-60016",
    agencyName: "Mirpur AJK Travel Link",
    tradingName: "Mirpur Link",
    city: "Mirpur",
    operatingRegion: "AJK",
    primaryContact: "Zahid Qayyum",
    email: "mirpur.link@agents-preview.example.com",
    phone: "+92 582 112233",
    agentType: "Retail Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Credit Enabled",
    settlementStatus: "Due",
    createdDate: "2025-07-01",
    supportOwner: "Partner Success — AJK",
    notesSummary: "AJK diaspora travel specialist.",
  },
  {
    id: "JP-AG-60017",
    agencyName: "Gilgit Baltistan Expeditions",
    tradingName: "GB Expeditions",
    city: "Gilgit",
    operatingRegion: "Gilgit-Baltistan",
    primaryContact: "Sher Ali",
    email: "gb.expeditions@agents-preview.example.com",
    phone: "+92 581 223344",
    agentType: "Corporate Agent",
    accountStatus: "Review Required",
    verificationStatus: "Incomplete",
    commercialStatus: "On Hold",
    settlementStatus: "Reconciliation Required",
    createdDate: "2025-08-12",
    supportOwner: "Partner Success — GB",
    notesSummary: "Tourism corporate desk under review.",
  },
  {
    id: "JP-AG-60018",
    agencyName: "Muzaffarabad Summit Air",
    tradingName: "Mzd Summit",
    city: "Muzaffarabad",
    operatingRegion: "AJK",
    primaryContact: "Rubina Akhtar",
    email: "mzd.summit@agents-preview.example.com",
    phone: "+92 582 334455",
    agentType: "Online Partner",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2025-09-05",
    supportOwner: "Partner Success — AJK",
    notesSummary: "Online partner with no bookings — commission pipeline empty.",
  },
  {
    id: "JP-AG-60019",
    agencyName: "Larkana Sindh Wings",
    tradingName: "Larkana Wings",
    city: "Larkana",
    operatingRegion: "Sindh",
    primaryContact: "Ghulam Murtaza",
    email: "larkana.wings@agents-preview.example.com",
    phone: "+92 74 445566",
    agentType: "Walk-in Desk",
    accountStatus: "Suspended",
    verificationStatus: "Pending",
    commercialStatus: "Prepaid Only",
    settlementStatus: "Overdue",
    createdDate: "2025-10-18",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "Suspended walk-in desk — overdue balance preview case.",
  },
  {
    id: "JP-AG-60020",
    agencyName: "JetPakistan Internal Sales East",
    tradingName: "JP Internal East",
    city: "Lahore",
    operatingRegion: "Punjab",
    primaryContact: "Operations Desk",
    email: "internal.east@jetpakistan-preview.example.com",
    phone: "+92 42 111000200",
    agentType: "Internal Sales",
    accountStatus: "Active",
    verificationStatus: "Not Required",
    commercialStatus: "Standard",
    settlementStatus: "Not Applicable",
    createdDate: "2025-11-01",
    supportOwner: "Internal Ops",
    notesSummary: "Internal sales channel — not a third-party partner.",
    commissionRatePercent: 0,
  },
  {
    id: "JP-AG-60021",
    agencyName: "Dubai Diaspora Connect PK",
    tradingName: "Diaspora DXB",
    city: "Karachi",
    operatingRegion: "Sindh",
    primaryContact: "Ahmed Farooqi",
    email: "diaspora.dxb@agents-preview.example.com",
    phone: "+92 21 7788990",
    agentType: "Online Partner",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Preferred",
    settlementStatus: "Current",
    createdDate: "2025-11-20",
    supportOwner: "Partner Success — Sindh",
    notesSummary: "UAE diaspora online partner with referred customers.",
  },
  {
    id: "JP-AG-60022",
    agencyName: "Sahiwal Central Air",
    tradingName: "Sahiwal Central",
    city: "Sahiwal",
    operatingRegion: "Punjab",
    primaryContact: "Nabeel Iqbal",
    email: "sahiwal.central@agents-preview.example.com",
    phone: "+92 40 556677",
    agentType: "Retail Agent",
    accountStatus: "Inactive",
    verificationStatus: "Not Required",
    commercialStatus: "Standard",
    settlementStatus: "Not Applicable",
    createdDate: "2025-12-05",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "Inactive retail registration — no activity.",
  },
  {
    id: "JP-AG-60023",
    agencyName: "Mardan KPK Air Link",
    tradingName: "Mardan Air",
    city: "Mardan",
    operatingRegion: "KPK",
    primaryContact: "Irfan Ullah",
    email: "mardan.air@agents-preview.example.com",
    phone: "+92 937 667788",
    agentType: "Sub-Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2026-01-08",
    supportOwner: "Partner Success — KPK",
    notesSummary: "KPK sub-agent with pending commission accrual.",
  },
  {
    id: "JP-AG-60024",
    agencyName: "Okara Prairie Travels",
    tradingName: "Okara Prairie",
    city: "Okara",
    operatingRegion: "Punjab",
    primaryContact: "Samina Parveen",
    email: "okara.prairie@agents-preview.example.com",
    phone: "+92 44 778899",
    agentType: "Referral Partner",
    accountStatus: "Active",
    verificationStatus: "Pending",
    commercialStatus: "Standard",
    settlementStatus: "Due",
    createdDate: "2026-01-22",
    supportOwner: "Partner Success — Punjab",
    notesSummary: "New referral partner — verification pending.",
  },
  {
    id: "JP-AG-60025",
    agencyName: "Gwadar Coastal Aviation",
    tradingName: "Gwadar Coastal",
    city: "Gwadar",
    operatingRegion: "Balochistan",
    primaryContact: "Hamza Mengal",
    email: "gwadar.coastal@agents-preview.example.com",
    phone: "+92 86 889900",
    agentType: "Corporate Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Credit Enabled",
    settlementStatus: "Current",
    createdDate: "2026-02-01",
    supportOwner: "Partner Success — Balochistan",
    notesSummary: "CPEC corridor corporate travel desk.",
  },
  {
    id: "JP-AG-60026",
    agencyName: "JetPakistan Partner Sandbox",
    tradingName: "JP Sandbox",
    city: "Islamabad",
    operatingRegion: "Federal",
    primaryContact: "QA Preview",
    email: "sandbox@jetpakistan-preview.example.com",
    phone: "+92 51 0000000",
    agentType: "Retail Agent",
    accountStatus: "Active",
    verificationStatus: "Verified",
    commercialStatus: "Standard",
    settlementStatus: "Current",
    createdDate: "2026-02-15",
    supportOwner: "QA Preview",
    notesSummary: "Synthetic sandbox agent for Playwright and preview QA.",
  },
];

function transactionsForBookings(bookingIds: string[]): string[] {
  return mockTransactions
    .filter((tx) => bookingIds.includes(tx.bookingId))
    .map((tx) => tx.transactionId);
}

function customerIdForBookingIndex(index: number): string {
  return `JP-CU-${String(40001 + index).padStart(5, "0")}`;
}

function pnrIdForBookingIndex(index: number): string {
  return `JP-PN-${String(70001 + index).padStart(5, "0")}`;
}

function ticketIdForBookingIndex(index: number): string {
  return `JP-TK-${String(80001 + index).padStart(5, "0")}`;
}

function buildAgentFromOffice(seed: OfficeSeed): AgentRecord {
  const bookingIndices = mockBookings
    .map((b, i) => ({ b, i }))
    .filter(({ b }) => b.agentOrSource === seed.agentSource)
    .map(({ i }) => i);

  const linkedBookings = bookingIndices.map((i) => mockBookings[i]!);
  const linkedBookingIds = linkedBookings.map((b) => b.id);
  const linkedCustomerIds = [...new Set(bookingIndices.map(customerIdForBookingIndex))];
  const linkedTransactionIds = transactionsForBookings(linkedBookingIds);
  const linkedPnrIds = bookingIndices.map(pnrIdForBookingIndex);
  const linkedTicketIds = linkedBookings
    .filter((b) => b.ticketingStatus === "ticketed")
    .map((b) => ticketIdForBookingIndex(mockBookings.findIndex((x) => x.id === b.id)));

  let grossBookingValue = 0;
  let totalPaid = 0;
  let confirmedBookingCount = 0;
  let cancelledBookingCount = 0;
  let ticketedBookingCount = 0;
  let travellerCount = 0;
  let refundExposure = 0;
  const bookingDates: string[] = [];
  const paymentDates: string[] = [];
  const ticketDates: string[] = [];

  for (const booking of linkedBookings) {
    grossBookingValue += booking.totalAmount;
    totalPaid += booking.amountPaid;
    travellerCount += booking.passengerCount;
    bookingDates.push(booking.bookingDate);
    if (booking.bookingStatus === "confirmed" || booking.bookingStatus === "pending") {
      confirmedBookingCount += 1;
    }
    if (booking.bookingStatus === "cancelled") {
      cancelledBookingCount += 1;
    }
    if (booking.ticketingStatus === "ticketed") {
      ticketedBookingCount += 1;
      ticketDates.push(booking.bookingDate);
    }
    refundExposure += mockTransactions
      .filter((tx) => tx.bookingId === booking.id && tx.transactionType === "refund")
      .reduce((sum, tx) => sum + tx.grossAmount, 0);
  }

  for (const txId of linkedTransactionIds) {
    const tx = mockTransactions.find((t) => t.transactionId === txId);
    if (tx) paymentDates.push(tx.transactionDate);
  }

  const outstandingCustomerBalance = Math.max(0, grossBookingValue - totalPaid);
  const commissionEarned = Math.round(grossBookingValue * (seed.commissionRatePercent / 100));
  const commissionPaid = Math.round(totalPaid * (seed.commissionRatePercent / 100) * 0.6);
  const commissionPending = Math.max(0, commissionEarned - commissionPaid);

  bookingDates.sort();
  paymentDates.sort();
  ticketDates.sort();

  return {
    id: seed.id,
    agencyName: seed.agencyName,
    tradingName: seed.tradingName,
    agentType: seed.agentType,
    city: seed.city,
    country: "Pakistan",
    operatingRegion: seed.operatingRegion,
    primaryContact: seed.primaryContact,
    email: seed.email,
    phone: seed.phone,
    accountStatus: seed.accountStatus,
    verificationStatus: seed.verificationStatus,
    commercialStatus: seed.commercialStatus,
    settlementStatus: seed.settlementStatus,
    preferredCurrency: "PKR",
    commissionRatePercent: seed.commissionRatePercent,
    customerCount: linkedCustomerIds.length,
    travellerCount,
    bookingCount: linkedBookingIds.length,
    confirmedBookingCount,
    cancelledBookingCount,
    ticketedBookingCount,
    grossBookingValue,
    totalPaid,
    outstandingCustomerBalance,
    commissionEarned,
    commissionPaid,
    commissionPending,
    refundExposure,
    lastBookingDate: bookingDates.length > 0 ? bookingDates[bookingDates.length - 1]! : null,
    lastPaymentDate: paymentDates.length > 0 ? paymentDates[paymentDates.length - 1]! : null,
    lastTicketActivity: ticketDates.length > 0 ? ticketDates[ticketDates.length - 1]! : null,
    createdDate: seed.createdDate,
    supportOwner: seed.supportOwner,
    notesSummary: seed.notesSummary,
    linkedCustomerIds,
    linkedBookingIds,
    linkedTransactionIds,
    linkedPnrIds,
    linkedTicketIds,
    currency: "PKR",
  };
}

function buildExtraAgent(seed: ExtraAgentSeed, index: number): AgentRecord {
  const commissionRatePercent =
    seed.commissionRatePercent ?? COMMISSION_RATES[index % COMMISSION_RATES.length]!;

  const linkedCustomerIds =
    index % 5 === 0
      ? [mockCustomers[25 + (index % 5)]!.id]
      : index % 7 === 0
        ? [mockCustomers[26 + (index % 4)]!.id, mockCustomers[27 + (index % 3)]!.id]
        : [];

  const customerTravellers = linkedCustomerIds.reduce((sum, cid) => {
    const customer = mockCustomers.find((c) => c.id === cid);
    return sum + (customer?.travellerCount ?? 0);
  }, 0);

  const hasSyntheticCommission = index === 22 || index === 6;
  const commissionPending = hasSyntheticCommission ? 12500 + index * 500 : 0;
  const commissionEarned = commissionPending;
  const outstandingCustomerBalance =
    seed.settlementStatus === "Overdue" ? 45000 + index * 1000 : seed.settlementStatus === "Due" ? 12000 : 0;

  return {
    id: seed.id,
    agencyName: seed.agencyName,
    tradingName: seed.tradingName,
    agentType: seed.agentType,
    city: seed.city,
    country: "Pakistan",
    operatingRegion: seed.operatingRegion,
    primaryContact: seed.primaryContact,
    email: seed.email,
    phone: seed.phone,
    accountStatus: seed.accountStatus,
    verificationStatus: seed.verificationStatus,
    commercialStatus: seed.commercialStatus,
    settlementStatus: seed.settlementStatus,
    preferredCurrency: "PKR",
    commissionRatePercent,
    customerCount: linkedCustomerIds.length,
    travellerCount: customerTravellers,
    bookingCount: 0,
    confirmedBookingCount: 0,
    cancelledBookingCount: 0,
    ticketedBookingCount: 0,
    grossBookingValue: 0,
    totalPaid: 0,
    outstandingCustomerBalance,
    commissionEarned,
    commissionPaid: 0,
    commissionPending,
    refundExposure: 0,
    lastBookingDate: null,
    lastPaymentDate: null,
    lastTicketActivity: null,
    createdDate: seed.createdDate,
    supportOwner: seed.supportOwner,
    notesSummary: seed.notesSummary,
    linkedCustomerIds,
    linkedBookingIds: [],
    linkedTransactionIds: [],
    linkedPnrIds: [],
    linkedTicketIds: [],
    currency: "PKR",
  };
}

/** Deterministic preview agents — not production data. */
export const mockAgents: AgentRecord[] = [
  ...OFFICE_AGENTS.map(buildAgentFromOffice),
  ...EXTRA_AGENT_SEEDS.map((seed, index) => buildExtraAgent(seed, index)),
];

export function getAgentById(id: string): AgentRecord | undefined {
  return mockAgents.find((a) => a.id === id);
}

export const AGENT_FIXTURE_COUNT = mockAgents.length;

export function agentSourceForId(id: string): string | undefined {
  const office = OFFICE_AGENTS.find((a) => a.id === id);
  return office?.agentSource;
}
