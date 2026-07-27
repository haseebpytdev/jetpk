import type { ActionType, Permission, PermissionGroup } from "@/types/access-control";

type PermissionSeed = {
  key: string;
  domain: PermissionGroup;
  action: ActionType;
  label: string;
  description: string;
  risk: Permission["risk"];
  isHighRisk?: boolean;
  prerequisiteKey?: string | null;
  channelAware?: boolean;
  laravelPolicyHint: string;
};

const HIGH_RISK_KEYS = new Set([
  "bookings.cancel.approve",
  "payments.refund.approve",
  "pnrs.cancel.approve",
  "tickets.issue.approve",
  "users.assignRoles",
  "roles.assignPermissions",
  "settings.update",
  "cms.publish.approve",
  "users.suspend",
  "audit.export",
]);

const PERMISSION_SEEDS: PermissionSeed[] = [
  { key: "dashboard.view", domain: "dashboard", action: "view", label: "View dashboard", description: "Access the operations dashboard overview.", risk: "standard", laravelPolicyHint: "Gate::define('dashboard.view')" },

  { key: "bookings.view", domain: "bookings", action: "view", label: "View bookings", description: "View booking records and summaries.", risk: "standard", laravelPolicyHint: "BookingPolicy::view" },
  { key: "bookings.create", domain: "bookings", action: "create", label: "Create bookings", description: "Create new booking records.", risk: "elevated", laravelPolicyHint: "BookingPolicy::create" },
  { key: "bookings.update", domain: "bookings", action: "update", label: "Update bookings", description: "Modify booking details.", risk: "elevated", laravelPolicyHint: "BookingPolicy::update" },
  { key: "bookings.cancel.request", domain: "bookings", action: "request", label: "Request booking cancellation", description: "Submit booking cancellation requests.", risk: "elevated", laravelPolicyHint: "BookingPolicy::cancelRequest" },
  { key: "bookings.cancel.approve", domain: "bookings", action: "approve", label: "Approve booking cancellation", description: "Approve or reject booking cancellation requests.", risk: "high", isHighRisk: true, prerequisiteKey: "bookings.cancel.request", laravelPolicyHint: "BookingPolicy::cancelApprove" },

  { key: "payments.view", domain: "payments", action: "view", label: "View payments", description: "View payment ledger and transactions.", risk: "standard", laravelPolicyHint: "PaymentPolicy::view" },
  { key: "payments.record", domain: "payments", action: "create", label: "Record payments", description: "Record payment entries.", risk: "elevated", laravelPolicyHint: "PaymentPolicy::record" },
  { key: "payments.reconcile", domain: "payments", action: "manage", label: "Reconcile payments", description: "Reconcile payment records.", risk: "elevated", laravelPolicyHint: "PaymentPolicy::reconcile" },
  { key: "payments.refund.request", domain: "payments", action: "request", label: "Request payment refund", description: "Submit refund requests.", risk: "elevated", laravelPolicyHint: "PaymentPolicy::refundRequest" },
  { key: "payments.refund.approve", domain: "payments", action: "approve", label: "Approve payment refund", description: "Approve or reject refund requests.", risk: "high", isHighRisk: true, prerequisiteKey: "payments.refund.request", laravelPolicyHint: "PaymentPolicy::refundApprove" },

  { key: "customers.view", domain: "customers", action: "view", label: "View customers", description: "View customer profiles.", risk: "standard", laravelPolicyHint: "CustomerPolicy::view" },
  { key: "customers.update", domain: "customers", action: "update", label: "Update customers", description: "Modify customer records.", risk: "elevated", laravelPolicyHint: "CustomerPolicy::update" },

  { key: "suppliers.view", domain: "suppliers", action: "view", label: "View suppliers", description: "View supplier connection metadata.", risk: "standard", laravelPolicyHint: "SupplierPolicy::view" },
  { key: "suppliers.manage", domain: "suppliers", action: "manage", label: "Manage suppliers", description: "Manage supplier configuration metadata.", risk: "elevated", laravelPolicyHint: "SupplierPolicy::manage" },

  { key: "agents.view", domain: "agents", action: "view", label: "View agents", description: "View agent accounts.", risk: "standard", laravelPolicyHint: "AgentPolicy::view" },
  { key: "agents.manage", domain: "agents", action: "manage", label: "Manage agents", description: "Manage agent account metadata.", risk: "elevated", laravelPolicyHint: "AgentPolicy::manage" },

  { key: "pnrs.view", domain: "pnrs", action: "view", label: "View PNRs and orders", description: "View PNR and NDC order records.", risk: "standard", channelAware: true, laravelPolicyHint: "PnrPolicy::view" },
  { key: "pnrs.review", domain: "pnrs", action: "view", label: "Review PNRs and orders", description: "Review PNR and order details.", risk: "elevated", channelAware: true, laravelPolicyHint: "PnrPolicy::review" },
  { key: "pnrs.cancel.request", domain: "pnrs", action: "request", label: "Request PNR cancellation", description: "Submit PNR or order cancellation requests.", risk: "elevated", channelAware: true, laravelPolicyHint: "PnrPolicy::cancelRequest" },
  { key: "pnrs.cancel.approve", domain: "pnrs", action: "approve", label: "Approve PNR cancellation", description: "Approve PNR or order cancellation.", risk: "high", isHighRisk: true, channelAware: true, prerequisiteKey: "pnrs.cancel.request", laravelPolicyHint: "PnrPolicy::cancelApprove" },

  { key: "tickets.view", domain: "tickets", action: "view", label: "View tickets", description: "View ticket and document records.", risk: "standard", channelAware: true, laravelPolicyHint: "TicketPolicy::view" },
  { key: "tickets.review", domain: "tickets", action: "view", label: "Review tickets", description: "Review ticket issuance readiness.", risk: "elevated", channelAware: true, laravelPolicyHint: "TicketPolicy::review" },
  { key: "tickets.issue.request", domain: "tickets", action: "request", label: "Request ticket issuance", description: "Submit ticket issuance requests.", risk: "elevated", channelAware: true, laravelPolicyHint: "TicketPolicy::issueRequest" },
  { key: "tickets.issue.approve", domain: "tickets", action: "approve", label: "Approve ticket issuance", description: "Approve ticket issuance requests.", risk: "high", isHighRisk: true, channelAware: true, prerequisiteKey: "tickets.issue.request", laravelPolicyHint: "TicketPolicy::issueApprove" },

  { key: "reports.view", domain: "reports", action: "view", label: "View reports", description: "Access analytics and reports.", risk: "standard", laravelPolicyHint: "ReportPolicy::view" },
  { key: "reports.export", domain: "reports", action: "export", label: "Export reports", description: "Export report data.", risk: "elevated", laravelPolicyHint: "ReportPolicy::export" },

  { key: "cms.view", domain: "cms", action: "view", label: "View CMS", description: "View CMS content records.", risk: "standard", laravelPolicyHint: "CmsPolicy::view" },
  { key: "cms.preview", domain: "cms", action: "view", label: "Preview CMS", description: "Preview CMS content locally.", risk: "standard", laravelPolicyHint: "CmsPolicy::preview" },
  { key: "cms.edit", domain: "cms", action: "update", label: "Edit CMS", description: "Edit CMS content drafts.", risk: "elevated", laravelPolicyHint: "CmsPolicy::edit" },
  { key: "cms.review", domain: "cms", action: "view", label: "Review CMS", description: "Review CMS content changes.", risk: "elevated", laravelPolicyHint: "CmsPolicy::review" },
  { key: "cms.publish.request", domain: "cms", action: "request", label: "Request CMS publication", description: "Submit CMS publication requests.", risk: "elevated", laravelPolicyHint: "CmsPolicy::publishRequest" },
  { key: "cms.publish.approve", domain: "cms", action: "approve", label: "Approve CMS publication", description: "Approve CMS publication.", risk: "high", isHighRisk: true, prerequisiteKey: "cms.publish.request", laravelPolicyHint: "CmsPolicy::publishApprove" },

  { key: "users.view", domain: "users", action: "view", label: "View users", description: "View dashboard user directory.", risk: "standard", laravelPolicyHint: "UserPolicy::view" },
  { key: "users.invite", domain: "users", action: "invite", label: "Invite users", description: "Invite new dashboard users.", risk: "elevated", laravelPolicyHint: "UserPolicy::invite" },
  { key: "users.update", domain: "users", action: "update", label: "Update users", description: "Update user profile metadata.", risk: "elevated", laravelPolicyHint: "UserPolicy::update" },
  { key: "users.suspend", domain: "users", action: "suspend", label: "Suspend users", description: "Suspend user accounts.", risk: "high", isHighRisk: true, laravelPolicyHint: "UserPolicy::suspend" },
  { key: "users.assignRoles", domain: "users", action: "assign", label: "Assign user roles", description: "Assign roles to users.", risk: "high", isHighRisk: true, laravelPolicyHint: "UserPolicy::assignRoles" },

  { key: "roles.view", domain: "roles", action: "view", label: "View roles", description: "View role definitions.", risk: "standard", laravelPolicyHint: "RolePolicy::view" },
  { key: "roles.create", domain: "roles", action: "create", label: "Create roles", description: "Create custom roles.", risk: "elevated", laravelPolicyHint: "RolePolicy::create" },
  { key: "roles.update", domain: "roles", action: "update", label: "Update roles", description: "Update role definitions.", risk: "elevated", laravelPolicyHint: "RolePolicy::update" },
  { key: "roles.assignPermissions", domain: "roles", action: "assign", label: "Assign role permissions", description: "Assign permissions to roles.", risk: "high", isHighRisk: true, laravelPolicyHint: "RolePolicy::assignPermissions" },

  { key: "settings.view", domain: "settings", action: "view", label: "View settings", description: "View system settings metadata.", risk: "standard", laravelPolicyHint: "SettingPolicy::view" },
  { key: "settings.update", domain: "settings", action: "update", label: "Update settings", description: "Update system settings.", risk: "high", isHighRisk: true, laravelPolicyHint: "SettingPolicy::update" },

  { key: "audit.view", domain: "audit", action: "view", label: "View audit log", description: "View audit event history.", risk: "standard", laravelPolicyHint: "AuditPolicy::view" },
  { key: "audit.export", domain: "audit", action: "export", label: "Export audit log", description: "Export audit events.", risk: "high", isHighRisk: true, laravelPolicyHint: "AuditPolicy::export" },
];

