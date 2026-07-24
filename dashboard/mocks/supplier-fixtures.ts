import { mockBookings } from "@/mocks/booking-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import type {
  CredentialStatus,
  IntegrationStatus,
  OperationalStatus,
  SettlementStatus,
  SupplierCategory,
  SupplierRecord,
} from "@/types/supplier";

type SupplierSeed = {
  id: string;
  supplierName: string;
  displayCode: string;
  supplierCategory: SupplierCategory;
  operatingRegion: string;
  operationalStatus: OperationalStatus;
  integrationStatus: IntegrationStatus;
  credentialStatus: CredentialStatus;
  settlementStatus: SettlementStatus;
  createdDate: string;
  supportContact: string;
  escalationContact: string;
  notesSummary: string;
  matchBookingSupplier?: string;
  matchAirline?: string;
};

const SUPPLIER_SEEDS: SupplierSeed[] = [
  {
    id: "JP-SU-50001",
    supplierName: "Sabre",
    displayCode: "SBR",
    supplierCategory: "GDS",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-03-15",
    supportContact: "gds-support@sabre-preview.example.com",
    escalationContact: "gds-escalation@sabre-preview.example.com",
    notesSummary: "Primary GDS for international fares — mock preview integration.",
    matchBookingSupplier: "Sabre",
  },
  {
    id: "JP-SU-50002",
    supplierName: "Duffel",
    displayCode: "DFL",
    supplierCategory: "NDC",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-06-01",
    supportContact: "support@duffel-preview.example.com",
    escalationContact: "escalation@duffel-preview.example.com",
    notesSummary: "NDC aggregator for select carriers — mock preview only.",
    matchBookingSupplier: "Duffel",
  },
  {
    id: "JP-SU-50003",
    supplierName: "Emirates",
    displayCode: "EK",
    supplierCategory: "Airline",
    operatingRegion: "Middle East",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-01-10",
    supportContact: "agency-support@emirates-preview.example.com",
    escalationContact: "agency-escalation@emirates-preview.example.com",
    notesSummary: "Emirates direct connect — settlement via BSP.",
    matchAirline: "Emirates",
  },
  {
    id: "JP-SU-50004",
    supplierName: "Turkish Airlines",
    displayCode: "TK",
    supplierCategory: "Airline",
    operatingRegion: "Europe / Middle East",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Due",
    createdDate: "2024-02-20",
    supportContact: "agency@turkish-preview.example.com",
    escalationContact: "agency-escalation@turkish-preview.example.com",
    notesSummary: "Turkish Airlines agency portal — weekly settlement cycle.",
    matchAirline: "Turkish Airlines",
  },
  {
    id: "JP-SU-50005",
    supplierName: "Saudia",
    displayCode: "SV",
    supplierCategory: "Airline",
    operatingRegion: "Middle East",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Expiring Soon",
    settlementStatus: "Current",
    createdDate: "2024-04-05",
    supportContact: "support@saudia-preview.example.com",
    escalationContact: "escalation@saudia-preview.example.com",
    notesSummary: "Saudia agency feed — credential renewal due in preview window.",
    matchAirline: "Saudia",
  },
  {
    id: "JP-SU-50006",
    supplierName: "British Airways",
    displayCode: "BA",
    supplierCategory: "Airline",
    operatingRegion: "Europe",
    operationalStatus: "Active",
    integrationStatus: "Degraded",
    credentialStatus: "Configured",
    settlementStatus: "Reconciliation Required",
    createdDate: "2024-05-12",
    supportContact: "agency@ba-preview.example.com",
    escalationContact: "agency-escalation@ba-preview.example.com",
    notesSummary: "BA NDC channel — intermittent latency in preview simulation.",
    matchAirline: "British Airways",
  },
  {
    id: "JP-SU-50007",
    supplierName: "flydubai",
    displayCode: "FZ",
    supplierCategory: "Airline",
    operatingRegion: "Middle East",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-07-18",
    supportContact: "agency@flydubai-preview.example.com",
    escalationContact: "agency-escalation@flydubai-preview.example.com",
    notesSummary: "flydubai LCC feed — high volume short-haul routes.",
    matchAirline: "flydubai",
  },
  {
    id: "JP-SU-50008",
    supplierName: "Qatar Airways",
    displayCode: "QR",
    supplierCategory: "Airline",
    operatingRegion: "Middle East / Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-08-22",
    supportContact: "agency@qatar-preview.example.com",
    escalationContact: "agency-escalation@qatar-preview.example.com",
    notesSummary: "Qatar Airways premium cabin inventory.",
    matchAirline: "Qatar Airways",
  },
  {
    id: "JP-SU-50009",
    supplierName: "Malaysia Airlines",
    displayCode: "MH",
    supplierCategory: "Airline",
    operatingRegion: "Asia Pacific",
    operationalStatus: "Maintenance",
    integrationStatus: "Disabled",
    credentialStatus: "Invalid",
    settlementStatus: "Not Applicable",
    createdDate: "2024-09-01",
    supportContact: "agency@malaysia-preview.example.com",
    escalationContact: "agency-escalation@malaysia-preview.example.com",
    notesSummary: "Scheduled maintenance window — integration disabled in preview.",
    matchAirline: "Malaysia Airlines",
  },
  {
    id: "JP-SU-50010",
    supplierName: "Oman Air",
    displayCode: "WY",
    supplierCategory: "Airline",
    operatingRegion: "Middle East",
    operationalStatus: "Restricted",
    integrationStatus: "Manual",
    credentialStatus: "Not Required",
    settlementStatus: "Not Applicable",
    createdDate: "2024-10-15",
    supportContact: "agency@omanair-preview.example.com",
    escalationContact: "agency-escalation@omanair-preview.example.com",
    notesSummary: "Manual fallback queue — restricted inventory in preview.",
    matchAirline: "Oman Air",
  },
  {
    id: "JP-SU-50011",
    supplierName: "Amadeus",
    displayCode: "AMD",
    supplierCategory: "GDS",
    operatingRegion: "Global",
    operationalStatus: "Inactive",
    integrationStatus: "Mock Only",
    credentialStatus: "Missing",
    settlementStatus: "Not Applicable",
    createdDate: "2023-12-01",
    supportContact: "support@amadeus-preview.example.com",
    escalationContact: "escalation@amadeus-preview.example.com",
    notesSummary: "Secondary GDS — not yet activated in preview environment.",
  },
  {
    id: "JP-SU-50012",
    supplierName: "Hotelbeds",
    displayCode: "HBD",
    supplierCategory: "Hotel",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-11-05",
    supportContact: "support@hotelbeds-preview.example.com",
    escalationContact: "escalation@hotelbeds-preview.example.com",
    notesSummary: "Hotel bedbank — post-ticketing ancillary upsell channel.",
  },
  {
    id: "JP-SU-50013",
    supplierName: "Booking.com Partner",
    displayCode: "BKG",
    supplierCategory: "Hotel",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Due",
    createdDate: "2025-01-08",
    supportContact: "partners@booking-preview.example.com",
    escalationContact: "partners-escalation@booking-preview.example.com",
    notesSummary: "Hotel partner API — settlement due within preview cycle.",
  },
  {
    id: "JP-SU-50014",
    supplierName: "Careem Transport",
    displayCode: "CRM",
    supplierCategory: "Ground Transport",
    operatingRegion: "Middle East / Pakistan",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2025-02-14",
    supportContact: "b2b@careem-preview.example.com",
    escalationContact: "b2b-escalation@careem-preview.example.com",
    notesSummary: "Airport transfer add-on — per-booking settlement.",
  },
  {
    id: "JP-SU-50015",
    supplierName: "VFS Global",
    displayCode: "VFS",
    supplierCategory: "Visa Service",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Manual",
    credentialStatus: "Not Required",
    settlementStatus: "Current",
    createdDate: "2025-03-01",
    supportContact: "agency@vfs-preview.example.com",
    escalationContact: "agency-escalation@vfs-preview.example.com",
    notesSummary: "Visa appointment facilitation — manual reconciliation.",
  },
  {
    id: "JP-SU-50016",
    supplierName: "Allianz Travel",
    displayCode: "ALZ",
    supplierCategory: "Insurance",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2025-03-20",
    supportContact: "travel@allianz-preview.example.com",
    escalationContact: "travel-escalation@allianz-preview.example.com",
    notesSummary: "Travel insurance upsell — per-policy commission model.",
  },
  {
    id: "JP-SU-50017",
    supplierName: "Blue Ribbon Bags",
    displayCode: "BRB",
    supplierCategory: "Ancillary Service",
    operatingRegion: "Global",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2025-04-10",
    supportContact: "partners@brb-preview.example.com",
    escalationContact: "partners-escalation@brb-preview.example.com",
    notesSummary: "Delayed baggage protection ancillary.",
  },
  {
    id: "JP-SU-50018",
    supplierName: "JazzCash Gateway",
    displayCode: "JCG",
    supplierCategory: "Payment Service",
    operatingRegion: "Pakistan",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2024-01-01",
    supportContact: "merchant@jazzcash-preview.example.com",
    escalationContact: "merchant-escalation@jazzcash-preview.example.com",
    notesSummary: "Domestic payment gateway — T+1 settlement.",
  },
  {
    id: "JP-SU-50019",
    supplierName: "EasyFly Consolidator",
    displayCode: "EFC",
    supplierCategory: "Consolidator",
    operatingRegion: "South Asia",
    operationalStatus: "Review Required",
    integrationStatus: "Degraded",
    credentialStatus: "Expiring Soon",
    settlementStatus: "Overdue",
    createdDate: "2024-08-01",
    supportContact: "ops@easyfly-preview.example.com",
    escalationContact: "ops-escalation@easyfly-preview.example.com",
    notesSummary: "Consolidator contract under review — overdue settlement in preview.",
  },
  {
    id: "JP-SU-50020",
    supplierName: "Travelport",
    displayCode: "TVP",
    supplierCategory: "GDS",
    operatingRegion: "Global",
    operationalStatus: "Inactive",
    integrationStatus: "Disabled",
    credentialStatus: "Missing",
    settlementStatus: "Not Applicable",
    createdDate: "2023-06-15",
    supportContact: "support@travelport-preview.example.com",
    escalationContact: "escalation@travelport-preview.example.com",
    notesSummary: "Legacy GDS — decommissioned in preview environment.",
  },
  {
    id: "JP-SU-50021",
    supplierName: "Airblue NDC",
    displayCode: "ABN",
    supplierCategory: "NDC",
    operatingRegion: "Pakistan",
    operationalStatus: "Active",
    integrationStatus: "Mock Only",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2025-05-01",
    supportContact: "agency@airblue-preview.example.com",
    escalationContact: "agency-escalation@airblue-preview.example.com",
    notesSummary: "Domestic NDC channel — mock-only in preview.",
  },
  {
    id: "JP-SU-50022",
    supplierName: "Serene Air",
    displayCode: "SER",
    supplierCategory: "Airline",
    operatingRegion: "Pakistan",
    operationalStatus: "Active",
    integrationStatus: "Connected",
    credentialStatus: "Configured",
    settlementStatus: "Current",
    createdDate: "2025-06-01",
    supportContact: "agency@serene-preview.example.com",
    escalationContact: "agency-escalation@serene-preview.example.com",
    notesSummary: "Domestic carrier — limited route map in preview.",
  },
];

