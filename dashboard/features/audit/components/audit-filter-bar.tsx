"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveAuditFilters } from "@/lib/audit/query-filters";
import { AUDIT_DATE_PRESET_LABELS } from "@/lib/audit/date-presets";
import { auditQueryToSearchParams } from "@/lib/audit-query";
import type { AuditDateRange, AuditModuleResult, AuditQuery } from "@/types/audit";
import type { AuditDatePreset } from "@/types/audit";

type Props = {
  query: AuditQuery;
  facets: AuditModuleResult["facets"];
  dateRange: AuditDateRange;
};

export function AuditFilterBar({ query, facets, dateRange }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: AuditQuery) => {
      const href = `/audit${auditQueryToSearchParams(next)}`;
      startTransition(() => router.push(href));
    },
    [router],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: AuditQuery = {
      ...query,
      search: "",
      category: "all",
      eventType: "",
      severity: "all",
      outcome: "all",
      actorType: "all",
      actor: "",
      targetType: "all",
      sourceModule: "",
      risk: "all",
      authorization: "all",
      channel: "all",
      datePreset: "last_30_days",
      startDate: "",
      endDate: "",
      validationState: "all",
      securityView: false,
      page: 1,
      sort: "occurredAt",
      direction: "desc",
      selected: null,
      state: "",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  const activeCount = countActiveAuditFilters(query);

  return (
    <Card className="space-y-4" data-testid="audit-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">
          Audit filters{activeCount > 0 ? ` (${activeCount} active)` : ""}
        </h2>
        <div className="flex flex-wrap gap-2">
          <Button variant="ghost" size="sm" type="button" onClick={reset}>
            Reset filters
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply filters
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <Label htmlFor="audit-search">Search</Label>
          <input
            id="audit-search"
            type="search"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            value={draft.search}
            onChange={(e) => setDraft((d) => ({ ...d, search: e.target.value }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="audit-category">Category</Label>
          <Select
            id="audit-category"
            value={draft.category}
            onChange={(e) => setDraft((d) => ({ ...d, category: e.target.value as AuditQuery["category"] }))}
          >
            <option value="all">All categories</option>
            {facets.categories.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-event-type">Event type</Label>
          <Select
            id="audit-event-type"
            value={draft.eventType || "all"}
            onChange={(e) => setDraft((d) => ({ ...d, eventType: e.target.value === "all" ? "" : e.target.value }))}
          >
            <option value="all">All types</option>
            {facets.eventTypes.map((t) => (
              <option key={t} value={t}>{t}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-severity">Severity</Label>
          <Select
            id="audit-severity"
            value={draft.severity}
            onChange={(e) => setDraft((d) => ({ ...d, severity: e.target.value as AuditQuery["severity"] }))}
          >
            <option value="all">All severities</option>
            {facets.severities.map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-outcome">Outcome</Label>
          <Select
            id="audit-outcome"
            value={draft.outcome}
            onChange={(e) => setDraft((d) => ({ ...d, outcome: e.target.value as AuditQuery["outcome"] }))}
          >
            <option value="all">All outcomes</option>
            {facets.outcomes.map((o) => (
              <option key={o} value={o}>{o}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-actor-type">Actor type</Label>
          <Select
            id="audit-actor-type"
            value={draft.actorType}
            onChange={(e) => setDraft((d) => ({ ...d, actorType: e.target.value as AuditQuery["actorType"] }))}
          >
            <option value="all">All actor types</option>
            {facets.actorTypes.map((a) => (
              <option key={a} value={a}>{a}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-actor">Actor</Label>
          <Select
            id="audit-actor"
            value={draft.actor || "all"}
            onChange={(e) => setDraft((d) => ({ ...d, actor: e.target.value === "all" ? "" : e.target.value }))}
          >
            <option value="all">All actors</option>
            {facets.actors.map((a) => (
              <option key={a.id} value={a.id}>{a.name}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-target-type">Target type</Label>
          <Select
            id="audit-target-type"
            value={draft.targetType}
            onChange={(e) => setDraft((d) => ({ ...d, targetType: e.target.value as AuditQuery["targetType"] }))}
          >
            <option value="all">All target types</option>
            {facets.targetTypes.map((t) => (
              <option key={t} value={t}>{t}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-source-module">Source module</Label>
          <Select
            id="audit-source-module"
            value={draft.sourceModule || "all"}
            onChange={(e) => setDraft((d) => ({ ...d, sourceModule: e.target.value === "all" ? "" : e.target.value }))}
          >
            <option value="all">All modules</option>
            {facets.sourceModules.map((m) => (
              <option key={m} value={m}>{m}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-risk">Risk state</Label>
          <Select
            id="audit-risk"
            value={draft.risk}
            onChange={(e) => setDraft((d) => ({ ...d, risk: e.target.value as AuditQuery["risk"] }))}
          >
            <option value="all">All risk states</option>
            {facets.riskStates.map((r) => (
              <option key={r} value={r}>{r}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-authorization">Authorization</Label>
          <Select
            id="audit-authorization"
            value={draft.authorization}
            onChange={(e) => setDraft((d) => ({ ...d, authorization: e.target.value as AuditQuery["authorization"] }))}
          >
            <option value="all">All authorization states</option>
            {facets.authorizationOutcomes.map((a) => (
              <option key={a} value={a}>{a}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-channel">Channel</Label>
          <Select
            id="audit-channel"
            value={draft.channel === "all" ? "all" : draft.channel ?? "all"}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                channel: e.target.value === "all" ? "all" : (e.target.value as AuditQuery["channel"]),
              }))
            }
          >
            <option value="all">All channels</option>
            {facets.channels.filter((c) => c !== null).map((c) => (
              <option key={c} value={c!}>{c}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="audit-date-preset">Date preset</Label>
          <Select
            id="audit-date-preset"
            value={draft.datePreset}
            onChange={(e) => setDraft((d) => ({ ...d, datePreset: e.target.value as AuditDatePreset }))}
          >
            {(Object.keys(AUDIT_DATE_PRESET_LABELS) as AuditDatePreset[]).map((preset) => (
              <option key={preset} value={preset}>{AUDIT_DATE_PRESET_LABELS[preset]}</option>
            ))}
          </Select>
        </div>
        {draft.datePreset === "custom" ? (
          <>
            <div>
              <Label htmlFor="audit-start-date">Start date</Label>
              <input
                id="audit-start-date"
                type="date"
                className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                value={draft.startDate}
                onChange={(e) => setDraft((d) => ({ ...d, startDate: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="audit-end-date">End date</Label>
              <input
                id="audit-end-date"
                type="date"
                className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                value={draft.endDate}
                onChange={(e) => setDraft((d) => ({ ...d, endDate: e.target.value }))}
              />
            </div>
          </>
        ) : null}
        <div>
          <Label htmlFor="audit-validation-state">Validation state</Label>
          <Select
            id="audit-validation-state"
            value={draft.validationState}
            onChange={(e) => setDraft((d) => ({ ...d, validationState: e.target.value as AuditQuery["validationState"] }))}
          >
            <option value="all">All validation states</option>
            {facets.validationStates.map((v) => (
              <option key={v} value={v}>{v}</option>
            ))}
          </Select>
        </div>
      </div>

      {dateRange.error ? (
        <p className="text-sm text-red-700" role="alert" id="audit-date-error">
          {dateRange.error}
        </p>
      ) : null}
    </Card>
  );
}
