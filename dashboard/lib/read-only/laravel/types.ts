import type { BookingsPageResult } from "@/types/booking";
import type { CustomerRecord } from "@/types/customer";
import type { TransactionRecord } from "@/types/payment";
import type { OverviewData } from "@/types/dashboard";

export type LaravelSessionPayload = {
  id: string;
  displayName: string;
  email: string | null;
  roles: string[];
  permissions: string[];
  accountType: string;
  accountStatus: string;
  staffType: string | null;
  portalType?: string;
  platformRole?: string;
  sessionUsable?: boolean;
  denialReason?: string | null;
  requiresPasswordChange?: boolean;
  requiresEmailVerification?: boolean;
  landingRoute?: string;
  navigation?: Array<{ label: string; href: string; key: string }>;
  capabilities?: Record<string, boolean>;
  schemaVersion: string;
  generatedAt: string;
};

export type LaravelOverviewPayload = {
  hasLiveData: boolean;
  referenceTime: string;
  summaryStats: Array<{ key: string; label: string; value: string; delta: string; tone: string }>;
  operationalQueues: Array<{
    key: string;
    label: string;
    count: number;
    helper: string;
    laravelRoute: string;
    queue: string | null;
    tone: string;
    cta: string;
  }>;
  recentBookings: OverviewData["recentBookings"];
  bookingPipeline: Array<{
    key: string;
    label: string;
    count: number;
    laravelRoute: string;
    queue: string | null;
  }>;
  paymentOperations: Array<{
    key: string;
    label: string;
    count: number;
    laravelRoute: string;
    queue?: string | null;
  }>;
  supportOperations: Array<{
    key: string;
    label: string;
    count: number;
    laravelRoute: string;
    queue?: string | null;
    helper?: string;
  }>;
  supplierStatus: OverviewData["supplierStatus"];
  systemHealth: OverviewData["systemHealth"];
  operationalCounts: Record<string, number>;
  failedNotifications: number;
  supplierFailures: number;
  accountType: string;
};

export type LaravelBookingsListPayload = {
  bookings: import("@/types/booking").BookingRecord[];
  summary: BookingsPageResult["summary"];
  facets: { suppliers: string[]; airlines: string[] };
};

export type LaravelPaymentsListPayload = {
  transactions: TransactionRecord[];
  summary: {
    totalDisplayed: number;
    grossTotal: number;
    paidTotal: number;
    outstandingTotal: number;
    currency: string;
  };
};

export type LaravelCustomersListPayload = {
  customers: CustomerRecord[];
  summary: {
    totalDisplayed: number;
    active: number;
    verified: number;
    withBookings: number;
  };
};

export type LaravelSuppliersListPayload = {
  suppliers: import("@/types/supplier").SupplierRecord[];
  summary: import("@/types/supplier").SuppliersSummaryMetrics;
  facets: import("@/types/supplier").SuppliersPageResult["facets"];
};

export type LaravelAgentsListPayload = {
  agents: import("@/types/agent").AgentRecord[];
  summary: Record<string, unknown>;
  facets: Record<string, unknown>;
};

export type LaravelPnrsListPayload = {
  pnrs: import("@/types/pnr").PnrRecord[];
  summary: Record<string, unknown>;
  facets: Record<string, unknown>;
};

export type LaravelTicketsListPayload = {
  tickets: import("@/types/ticket").TicketRecord[];
  summary: Record<string, unknown>;
  facets: Record<string, unknown>;
};

export type LaravelReportPayload = {
  section: string;
  currency: string;
  referenceTime: string;
  hasLiveData: boolean;
  metrics: Array<Record<string, unknown>>;
  tableRows: Array<Record<string, unknown>>;
  warnings: Array<{ code: string; message: string }>;
};

export type LaravelCmsPagesListPayload = {
  pages: Array<Record<string, unknown>>;
  summary: { totalDisplayed: number; published: number; draft: number };
  facets: Record<string, unknown>;
};

export type LaravelUsersListPayload = {
  users: import("@/types/users").UserTableRow[];
  summary: import("@/types/users").UsersSummaryMetrics;
};

export type LaravelRolesListPayload = {
  roles: import("@/types/roles").RoleTableRow[];
  summary: import("@/types/roles").RolesSummaryMetrics;
};

export type LaravelPermissionsListPayload = {
  permissions: import("@/types/permissions").PermissionTableRow[];
  summary: import("@/types/permissions").PermissionsSummaryMetrics;
};

export type LaravelRbacMatrixPayload = {
  roles: import("@/types/roles").RoleTableRow[];
  permissionKeys: string[];
  assignments: Record<string, Record<string, boolean>>;
  protectedRoleMetadata: Array<{ roleId: string; isProtected: boolean }>;
  highRiskMarkers: string[];
  scopeContext: string;
  channelContext: string;
};

export type LaravelSettingsPayload = Record<string, unknown>;

export type LaravelAuditListPayload = {
  events: import("@/types/audit").AuditTableRow[];
  summary: import("@/types/audit").AuditSummaryMetrics;
};
