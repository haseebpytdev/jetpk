export type CustomerType = "Individual" | "Family" | "Corporate Contact" | "Agent-Referred";

export type AccountStatus = "Active" | "Inactive" | "Suspended" | "Review Required";

export type VerificationStatus = "Verified" | "Pending" | "Incomplete" | "Not Required";

export type PreferredContactMethod = "email" | "phone" | "whatsapp";

export type TravellerAgeGroup = "Adult" | "Child" | "Infant";

export type PassportStatus = "Not required" | "On file" | "Pending" | "Expired";

export type TravellerProfile = {
  id: string;
  name: string;
  ageGroup: TravellerAgeGroup;
  gender?: "Male" | "Female" | "Not specified";
  nationality: string;
  passportStatus: PassportStatus;
  frequentTraveller: boolean;
  relationshipToPrimary: string;
};

export type CustomerRecord = {
  id: string;
  fullName: string;
  email: string;
  phone: string;
  city: string;
  country: string;
  nationality: string;
  customerType: CustomerType;
  accountStatus: AccountStatus;
  verificationStatus: VerificationStatus;
  travellerCount: number;
  travellers: TravellerProfile[];
  bookingCount: number;
  completedBookingCount: number;
  cancelledBookingCount: number;
  totalBookedValue: number;
  totalPaid: number;
  outstandingBalance: number;
  refundTotal: number;
  lastBookingDate: string | null;
  lastPaymentDate: string | null;
  createdDate: string;
  preferredContactMethod: PreferredContactMethod;
  notesSummary: string;
  linkedBookingIds: string[];
  linkedTransactionIds: string[];
  currency: string;
};

export type CustomerSortField =
  | "name"
  | "newest"
  | "oldest"
  | "bookingCount"
  | "totalBookedValue"
  | "totalPaid"
  | "outstandingBalance"
  | "lastBookingDate";

export type SortDirection = "asc" | "desc";

export type CustomersQuery = {
  q: string;
  accountStatus: AccountStatus | "all";
  verificationStatus: VerificationStatus | "all";
  customerType: CustomerType | "all";
  city: string;
  country: string;
  hasOutstandingBalance: "all" | "yes" | "no";
  hasBookings: "all" | "yes" | "no";
  activityFrom: string;
  activityTo: string;
  page: number;
  pageSize: number;
  sort: CustomerSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
  previewLoading: boolean;
};

export type CustomersSummaryMetrics = {
  totalCustomers: number;
  activeCustomers: number;
  totalTravellers: number;
  customersWithOutstanding: number;
  totalLifetimeValue: number;
  recentCustomers: number;
  currency: string;
};

export type CustomersPageResult = {
  customers: CustomerRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: CustomersSummaryMetrics;
  facets: {
    cities: string[];
    countries: string[];
    customerTypes: CustomerType[];
  };
};
