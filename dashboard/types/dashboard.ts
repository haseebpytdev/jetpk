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

export type NotificationItem = {
  id: string;
  title: string;
  detail: string;
  time: string;
  tone: "info" | "success" | "warn";
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

export type OverviewData = {
  summaryStats: StatCard[];
  operationalActionCards: ActionCard[];
  shortcutActions: { label: string; laravelRoute: string; queue?: string }[];
  bookingTrend: { day: string; bookings: number; revenue: number }[];
  statusBreakdown: { name: string; value: number; color: string }[];
  recentNotifications: NotificationItem[];
  recentBookings: BookingRow[];
  topRoutes: { route: string; share: number }[];
  systemHealth: SystemHealthItem[];
};
