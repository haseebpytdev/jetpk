export type StatCard = {
  key: string;
  label: string;
  value: string;
  delta: string;
  tone: "up" | "down" | "warn";
};

export type ActionCard = {
  key: string;
  label: string;
  count: number;
  helper: string;
  laravelRoute: string;
  queue?: string;
  tone: string;
  cta: string;
};

export type PipelineStage = {
  key: string;
  label: string;
  count: number;
  laravelRoute: string;
  queue?: string;
};

export type SupplierStatusItem = {
  key: string;
  label: string;
  status: string;
  detail: string;
};

export type OperationalSummaryItem = {
  key: string;
  label: string;
  count: number;
  laravelRoute: string;
  queue?: string;
  helper?: string;
};

export type BookingRow = {
  id: string;
  pnr: string;
  customer: string;
  phone: string;
  route: string;
  date: string;
  status: string;
  amount: string;
  payment: string;
};

export type SystemHealthItem = {
  name: string;
  status: "operational" | "degraded" | "down";
};

/** Preview-only notification row (fixture/dev). */
export type NotificationItem = {
  id: string;
  title: string;
  detail: string;
  time: string;
  tone: "info" | "success" | "warn";
};

export type OverviewData = {
  summaryStats: StatCard[];
  operationalActionCards: ActionCard[];
  bookingPipeline: PipelineStage[];
  shortcutActions: { label: string; laravelRoute: string; queue?: string }[];
  recentBookings: BookingRow[];
  paymentOperations: OperationalSummaryItem[];
  supportOperations: OperationalSummaryItem[];
  supplierStatus: SupplierStatusItem[];
  systemHealth: SystemHealthItem[];
  /** Legacy preview chart payloads — omitted in live operational responses. */
  bookingTrend?: { day: string; bookings: number; revenue: number }[];
  statusBreakdown?: { name: string; value: number; color: string }[];
  recentNotifications?: NotificationItem[];
  topRoutes?: { route: string; share: number }[];
};

export type DashboardSearchResult = {
  type: string;
  label: string;
  detail: string;
  href: string;
};
