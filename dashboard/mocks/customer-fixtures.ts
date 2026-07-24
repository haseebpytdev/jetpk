import { mockBookings } from "@/mocks/booking-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import type {
  AccountStatus,
  CustomerRecord,
  CustomerType,
  PreferredContactMethod,
  TravellerProfile,
  VerificationStatus,
} from "@/types/customer";

const CITIES = [
  "Karachi",
  "Lahore",
  "Islamabad",
  "Rawalpindi",
  "Faisalabad",
  "Multan",
  "Peshawar",
  "Quetta",
  "Sialkot",
  "Hyderabad",
] as const;

const CUSTOMER_TYPES: CustomerType[] = [
  "Individual",
  "Family",
  "Corporate Contact",
  "Agent-Referred",
];

const ACCOUNT_STATUSES: AccountStatus[] = ["Active", "Inactive", "Suspended", "Review Required"];

const VERIFICATION_STATUSES: VerificationStatus[] = [
  "Verified",
  "Pending",
  "Incomplete",
  "Not Required",
];

const CONTACT_METHODS: PreferredContactMethod[] = ["email", "phone", "whatsapp"];

const NATIONALITIES = [
  "Pakistani",
  "Pakistani",
  "Pakistani",
  "Pakistani",
  "British Pakistani",
  "UAE Resident",
  "Saudi Resident",
];

function buildTravellers(
  customerId: string,
  primaryName: string,
  count: number,
  nationality: string,
): TravellerProfile[] {
  const travellers: TravellerProfile[] = [];
  const parts = primaryName.split(" ");
  const lastName = parts[parts.length - 1] ?? "Traveller";

  for (let i = 0; i < count; i += 1) {
    const isPrimary = i === 0;
    travellers.push({
      id: `${customerId}-TR-${String(i + 1).padStart(2, "0")}`,
      name: isPrimary ? primaryName : `${["Ahmed", "Sara", "Ali", "Zara", "Omar"][i % 5]} ${lastName}`,
      ageGroup: i === count - 1 && count > 1 ? "Child" : "Adult",
      gender: i % 2 === 0 ? "Female" : "Male",
      nationality,
      passportStatus: i % 4 === 0 ? "Pending" : i % 5 === 0 ? "On file" : "Not required",
      frequentTraveller: isPrimary && count > 1,
      relationshipToPrimary: isPrimary ? "Primary account holder" : ["Spouse", "Child", "Parent"][i % 3],
    });
  }
  return travellers;
}

function transactionsForBookings(bookingIds: string[]): string[] {
  return mockTransactions
    .filter((tx) => bookingIds.includes(tx.bookingId))
    .map((tx) => tx.transactionId);
}

function buildCustomerFromBooking(index: number): CustomerRecord {
  const booking = mockBookings[index];
  const id = `JP-CU-${String(40001 + index).padStart(5, "0")}`;
  const city = CITIES[index % CITIES.length];
  const nationality = NATIONALITIES[index % NATIONALITIES.length];
  const travellerCount = Math.max(1, booking.passengerCount);
  const travellers = buildTravellers(id, booking.customerName, travellerCount, nationality);

  const completed =
    booking.bookingStatus === "confirmed" || booking.bookingStatus === "pending" ? 1 : 0;
  const cancelled = booking.bookingStatus === "cancelled" ? 1 : 0;
  const refundTotal =
    mockTransactions
      .filter((tx) => tx.bookingId === booking.id && tx.transactionType === "refund")
      .reduce((sum, tx) => sum + tx.grossAmount, 0) ?? 0;

  const txDates = mockTransactions
    .filter((tx) => tx.bookingId === booking.id)
    .map((tx) => tx.transactionDate)
    .sort();
  const lastPaymentDate = txDates.length > 0 ? txDates[txDates.length - 1]! : null;

  return {
    id,
    fullName: booking.customerName,
    email: booking.customerEmail,
    phone: booking.customerPhone,
    city,
    country: "Pakistan",
    nationality,
    customerType: CUSTOMER_TYPES[index % CUSTOMER_TYPES.length]!,
    accountStatus: index % 11 === 0 ? "Suspended" : index % 7 === 0 ? "Review Required" : "Active",
    verificationStatus: VERIFICATION_STATUSES[index % VERIFICATION_STATUSES.length]!,
    travellerCount,
    travellers,
    bookingCount: 1,
    completedBookingCount: completed,
    cancelledBookingCount: cancelled,
    totalBookedValue: booking.totalAmount,
    totalPaid: booking.amountPaid,
    outstandingBalance: Math.max(0, booking.totalAmount - booking.amountPaid),
    refundTotal,
    lastBookingDate: booking.bookingDate,
    lastPaymentDate,
    createdDate: `2025-${String((index % 12) + 1).padStart(2, "0")}-${String((index % 28) + 1).padStart(2, "0")}`,
    preferredContactMethod: CONTACT_METHODS[index % CONTACT_METHODS.length]!,
    notesSummary: `Preview customer linked to booking ${booking.id}. ${booking.agentOrSource}.`,
    linkedBookingIds: [booking.id],
    linkedTransactionIds: transactionsForBookings([booking.id]),
    currency: booking.currency,
  };
}

