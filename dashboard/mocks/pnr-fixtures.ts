import { mockBookings } from "@/mocks/booking-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import type {
  PnrCancellationEligibility,
  PnrChannel,
  PnrFulfilmentStatus,
  PnrLifecycleStatus,
  PnrPaymentStatus,
  PnrQueueReviewStatus,
  PnrRecord,
  PnrReferenceType,
  PnrTicketingStatus,
} from "@/types/pnr";
import { mapBookingPaymentStatus } from "@/types/pnr";

const AGENT_BY_SOURCE: Record<string, { id: string; name: string }> = {
  "Agent — Lahore Central": { id: "JP-AG-60001", name: "Lahore Central Travel" },
  "Agent — Karachi North": { id: "JP-AG-60002", name: "Karachi North Agency" },
  "Agent — Karachi South": { id: "JP-AG-60003", name: "Karachi South Partners" },
  "Agent — Islamabad": { id: "JP-AG-60004", name: "Islamabad City Desk" },
};

const SUPPLIER_BY_NAME: Record<string, { id: string; name: string }> = {
  Sabre: { id: "JP-SU-50001", name: "Sabre" },
  Duffel: { id: "JP-SU-50002", name: "Duffel" },
};

const CABINS = ["Economy", "Premium Economy", "Business"] as const;

const LIFECYCLE_STATUSES: PnrLifecycleStatus[] = [
  "Active",
  "Confirmed",
  "On Hold",
  "Pending Supplier",
  "Partially Confirmed",
  "Cancelled",
  "Expired",
  "Failed",
  "Review Required",
];

const FULFILMENT_STATUSES: PnrFulfilmentStatus[] = [
  "Not Required",
  "Pending",
  "Partially Fulfilled",
  "Fulfilled",
  "Failed",
  "Refunded",
];

const TICKETING_STATUSES: PnrTicketingStatus[] = [
  "Not Ticketed",
  "Ready for Ticketing",
  "Ticketing Blocked",
  "Partially Ticketed",
  "Ticketed",
  "Failed",
  "Voided",
  "Refunded",
  "Not Applicable",
];

const CANCELLATION_ELIGIBILITY: PnrCancellationEligibility[] = [
  "Eligible",
  "Not Eligible",
  "Supplier Review Required",
  "Already Cancelled",
  "Unknown",
  "Not Applicable",
];

function transactionsForBooking(bookingId: string): string[] {
  return mockTransactions.filter((tx) => tx.bookingId === bookingId).map((tx) => tx.transactionId);
}

function lifecycleFromBooking(
  bookingStatus: string,
  index: number,
): PnrLifecycleStatus {
  if (bookingStatus === "confirmed") return index % 9 === 0 ? "Active" : "Confirmed";
  if (bookingStatus === "pending") return index % 3 === 0 ? "On Hold" : "Pending Supplier";
  if (bookingStatus === "cancelled") return "Cancelled";
  if (bookingStatus === "failed") return "Failed";
  return LIFECYCLE_STATUSES[index % LIFECYCLE_STATUSES.length]!;
}

function fulfilmentFromBooking(
  referenceType: PnrReferenceType,
  bookingStatus: string,
  ticketingStatus: string,
  index: number,
): PnrFulfilmentStatus {
  if (referenceType === "GDS PNR") {
    if (bookingStatus === "cancelled") return "Refunded";
    if (ticketingStatus === "ticketed") return "Fulfilled";
    if (ticketingStatus === "pending") return "Partially Fulfilled";
    return index % 4 === 0 ? "Pending" : "Not Required";
  }
  if (bookingStatus === "failed") return "Failed";
  if (bookingStatus === "cancelled") return "Refunded";
  if (ticketingStatus === "ticketed") return "Fulfilled";
  return FULFILMENT_STATUSES[index % FULFILMENT_STATUSES.length]!;
}

function ticketingFromBooking(
  channel: PnrChannel,
  referenceType: PnrReferenceType,
  bookingStatus: string,
  bookingTicketing: string,
  index: number,
): PnrTicketingStatus {
  if (referenceType !== "GDS PNR") {
    if (bookingTicketing === "ticketed") return "Not Applicable";
    return "Not Applicable";
  }
  if (bookingStatus === "cancelled") return index % 2 === 0 ? "Voided" : "Refunded";
  if (bookingTicketing === "ticketed") return "Ticketed";
  if (channel === "Sabre GDS") return "Ticketing Blocked";
  if (bookingTicketing === "pending") return "Ready for Ticketing";
  return TICKETING_STATUSES[index % TICKETING_STATUSES.length]!;
}

