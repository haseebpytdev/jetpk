export type ApiConnectionRow = {
  id: string;
  name: string;
  provider: string;
  environment: string;
  enabled: boolean;
  status?: string;
  credentialsConfigured?: boolean;
  lastTestStatus?: string | null;
  lastTestedAt?: string | null;
  lastFailure?: string | null;
  registryLabel?: string | null;
  channel?: string;
  sabreGdsSupported?: boolean | null;
  sabreNdcSupported?: boolean | null;
  sabreNdcEnabled?: boolean | null;
};

export type ConnectionOperationalStatus =
  | "connected"
  | "untested"
  | "auth_required"
  | "config_incomplete"
  | "disabled"
  | "test_failed";

const STATUS_LABELS: Record<ConnectionOperationalStatus, string> = {
  connected: "Connected",
  untested: "Untested",
  auth_required: "Authentication required",
  config_incomplete: "Configuration incomplete",
  disabled: "Disabled",
  test_failed: "Last test failed",
};

export function resolveConnectionOperationalStatus(row: ApiConnectionRow): ConnectionOperationalStatus {
  if (!row.enabled) {
    return "disabled";
  }
  if (!row.credentialsConfigured) {
    return "auth_required";
  }
  const test = (row.lastTestStatus ?? "").toLowerCase();
  if (test.includes("fail") || test.includes("error") || test.includes("invalid")) {
    return "test_failed";
  }
  if (test.includes("pass") || test.includes("success") || test.includes("ok") || test.includes("connected")) {
    return "connected";
  }
  const status = (row.status ?? "").toLowerCase();
  if (status.includes("error") || status.includes("fail")) {
    return "test_failed";
  }
  if (status.includes("pending") || status.includes("incomplete")) {
    return "config_incomplete";
  }

  return "untested";
}

export function connectionStatusLabel(status: ConnectionOperationalStatus): string {
  return STATUS_LABELS[status];
}

export function environmentBadgeLabel(environment: string): string {
  const normalized = environment.trim().toLowerCase();
  if (normalized === "live" || normalized === "production") {
    return "Live";
  }
  if (normalized === "sandbox" || normalized === "cert" || normalized === "demo") {
    return normalized === "demo" ? "Demo" : "Sandbox";
  }

  return environment || "Unknown";
}