/** Extra standalone customers without bookings — deterministic preview data. */
const EXTRA_CUSTOMERS: Omit<
  CustomerRecord,
  "linkedBookingIds" | "linkedTransactionIds" | "bookingCount" | "completedBookingCount" | "cancelledBookingCount" | "totalBookedValue" | "totalPaid" | "outstandingBalance" | "refundTotal" | "lastBookingDate" | "lastPaymentDate"
>[] = [
  {
    id: "JP-CU-40026",
    fullName: "Rashid Mehmood",
    email: "rashid.mehmood@example.com",
    phone: "+92 345 1122334",
    city: "Karachi",
    country: "Pakistan",
    nationality: "Pakistani",
    customerType: "Individual",
    accountStatus: "Active",
    verificationStatus: "Verified",
    travellerCount: 1,
    travellers: buildTravellers("JP-CU-40026", "Rashid Mehmood", 1, "Pakistani"),
    createdDate: "2026-01-15",
    preferredContactMethod: "email",
    notesSummary: "Registered via web — no bookings yet.",
    currency: "PKR",
  },
  {
    id: "JP-CU-40027",
    fullName: "Saima Bukhari",
    email: "saima.bukhari@example.com",
    phone: "+92 312 9988776",
    city: "Lahore",
    country: "Pakistan",
    nationality: "Pakistani",
    customerType: "Family",
    accountStatus: "Active",
    verificationStatus: "Pending",
    travellerCount: 3,
    travellers: buildTravellers("JP-CU-40027", "Saima Bukhari", 3, "Pakistani"),
    createdDate: "2026-01-18",
    preferredContactMethod: "whatsapp",
    notesSummary: "Family account — profile complete, awaiting first booking.",
    currency: "PKR",
  },
  {
    id: "JP-CU-40028",
    fullName: "Corporate Travel Desk",
    email: "travel@techcorp-pk.example.com",
    phone: "+92 42 35551234",
    city: "Lahore",
    country: "Pakistan",
    nationality: "Pakistani",
    customerType: "Corporate Contact",
    accountStatus: "Active",
    verificationStatus: "Verified",
    travellerCount: 2,
    travellers: buildTravellers("JP-CU-40028", "Corporate Travel Desk", 2, "Pakistani"),
    createdDate: "2025-11-20",
    preferredContactMethod: "email",
    notesSummary: "B2B corporate contact — invoicing on net-30 terms.",
    currency: "PKR",
  },
  {
    id: "JP-CU-40029",
    fullName: "Khalid Ansari",
    email: "khalid.ansari@example.com",
    phone: "+92 333 4455661",
    city: "Islamabad",
    country: "Pakistan",
    nationality: "Pakistani",
    customerType: "Agent-Referred",
    accountStatus: "Inactive",
    verificationStatus: "Incomplete",
    travellerCount: 1,
    travellers: buildTravellers("JP-CU-40029", "Khalid Ansari", 1, "Pakistani"),
    createdDate: "2025-09-05",
    preferredContactMethod: "phone",
    notesSummary: "Referred by Lahore Central agent — account dormant.",
    currency: "PKR",
  },
  {
    id: "JP-CU-40030",
    fullName: "Nadia Farooq",
    email: "nadia.farooq@example.com",
    phone: "+92 300 7766554",
    city: "Multan",
    country: "Pakistan",
    nationality: "Pakistani",
    customerType: "Individual",
    accountStatus: "Review Required",
    verificationStatus: "Pending",
    travellerCount: 2,
    travellers: buildTravellers("JP-CU-40030", "Nadia Farooq", 2, "Pakistani"),
    createdDate: "2026-02-01",
    preferredContactMethod: "whatsapp",
    notesSummary: "New registration flagged for routine review.",
    currency: "PKR",
  },
];

function finalizeExtraCustomer(
  partial: (typeof EXTRA_CUSTOMERS)[number],
): CustomerRecord {
  return {
    ...partial,
    bookingCount: 0,
    completedBookingCount: 0,
    cancelledBookingCount: 0,
    totalBookedValue: 0,
    totalPaid: 0,
    outstandingBalance: 0,
    refundTotal: 0,
    lastBookingDate: null,
    lastPaymentDate: null,
    linkedBookingIds: [],
    linkedTransactionIds: [],
  };
}

/** Deterministic preview customers — not production data. */
export const mockCustomers: CustomerRecord[] = [
  ...mockBookings.map((_, index) => buildCustomerFromBooking(index)),
  ...EXTRA_CUSTOMERS.map(finalizeExtraCustomer),
];

export function getCustomerById(id: string): CustomerRecord | undefined {
  return mockCustomers.find((c) => c.id === id);
}

export const CUSTOMER_FIXTURE_COUNT = mockCustomers.length;