function cancellationFromBooking(bookingStatus: string, index: number): PnrCancellationEligibility {
  if (bookingStatus === "cancelled") return "Already Cancelled";
  if (bookingStatus === "failed") return "Not Eligible";
  return CANCELLATION_ELIGIBILITY[index % CANCELLATION_ELIGIBILITY.length]!;
}

function queueReviewFromRecord(lifecycle: PnrLifecycleStatus, index: number): PnrQueueReviewStatus {
  if (lifecycle === "Review Required") return "Review Required";
  if (index % 13 === 0) return "Supplier Queue";
  if (index % 17 === 0) return "Ticketing Queue";
  return "None";
}

function buildTravellerNames(customerName: string, count: number): string[] {
  const parts = customerName.split(" ");
  const last = parts[parts.length - 1] ?? "Traveller";
  const names = [customerName];
  for (let i = 1; i < count; i += 1) {
    names.push(`${["Ahmed", "Sara", "Ali", "Zara", "Omar"][i % 5]} ${last}`);
  }
  return names;
}

function buildFromBooking(index: number): PnrRecord {
  const booking = mockBookings[index]!;
  const id = `JP-PN-${String(70001 + index).padStart(5, "0")}`;
  const customerId = `JP-CU-${String(40001 + index).padStart(5, "0")}`;
  const isSabre = booking.supplier === "Sabre";
  const referenceType: PnrReferenceType = isSabre ? "GDS PNR" : "NDC Order";
  const channel: PnrChannel = isSabre ? "Sabre GDS" : "Sabre NDC";
  const supplier = SUPPLIER_BY_NAME[booking.supplier] ?? { id: "JP-SU-50001", name: booking.supplier };
  const agent = AGENT_BY_SOURCE[booking.agentOrSource] ?? null;
  const lifecycleStatus = lifecycleFromBooking(booking.bookingStatus, index);
  const ticketingStatus = ticketingFromBooking(
    channel,
    referenceType,
    booking.bookingStatus,
    booking.ticketingStatus,
    index,
  );
  const fulfilmentStatus = fulfilmentFromBooking(
    referenceType,
    booking.bookingStatus,
    booking.ticketingStatus,
    index,
  );
  const paymentStatus: PnrPaymentStatus = mapBookingPaymentStatus(booking.paymentStatus);
  const linkedTransactions = transactionsForBooking(booking.id);
  const linkedTickets =
    booking.ticketingStatus === "ticketed" ? [`JP-TK-${String(80001 + index).padStart(5, "0")}`] : [];
  const travellerNames = buildTravellerNames(booking.customerName, booking.passengerCount);
  const ticketingDeadline =
    booking.bookingStatus === "confirmed" || booking.bookingStatus === "pending"
      ? `2026-${String(((index + 2) % 12) + 1).padStart(2, "0")}-${String(((index + 5) % 28) + 1).padStart(2, "0")}`
      : null;

  return {
    id,
    externalReference: booking.pnr,
    referenceType,
    channel,
    supplierId: supplier.id,
    supplierName: supplier.name,
    airline: booking.airline,
    bookingId: booking.id,
    customerId,
    customerName: booking.customerName,
    agentId: agent?.id ?? null,
    agentName: agent?.name ?? null,
    travellerCount: booking.passengerCount,
    travellerNames,
    itinerarySummary: `${booking.origin} → ${booking.destination}${booking.returnDate ? " (return)" : ""}`,
    origin: booking.origin,
    destination: booking.destination,
    departureDate: booking.departureDate,
    returnDate: booking.returnDate,
    tripType: booking.tripType,
    cabin: CABINS[index % CABINS.length]!,
    lifecycleStatus,
    fulfilmentStatus,
    paymentStatus,
    ticketingStatus,
    ticketingDeadline,
    cancellationEligibility: cancellationFromBooking(booking.bookingStatus, index),
    queueReviewStatus: queueReviewFromRecord(lifecycleStatus, index),
    createdDate: booking.bookingDate,
    lastModifiedDate: booking.lastUpdated.slice(0, 10),
    lastSupplierActivity: booking.lastUpdated,
    linkedTicketIds: linkedTickets,
    linkedTransactionIds: linkedTransactions,
    bookingValue: booking.totalAmount,
    currency: booking.currency,
    notesSummary: `Preview ${referenceType.toLowerCase()} linked to booking ${booking.id} via ${channel}.`,
    showGdsTicketingLimitation:
      channel === "Sabre GDS" && ticketingStatus === "Ticketing Blocked",
  };
}