function linkedBookingsForSeed(seed: SupplierSeed): string[] {
  if (seed.matchBookingSupplier) {
    return mockBookings.filter((b) => b.supplier === seed.matchBookingSupplier).map((b) => b.id);
  }
  if (seed.matchAirline) {
    return mockBookings.filter((b) => b.airline === seed.matchAirline).map((b) => b.id);
  }
  return [];
}

function buildSupplierRecord(seed: SupplierSeed, index: number): SupplierRecord {
  const linkedBookingIds = linkedBookingsForSeed(seed);
  const linkedBookings = mockBookings.filter((b) => linkedBookingIds.includes(b.id));
  const linkedTransactionIds = mockTransactions
    .filter((tx) => linkedBookingIds.includes(tx.bookingId))
    .map((tx) => tx.transactionId);

  const confirmedBookingCount = linkedBookings.filter((b) => b.bookingStatus === "confirmed").length;
  const failedBookingCount = linkedBookings.filter((b) => b.bookingStatus === "failed").length;
  const totalBookingValue = linkedBookings.reduce((sum, b) => sum + b.totalAmount, 0);
  const totalPaidToSupplier = linkedBookings.reduce((sum, b) => sum + b.amountPaid, 0);
  const outstandingSettlement = Math.max(0, totalBookingValue - totalPaidToSupplier);
  const refundExposure = mockTransactions
    .filter(
      (tx) =>
        linkedBookingIds.includes(tx.bookingId) &&
        tx.transactionType === "refund" &&
        tx.transactionStatus === "succeeded",
    )
    .reduce((sum, tx) => sum + tx.grossAmount, 0);

  const bookingDates = linkedBookings.map((b) => b.bookingDate).sort();
  const settlementDates = mockTransactions
    .filter((tx) => linkedBookingIds.includes(tx.bookingId) && tx.transactionStatus === "succeeded")
    .map((tx) => tx.transactionDate)
    .sort();

  return {
    id: seed.id,
    supplierName: seed.supplierName,
    displayCode: seed.displayCode,
    supplierCategory: seed.supplierCategory,
    operatingRegion: seed.operatingRegion,
    operationalStatus: seed.operationalStatus,
    integrationStatus: seed.integrationStatus,
    credentialStatus: seed.credentialStatus,
    settlementStatus: seed.settlementStatus,
    currency: "PKR",
    bookingCount: linkedBookings.length,
    confirmedBookingCount,
    failedBookingCount,
    totalBookingValue,
    totalPaidToSupplier,
    outstandingSettlement: linkedBookings.length > 0 ? outstandingSettlement : index % 3 === 0 ? 125000 : 0,
    refundExposure,
    lastBookingActivity: bookingDates.length > 0 ? bookingDates[bookingDates.length - 1]! : null,
    lastSettlementActivity:
      settlementDates.length > 0 ? settlementDates[settlementDates.length - 1]! : null,
    createdDate: seed.createdDate,
    supportContact: seed.supportContact,
    escalationContact: seed.escalationContact,
    linkedBookingIds,
    linkedTransactionIds,
    notesSummary: seed.notesSummary,
  };
}

/** Deterministic preview suppliers — not production data. */
export const mockSuppliers: SupplierRecord[] = SUPPLIER_SEEDS.map((seed, index) =>
  buildSupplierRecord(seed, index),
);

export function getSupplierById(id: string): SupplierRecord | undefined {
  return mockSuppliers.find((s) => s.id === id);
}

export const SUPPLIER_FIXTURE_COUNT = mockSuppliers.length;
