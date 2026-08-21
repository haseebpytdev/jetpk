"use client";

import { useId, useMemo, useState } from "react";
import type { FareRulesContract } from "../types";

type FareRulesAccordionProps = {
  rules?: FareRulesContract | null;
  refundRule?: string | null;
  changeRule?: string | null;
  refundable?: boolean;
  routeLabel?: string | null;
};

function SafeRuleText({ text }: { text: string }) {
  return (
    <p className="whitespace-pre-wrap break-words text-sm text-jp-text" data-testid="fare-rule-text">
      {text}
    </p>
  );
}

function classifyRuleLine(line: string): "no_show" | "before_departure" | "after_departure" | "penalty" | "other" {
  const lower = line.toLowerCase();
  if (/no[\s-]?show/.test(lower)) return "no_show";
  if (/before\s+departure|prior\s+to\s+departure/.test(lower)) return "before_departure";
  if (/after\s+departure/.test(lower)) return "after_departure";
  if (/penalt/.test(lower)) return "penalty";
  return "other";
}

export function FareRulesAccordion({
  rules,
  refundRule,
  changeRule,
  refundable,
  routeLabel,
}: FareRulesAccordionProps) {
  const baseId = useId();

  const refundContent =
    rules?.refund_rule ??
    rules?.refund_status ??
    (refundable === true ? "Refundable" : refundable === false ? "Non-refundable" : refundRule ?? null);

  const changeContent = rules?.change_rule ?? changeRule ?? null;
  const penaltyContent = rules?.penalty ?? null;
  const noShowContent = rules?.no_show ?? null;
  const beforeDeparture = rules?.before_departure ?? null;
  const afterDeparture = rules?.after_departure ?? null;

  const { leftoverLines, classified } = useMemo(() => {
    const lines = rules?.rule_lines ?? [];
    const buckets: Record<"no_show" | "before_departure" | "after_departure" | "penalty", string[]> = {
      no_show: [],
      before_departure: [],
      after_departure: [],
      penalty: [],
    };
    const leftover: string[] = [];
    for (const line of lines) {
      const kind = classifyRuleLine(line);
      if (kind === "other") leftover.push(line);
      else buckets[kind].push(line);
    }
    return { leftoverLines: leftover, classified: buckets };
  }, [rules?.rule_lines]);

  const sections: Array<{ id: string; title: string; content: string | null }> = [
    {
      id: "refund",
      title: "Cancellation / Refund",
      content: refundContent,
    },
    {
      id: "changes",
      title: "Exchange / Changes",
      content: changeContent,
    },
    {
      id: "penalties",
      title: "Penalties",
      content: penaltyContent ?? (classified.penalty.length ? classified.penalty.join("\n") : null),
    },
    {
      id: "no_show",
      title: "No-show",
      content: noShowContent ?? (classified.no_show.length ? classified.no_show.join("\n") : null),
    },
    {
      id: "before_departure",
      title: "Before departure",
      content: beforeDeparture ?? (classified.before_departure.length ? classified.before_departure.join("\n") : null),
    },
    {
      id: "after_departure",
      title: "After departure",
      content: afterDeparture ?? (classified.after_departure.length ? classified.after_departure.join("\n") : null),
    },
  ];

  const visibleSections = sections.filter((section) => Boolean(section.content));
  const displayLeftover = leftoverLines;
  const [openSection, setOpenSection] = useState<string | null>(() => visibleSections[0]?.id ?? null);

  return (
    <section data-testid="fare-rules-accordion" aria-labelledby={`${baseId}-heading`}>
      <h3 id={`${baseId}-heading`} className="sr-only">
        Fare policy
      </h3>

      {routeLabel ? (
        <div
          className="mb-3 flex items-center gap-2 rounded-jp-md bg-jp-surface-muted px-3 py-2 text-sm font-medium text-jp-text"
          data-testid="fare-policy-route-header"
        >
          <span className="text-jp-primary" aria-hidden>
            ✈
          </span>
          <span>{routeLabel}</span>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-jp-md border border-jp-border">
        {visibleSections.map((section) => {
          const expanded = openSection === section.id;
          const panelId = `${baseId}-${section.id}`;
          return (
            <div key={section.id} className="border-b border-jp-border-soft last:border-b-0">
              <button
                type="button"
                className="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm font-medium text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                aria-expanded={expanded}
                aria-controls={panelId}
                onClick={() => setOpenSection(expanded ? null : section.id)}
              >
                <span>{section.title}</span>
                <span className="text-jp-text-muted" aria-hidden>
                  {expanded ? "−" : "+"}
                </span>
              </button>
              {expanded ? (
                <div id={panelId} className="border-t border-jp-border-soft bg-jp-surface-muted/60 px-3 py-2.5">
                  <SafeRuleText text={section.content!} />
                </div>
              ) : null}
            </div>
          );
        })}
        {displayLeftover.length > 0 ? (
          <div className="border-t border-jp-border px-3 py-2.5">
            <p className="mb-2 text-sm font-medium text-jp-text">Additional fare conditions</p>
            {displayLeftover.map((line, index) => (
              <SafeRuleText key={`rule-line-${index}`} text={line} />
            ))}
          </div>
        ) : null}
        {visibleSections.length === 0 && displayLeftover.length === 0 ? (
          <p className="px-3 py-2.5 text-sm text-jp-text-muted">Fare rules were not provided by the airline.</p>
        ) : null}
      </div>
    </section>
  );
}