type ExtraPnrSeed = {
  referenceType: PnrReferenceType;
  channel: PnrChannel;
  supplierId: string;
  supplierName: string;
  airline: string;
  externalReference: string;
  bookingId: string;
  customerId: string;
  customerName: string;
  agentId: string | null;
  agentName: string | null;
  lifecycleStatus: PnrLifecycleStatus;
  fulfilmentStatus: PnrFulfilmentStatus;
  paymentStatus: PnrPaymentStatus;
  ticketingStatus: PnrTicketingStatus;
  cancellationEligibility: PnrCancellationEligibility;
  queueReviewStatus: PnrQueueReviewStatus;
  origin: string;
  destination: string;
  departureDate: string;
  returnDate: string | null;
  tripType: "one_way" | "return";
  travellerCount: number;
  bookingValue: number;
  ticketingDeadline: string | null;
  linkedTicketIds: string[];
  linkedTransactionIds: string[];
  notesSummary: string;
};

const EXTRA_PNRS: ExtraPnrSeed[] = [
  {
    referenceType: "NDC Order",
    channel: "Sabre NDC",
    supplierId: "JP-SU-50002",
    supplierName: "Duffel",
    airline: "Qatar Airways",
    externalReference: "NDC-QR-7K2M9",
    bookingId: "JP-BK-10001",
    customerId: "JP-CU-40001",
    customerName: "Ayesha Khan",
    agentId: null,
    agentName: null,
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Pending",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "None",
    origin: "KHI",
    destination: "DOH",
    departureDate: "2026-04-12",
    returnDate: "2026-04-19",
    tripType: "return",
    travellerCount: 2,
    bookingValue: 312000,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80026"],
    linkedTransactionIds: ["JP-TX-20001"],
    notesSummary: "Standalone NDC order preview — fulfilment document pending.",
  },
  {
    referenceType: "One API Order",
    channel: "One API",
    supplierId: "JP-SU-50006",
    supplierName: "PIA NDC",
    airline: "PIA",
    externalReference: "IATI-ORD-88421",
    bookingId: "JP-BK-10004",
    customerId: "JP-CU-40004",
    customerName: "Omar Siddiqui",
    agentId: "JP-AG-60002",
    agentName: "Karachi North Agency",
    lifecycleStatus: "Active",
    fulfilmentStatus: "Fulfilled",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Supplier Review Required",
    queueReviewStatus: "None",
    origin: "KHI",
    destination: "LHE",
    departureDate: "2026-03-22",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 48500,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80027"],
    linkedTransactionIds: ["JP-TX-20004"],
    notesSummary: "One API order reference — not a GDS PNR.",
  },
  {
    referenceType: "Manual Reference",
    channel: "Manual",
    supplierId: "JP-SU-50003",
    supplierName: "Emirates",
    airline: "Emirates",
    externalReference: "MAN-EM-44219",
    bookingId: "JP-BK-10006",
    customerId: "JP-CU-40006",
    customerName: "Zainab Malik",
    agentId: "JP-AG-60003",
    agentName: "Karachi South Partners",
    lifecycleStatus: "Review Required",
    fulfilmentStatus: "Pending",
    paymentStatus: "Partially Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Unknown",
    queueReviewStatus: "Review Required",
    origin: "LHE",
    destination: "DXB",
    departureDate: "2026-05-03",
    returnDate: "2026-05-10",
    tripType: "return",
    travellerCount: 2,
    bookingValue: 268000,
    ticketingDeadline: "2026-04-28",
    linkedTicketIds: [],
    linkedTransactionIds: ["JP-TX-20006"],
    notesSummary: "Manual supplier reference entered by operations — read-only preview.",
  },
  {
    referenceType: "NDC Order",
    channel: "Sabre NDC",
    supplierId: "JP-SU-50002",
    supplierName: "Duffel",
    airline: "Turkish Airlines",
    externalReference: "NDC-TK-99102",
    bookingId: "JP-BK-10008",
    customerId: "JP-CU-40008",
    customerName: "Bilal Hussain",
    agentId: "JP-AG-60001",
    agentName: "Lahore Central Travel",
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Partially Fulfilled",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "Supplier Queue",
    origin: "ISB",
    destination: "IST",
    departureDate: "2026-04-01",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 156000,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80028"],
    linkedTransactionIds: ["JP-TX-20008"],
    notesSummary: "Sabre NDC order — order document fulfilment in progress.",
  },
  {
    referenceType: "One API Order",
    channel: "One API",
    supplierId: "JP-SU-50007",
    supplierName: "Airblue NDC",
    airline: "Airblue",
    externalReference: "IATI-AB-33017",
    bookingId: "JP-BK-10010",
    customerId: "JP-CU-40010",
    customerName: "Hina Sheikh",
    agentId: null,
    agentName: null,
    lifecycleStatus: "Pending Supplier",
    fulfilmentStatus: "Pending",
    paymentStatus: "Pending",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Not Eligible",
    queueReviewStatus: "None",
    origin: "KHI",
    destination: "ISB",
    departureDate: "2026-03-15",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 28500,
    ticketingDeadline: null,
    linkedTicketIds: [],
    linkedTransactionIds: [],
    notesSummary: "One API domestic order awaiting supplier confirmation.",
  },
  {
    referenceType: "Manual Reference",
    channel: "Manual",
    supplierId: "JP-SU-50004",
    supplierName: "Turkish Airlines",
    airline: "Turkish Airlines",
    externalReference: "MAN-TK-77881",
    bookingId: "JP-BK-10012",
    customerId: "JP-CU-40012",
    customerName: "Kamran Javed",
    agentId: "JP-AG-60004",
    agentName: "Islamabad City Desk",
    lifecycleStatus: "On Hold",
    fulfilmentStatus: "Not Required",
    paymentStatus: "Unpaid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Not Applicable",
    queueReviewStatus: "Review Required",
    origin: "LHE",
    destination: "IST",
    departureDate: "2026-06-20",
    returnDate: "2026-06-27",
    tripType: "return",
    travellerCount: 3,
    bookingValue: 445000,
    ticketingDeadline: "2026-06-01",
    linkedTicketIds: [],
    linkedTransactionIds: [],
    notesSummary: "Manual hold reference — no automated supplier sync.",
  },
  {
    referenceType: "NDC Order",
    channel: "Mock",
    supplierId: "JP-SU-50002",
    supplierName: "Duffel",
    airline: "Saudia",
    externalReference: "MOCK-NDC-SV-1209",
    bookingId: "JP-BK-10014",
    customerId: "JP-CU-40014",
    customerName: "Nadia Farooq",
    agentId: "JP-AG-60001",
    agentName: "Lahore Central Travel",
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Fulfilled",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "None",
    origin: "KHI",
    destination: "JED",
    departureDate: "2026-02-28",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 2,
    bookingValue: 198000,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80029"],
    linkedTransactionIds: ["JP-TX-20014"],
    notesSummary: "Mock NDC channel preview record for training scenarios.",
  },
  {
    referenceType: "One API Order",
    channel: "One API",
    supplierId: "JP-SU-50008",
    supplierName: "Flydubai",
    airline: "Flydubai",
    externalReference: "IATI-FZ-55201",
    bookingId: "JP-BK-10016",
    customerId: "JP-CU-40016",
    customerName: "Saad Mehmood",
    agentId: "JP-AG-60002",
    agentName: "Karachi North Agency",
    lifecycleStatus: "Active",
    fulfilmentStatus: "Fulfilled",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Supplier Review Required",
    queueReviewStatus: "None",
    origin: "LHE",
    destination: "DXB",
    departureDate: "2026-05-18",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 89500,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80030"],
    linkedTransactionIds: ["JP-TX-20016"],
    notesSummary: "One API Flydubai order — distinct from GDS PNR workflow.",
  },
  {
    referenceType: "Manual Reference",
    channel: "Manual",
    supplierId: "JP-SU-50005",
    supplierName: "Saudia",
    airline: "Saudia",
    externalReference: "MAN-SV-90331",
    bookingId: "JP-BK-10018",
    customerId: "JP-CU-40018",
    customerName: "Amir Raza",
    agentId: null,
    agentName: null,
    lifecycleStatus: "Partially Confirmed",
    fulfilmentStatus: "Pending",
    paymentStatus: "Partially Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Unknown",
    queueReviewStatus: "Supplier Queue",
    origin: "ISB",
    destination: "JED",
    departureDate: "2026-07-04",
    returnDate: "2026-07-11",
    tripType: "return",
    travellerCount: 4,
    bookingValue: 620000,
    ticketingDeadline: "2026-06-20",
    linkedTicketIds: [],
    linkedTransactionIds: ["JP-TX-20018"],
    notesSummary: "Manual group reference under operations review.",
  },
  {
    referenceType: "NDC Order",
    channel: "Sabre NDC",
    supplierId: "JP-SU-50002",
    supplierName: "Duffel",
    airline: "Emirates",
    externalReference: "NDC-EK-44102",
    bookingId: "JP-BK-10020",
    customerId: "JP-CU-40020",
    customerName: "Tariq Ahmed",
    agentId: "JP-AG-60004",
    agentName: "Islamabad City Desk",
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Failed",
    paymentStatus: "Refunded",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Already Cancelled",
    queueReviewStatus: "None",
    origin: "KHI",
    destination: "DXB",
    departureDate: "2026-03-08",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 175000,
    ticketingDeadline: null,
    linkedTicketIds: [],
    linkedTransactionIds: ["JP-TX-20020"],
    notesSummary: "Failed NDC fulfilment — refunded in preview ledger.",
  },
  {
    referenceType: "GDS PNR",
    channel: "Mock",
    supplierId: "JP-SU-50001",
    supplierName: "Sabre",
    airline: "Qatar Airways",
    externalReference: "MOCK-QR-8812",
    bookingId: "JP-BK-10022",
    customerId: "JP-CU-40022",
    customerName: "Farah Noor",
    agentId: "JP-AG-60003",
    agentName: "Karachi South Partners",
    lifecycleStatus: "Active",
    fulfilmentStatus: "Not Required",
    paymentStatus: "Paid",
    ticketingStatus: "Ready for Ticketing",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "Ticketing Queue",
    origin: "LHE",
    destination: "DOH",
    departureDate: "2026-08-12",
    returnDate: "2026-08-19",
    tripType: "return",
    travellerCount: 2,
    bookingValue: 388000,
    ticketingDeadline: "2026-07-25",
    linkedTicketIds: [],
    linkedTransactionIds: ["JP-TX-20022"],
    notesSummary: "Mock GDS channel record for sandbox training — not live Sabre.",
  },
  {
    referenceType: "One API Order",
    channel: "One API",
    supplierId: "JP-SU-50009",
    supplierName: "Etihad",
    airline: "Etihad Airways",
    externalReference: "IATI-EY-22045",
    bookingId: "JP-BK-10024",
    customerId: "JP-CU-40024",
    customerName: "Imran Qureshi",
    agentId: null,
    agentName: null,
    lifecycleStatus: "Expired",
    fulfilmentStatus: "Failed",
    paymentStatus: "Unpaid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Not Eligible",
    queueReviewStatus: "None",
    origin: "ISB",
    destination: "AUH",
    departureDate: "2026-01-15",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 92000,
    ticketingDeadline: null,
    linkedTicketIds: [],
    linkedTransactionIds: [],
    notesSummary: "Expired One API order — payment never completed.",
  },
  {
    referenceType: "Manual Reference",
    channel: "Manual",
    supplierId: "JP-SU-50010",
    supplierName: "Gulf Air",
    airline: "Gulf Air",
    externalReference: "MAN-GF-11007",
    bookingId: "JP-BK-10003",
    customerId: "JP-CU-40003",
    customerName: "Fatima Raza",
    agentId: null,
    agentName: null,
    lifecycleStatus: "Failed",
    fulfilmentStatus: "Failed",
    paymentStatus: "Unpaid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Not Applicable",
    queueReviewStatus: "Review Required",
    origin: "ISB",
    destination: "BAH",
    departureDate: "2026-02-01",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 3,
    bookingValue: 245000,
    ticketingDeadline: null,
    linkedTicketIds: [],
    linkedTransactionIds: [],
    notesSummary: "Failed manual reference linked to unsuccessful booking attempt.",
  },
  {
    referenceType: "NDC Order",
    channel: "Sabre NDC",
    supplierId: "JP-SU-50002",
    supplierName: "Duffel",
    airline: "British Airways",
    externalReference: "NDC-BA-66012",
    bookingId: "JP-BK-10007",
    customerId: "JP-CU-40007",
    customerName: "Sana Tariq",
    agentId: "JP-AG-60001",
    agentName: "Lahore Central Travel",
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Fulfilled",
    paymentStatus: "Paid",
    ticketingStatus: "Not Applicable",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "None",
    origin: "LHE",
    destination: "LHR",
    departureDate: "2026-09-10",
    returnDate: "2026-09-24",
    tripType: "return",
    travellerCount: 2,
    bookingValue: 725000,
    ticketingDeadline: null,
    linkedTicketIds: ["JP-TK-80031", "JP-TK-80032"],
    linkedTransactionIds: ["JP-TX-20007"],
    notesSummary: "High-value NDC return order with multiple fulfilment documents.",
  },
  {
    referenceType: "GDS PNR",
    channel: "Sabre GDS",
    supplierId: "JP-SU-50001",
    supplierName: "Sabre",
    airline: "Oman Air",
    externalReference: "WY9K2P",
    bookingId: "JP-BK-10011",
    customerId: "JP-CU-40011",
    customerName: "Rabia Ansari",
    agentId: "JP-AG-60002",
    agentName: "Karachi North Agency",
    lifecycleStatus: "Confirmed",
    fulfilmentStatus: "Not Required",
    paymentStatus: "Paid",
    ticketingStatus: "Ticketing Blocked",
    cancellationEligibility: "Eligible",
    queueReviewStatus: "Ticketing Queue",
    origin: "KHI",
    destination: "MCT",
    departureDate: "2026-10-05",
    returnDate: null,
    tripType: "one_way",
    travellerCount: 1,
    bookingValue: 112000,
    ticketingDeadline: "2026-09-15",
    linkedTicketIds: [],
    linkedTransactionIds: ["JP-TX-20011"],
    notesSummary: "Sabre GDS PNR with blocked ticketing — printer designation pending in preview.",
  },
];