function defaultScopes(channelAware?: boolean): Permission["supportedScopes"] {
  if (channelAware) {
    return ["all", "channel:gds", "channel:ndc", "channel:oneApi", "channel:manual", "channel:mock", "own", "branch"];
  }
  return ["all", "own", "branch", "supplier"];
}

export const PERMISSION_CATALOG: Permission[] = PERMISSION_SEEDS.map((seed, index) => {
  const id = `JP-PRM-${String(index + 1).padStart(4, "0")}`;
  return {
    id,
    key: seed.key,
    domain: seed.domain,
    action: seed.action,
    label: seed.label,
    description: seed.description,
    risk: seed.risk,
    isHighRisk: seed.isHighRisk ?? HIGH_RISK_KEYS.has(seed.key),
    prerequisiteKey: seed.prerequisiteKey ?? null,
    supportedScopes: defaultScopes(seed.channelAware),
    channelAware: seed.channelAware ?? false,
    laravelPolicyHint: seed.laravelPolicyHint,
    implementationStatus: "fixture" as const,
  };
});

export const PERMISSION_BY_KEY = new Map(PERMISSION_CATALOG.map((p) => [p.key, p]));
export const PERMISSION_BY_ID = new Map(PERMISSION_CATALOG.map((p) => [p.id, p]));

export const HIGH_RISK_PERMISSION_KEYS = PERMISSION_CATALOG.filter((p) => p.isHighRisk).map((p) => p.key);

export const PERMISSION_GROUP_LABELS: Record<PermissionGroup, string> = {
  dashboard: "Dashboard",
  bookings: "Bookings",
  payments: "Payments",
  customers: "Customers",
  suppliers: "Suppliers",
  agents: "Agents",
  pnrs: "PNRs and Orders",
  tickets: "Tickets and Documents",
  reports: "Reports",
  cms: "CMS",
  users: "Users",
  roles: "Roles",
  settings: "Settings",
  audit: "Audit",
};

export function getPermissionByKey(key: string): Permission | undefined {
  return PERMISSION_BY_KEY.get(key);
}

export function isHighRiskPermission(key: string): boolean {
  return HIGH_RISK_KEYS.has(key);
}
