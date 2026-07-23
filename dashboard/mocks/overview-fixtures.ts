import type { ActionCard, BookingRow, NotificationItem, StatCard, SystemHealthItem } from "@/types/dashboard";

export const mockUser = {
  name: "Preview Admin",
  role: "Super Admin",
  email: "preview@jetpakistan.pk",
  initials: "PA",
  online: true,
};

export const summaryStats: StatCard[] = [
  { key: "total_bookings", label: "Total Bookings", value: "1,248", delta: "+12.5%", tone: "up" },
  { key: "total_revenue", label: "Total Revenue", value: "PKR 24.8M", delta: "+18.3%", tone: "up" },
  { key: "pending_payments", label: "Pending Payments", value: "156", delta: "+5.6%", tone: "warn" },
  { key: "tickets_issued", label: "Tickets Issued", value: "987", delta: "+15.2%", tone: "up" },
  { key: "cancellations", label: "Cancellations", value: "23", delta: "-2.1%", tone: "down" },
  { key: "active_customers", label: "Active Customers", value: "3,456", delta: "+20.4%", tone: "up" },
];

export const operationalActionCards: ActionCard[] = [
  {
    key: "pending_deposits",
    label: "Pending Deposits",
    count: 12,
    helper: "Agency funds waiting approval.",
    laravelRoute: "admin.agent-deposits.index",
    tone: "amber",
    cta: "Review Deposits",
  },
  {
    key: "agency_applications",
    label: "Agency Applications",
    count: 4,
    helper: "New agent sign-ups awaiting review.",
    laravelRoute: "admin.agent-applications.index",
    tone: "violet",
    cta: "Review",
  },
  {
    key: "payment_review",
    label: "Payment Review",
    count: 156,
    helper: "Unpaid or partial balances.",
    laravelRoute: "admin.bookings",
    queue: "payment_review",
    tone: "emerald",
    cta: "Review",
  },
  {
    key: "supplier_pnr_pending",
    label: "Supplier / PNR Pending",
    count: 38,
    helper: "Paid bookings awaiting a PNR.",
    laravelRoute: "admin.bookings",
    queue: "supplier_pnr",
    tone: "blue",
    cta: "View",
  },
  {
    key: "manual_review",
    label: "Manual Review",
    count: 9,
    helper: "Supplier failures needing staff review.",
    laravelRoute: "admin.bookings",
    queue: "supplier_pnr",
    tone: "amber",
    cta: "Review",
  },
  {
    key: "ticketing_pending",
    label: "Ticketing Pending",
    count: 42,
    helper: "Ready for ticket issuance.",
    laravelRoute: "admin.bookings",
    queue: "ticketing",
    tone: "emerald",
    cta: "Queue",
  },
  {
    key: "cancellations_pending",
    label: "Cancellation Requests",
    count: 7,
    helper: "Open cancellation workflows.",
    laravelRoute: "admin.bookings",
    queue: "cancellations",
    tone: "amber",
    cta: "View",
  },
  {
    key: "refunds_pending",
    label: "Refund Requests",
    count: 11,
    helper: "Awaiting approval or payout.",
    laravelRoute: "admin.bookings",
    queue: "refunds",
    tone: "red",
    cta: "View",
  },
  {
    key: "failed_notifications",
    label: "Failed Notifications",
    count: 3,
    helper: "Delivery failures in comms log.",
    laravelRoute: "admin.settings.communications.delivery-log.index",
    tone: "red",
    cta: "Inspect",
  },
  {
    key: "supplier_failures",
    label: "Supplier Failures",
    count: 5,
    helper: "Recent supplier errors (7d).",
    laravelRoute: "admin.bookings",
    queue: "manual_review",
    tone: "red",
    cta: "Triage",
  },
];

export const shortcutActions = [
  { label: "Review Deposits", laravelRoute: "admin.agent-deposits.index" },
  { label: "Approve Agencies", laravelRoute: "admin.agent-applications.index" },
  { label: "Payment Review", laravelRoute: "admin.bookings", queue: "payment_review" },
  { label: "Ticketing Queue", laravelRoute: "admin.bookings", queue: "ticketing" },
  { label: "Manual Review", laravelRoute: "admin.bookings", queue: "supplier_pnr" },
  { label: "Reports", laravelRoute: "admin.reports" },
  { label: "API Settings", laravelRoute: "admin.api-settings" },
];

export const bookingTrend = [
  { day: "Mon", bookings: 142, revenue: 2.1 },
  { day: "Tue", bookings: 168, revenue: 2.4 },
  { day: "Wed", bookings: 155, revenue: 2.2 },
  { day: "Thu", bookings: 190, revenue: 2.8 },
  { day: "Fri", bookings: 210, revenue: 3.1 },
  { day: "Sat", bookings: 198, revenue: 2.9 },
  { day: "Sun", bookings: 185, revenue: 2.7 },
];

export const statusBreakdown = [
  { name: "Confirmed", value: 678, color: "#10B981" },
  { name: "Ticketed", value: 215, color: "#3B82F6" },
  { name: "Pending Payment", value: 156, color: "#F59E0B" },
  { name: "On Hold", value: 98, color: "#8B5CF6" },
  { name: "Cancelled", value: 101, color: "#EF4444" },
];

export const recentNotifications: NotificationItem[] = [
  { id: "n1", title: "New booking received", detail: "Demo reference JP-2401", time: "2m ago", tone: "info" },
  { id: "n2", title: "Payment received", detail: "PKR 45,200 — demo only", time: "15m ago", tone: "success" },
  { id: "n3", title: "Supplier sync delayed", detail: "Sandbox status", time: "1h ago", tone: "warn" },
];

export const recentBookings: BookingRow[] = [
  {
    id: "b1",
    pnr: "DEMO01",
    customer: "Preview Customer",
    phone: "+92 300 0000000",
    route: "LHE → JED",
    date: "20 Jun 2026",
    status: "Confirmed",
    amount: "PKR 185,400",
    payment: "Paid",
  },
  {
    id: "b2",
    pnr: "DEMO02",
    customer: "Sample Traveller",
    phone: "+92 321 0000000",
    route: "KHI → DXB",
    date: "19 Jun 2026",
    status: "Pending Payment",
    amount: "PKR 92,100",
    payment: "Pending",
  },
  {
    id: "b3",
    pnr: "DEMO03",
    customer: "Agent Booking",
    phone: "+92 333 0000000",
    route: "ISB → LHR",
    date: "18 Jun 2026",
    status: "Ticketed",
    amount: "PKR 412,800",
    payment: "Paid",
  },
];

export const topRoutes = [
  { route: "Lahore → Jeddah", share: 28 },
  { route: "Karachi → Dubai", share: 22 },
  { route: "Islamabad → London", share: 15 },
  { route: "Lahore → Doha", share: 12 },
];

export const systemHealth: SystemHealthItem[] = [
  { name: "Sabre GDS", status: "operational" },
  { name: "PIA NDC", status: "operational" },
  { name: "IATA Pay", status: "operational" },
  { name: "Email delivery", status: "degraded" },
];