function buildExtraPnr(index: number, seed: ExtraPnrSeed): PnrRecord {
  const id = `JP-PN-${String(70026 + index).padStart(5, "0")}`;
  const travellerNames = buildTravellerNames(seed.customerName, seed.travellerCount);

  return {
    id,
    externalReference: seed.externalReference,
    referenceType: seed.referenceType,
    channel: seed.channel,
    supplierId: seed.supplierId,
    supplierName: seed.supplierName,
    airline: seed.airline,
    bookingId: seed.bookingId,
    customerId: seed.customerId,
    customerName: seed.customerName,
    agentId: seed.agentId,
    agentName: seed.agentName,
    travellerCount: seed.travellerCount,
    travellerNames,
    itinerarySummary: `${seed.origin} → ${seed.destination}${seed.returnDate ? " (return)" : ""}`,
    origin: seed.origin,
    destination: seed.destination,
    departureDate: seed.departureDate,
    returnDate: seed.returnDate,
    tripType: seed.tripType,
    cabin: CABINS[(index + 1) % CABINS.length]!,
    lifecycleStatus: seed.lifecycleStatus,
    fulfilmentStatus: seed.fulfilmentStatus,
    paymentStatus: seed.paymentStatus,
    ticketingStatus: seed.ticketingStatus,
    ticketingDeadline: seed.ticketingDeadline,
    cancellationEligibility: seed.cancellationEligibility,
    queueReviewStatus: seed.queueReviewStatus,
    createdDate: `2026-0${(index % 3) + 1}-${String((index % 20) + 1).padStart(2, "0")}`,
    lastModifiedDate: `2026-0${(index % 3) + 2}-${String((index % 20) + 1).padStart(2, "0")}`,
    lastSupplierActivity: `2026-0${(index % 3) + 2}-${String((index % 20) + 1).padStart(2, "0")}T10:00:00Z`,
    linkedTicketIds: seed.linkedTicketIds,
    linkedTransactionIds: seed.linkedTransactionIds,
    bookingValue: seed.bookingValue,
    currency: "PKR",
    notesSummary: seed.notesSummary,
    showGdsTicketingLimitation:
      seed.channel === "Sabre GDS" && seed.ticketingStatus === "Ticketing Blocked",
  };
}

const BOOKING_PNRS = mockBookings.map((_, index) => buildFromBooking(index));
const EXTRA_RECORDS = EXTRA_PNRS.map((seed, index) => buildExtraPnr(index, seed));

export const mockPnrs: PnrRecord[] = [...BOOKING_PNRS, ...EXTRA_RECORDS];

export const PNR_FIXTURE_COUNT = mockPnrs.length;

export function getPnrById(id: string): PnrRecord | undefined {
  return mockPnrs.find((pnr) => pnr.id === id);
}
