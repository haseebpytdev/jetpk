"use client";

import { Card } from "@/components/ui/card";
import {
  connectionStatusLabel,
  environmentBadgeLabel,
  resolveConnectionOperationalStatus,
  type ApiConnectionRow,
} from "@/features/api-connections/lib/connection-status";

type Props = {
  row: ApiConnectionRow;
  providerLabel: string;
  providerIcon?: string;
  busy: boolean;
  isLive: boolean;
  onConfigure: () => void;
  onTest: () => void;
  onToggle: () => void;
};

function StatusBadge({ status }: { status: ReturnType<typeof resolveConnectionOperationalStatus> }) {
  const tone =
    status === "connected"
      ? "bg-emerald-50 text-emerald-800 border-emerald-200"
      : status === "disabled"
        ? "bg-gray-100 text-gray-700 border-gray-200"
        : status === "test_failed"
          ? "bg-red-50 text-red-800 border-red-200"
          : status === "auth_required" || status === "config_incomplete"
            ? "bg-amber-50 text-amber-900 border-amber-200"
            : "bg-sky-50 text-sky-900 border-sky-200";

  return (
    <span className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium ${tone}`}>
      {connectionStatusLabel(status)}
    </span>
  );
}

export function ApiConnectionCard({
  row,
  providerLabel,
  providerIcon,
  busy,
  isLive,
  onConfigure,
  onTest,
  onToggle,
}: Props) {
  const operationalStatus = resolveConnectionOperationalStatus(row);
  const capabilities: string[] = [];
  if (row.provider === "sabre") {
    if (row.sabreGdsSupported) capabilities.push(`GDS ${row.sabreGdsEnabled === false ? "off" : "on"}`);
    if (row.sabreNdcSupported) capabilities.push(`NDC ${row.sabreNdcEnabled ? "on" : "off"}`);
  } else if (row.registryLabel) {
    capabilities.push(row.registryLabel);
  }

  return (
    <Card className="flex h-full flex-col gap-3 p-4" data-testid={`api-connection-card-${row.id}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3">
          <div
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-jp-accent/10 text-sm font-semibold text-jp-accent"
            aria-hidden="true"
          >
            {providerIcon ?? providerLabel.slice(0, 2).toUpperCase()}
          </div>
          <div className="min-w-0">
            <p className="truncate font-semibold text-gray-900">{row.name}</p>
            <p className="text-xs text-jp-muted">{providerLabel}</p>
          </div>
        </div>
        <StatusBadge status={operationalStatus} />
      </div>

      <div className="flex flex-wrap gap-2 text-[11px]">
        <span className="rounded-full border border-jp-border bg-white px-2 py-0.5 font-medium">
          {environmentBadgeLabel(row.environment)}
        </span>
        <span className="rounded-full border border-jp-border bg-white px-2 py-0.5">
          {row.enabled ? "Enabled" : "Disabled"}
        </span>
        <span className="rounded-full border border-jp-border bg-white px-2 py-0.5">
          {row.credentialsConfigured ? "Credentials configured" : "Credentials missing"}
        </span>
      </div>

      {capabilities.length > 0 ? (
        <p className="text-xs text-jp-muted">Capabilities: {capabilities.join(" · ")}</p>
      ) : null}

      <dl className="grid gap-1 text-xs text-jp-muted">
        <div>
          <dt className="sr-only">Last test</dt>
          <dd>
            {row.lastTestStatus
              ? `Last test: ${row.lastTestStatus}${row.lastTestedAt ? ` · ${row.lastTestedAt}` : ""}`
              : "Not tested yet"}
          </dd>
        </div>
        {row.channel ? (
          <div>
            <dt className="sr-only">Channel</dt>
            <dd>Channel: {row.channel}</dd>
          </div>
        ) : null}
      </dl>

      {isLive ? (
        <div className="mt-auto flex flex-wrap gap-2">
          <button
            type="button"
            className="min-h-10 rounded-xl border border-jp-border px-3 text-sm font-medium hover:bg-gray-50"
            disabled={busy}
            onClick={onConfigure}
          >
            Configure
          </button>
          <button
            type="button"
            className="min-h-10 rounded-xl border border-jp-border px-3 text-sm"
            disabled={busy}
            onClick={onTest}
          >
            Test
          </button>
          <button
            type="button"
            className="min-h-10 rounded-xl border border-jp-border px-3 text-sm"
            disabled={busy}
            onClick={onToggle}
          >
            {row.enabled ? "Disable" : "Enable"}
          </button>
        </div>
      ) : null}
    </Card>
  );
}
