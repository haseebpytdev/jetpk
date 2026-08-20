"use client";

import { useId, useState } from "react";
import type { FareRulesContract } from "../types";

type FareRulesAccordionProps = {
  rules?: FareRulesContract | null;
  refundRule?: string | null;
  changeRule?: string | null;
  refundable?: boolean;
};

function SafeRuleText({ text }: { text: string }) {
  return (
    <p className="whitespace-pre-wrap break-words text-sm text-jp-text" data-testid="fare-rule-text">
      {text}
    </p>
  );
}

export function FareRulesAccordion({
  rules,
  refundRule,
  changeRule,
  refundable,
}: FareRulesAccordionProps) {
  const baseId = useId();
  const [openSection, setOpenSection] = useState<string | null>("refund");

  const sections: Array<{ id: string; title: string; content: string | null }> = [
    {
      id: "refund",
      title: "Cancellation / refund",
      content: rules?.refund_rule ?? rules?.refund_status ?? (refundable === true ? "Refundable" : refundable === false ? "Non-refundable" : refundRule ?? null),
    },
    {
      id: "changes",
      title: "Changes",
      content: rules?.change_rule ?? changeRule ?? null,
    },
    {
      id: "penalties",
      title: "Penalties",
      content: rules?.penalty ?? null,
    },
    {
      id: "ticketing",
      title: "Fare details",
      content: [rules?.fare_basis, rules?.booking_class, rules?.cabin, rules?.fare_family]
        .filter(Boolean)
        .join(" · ") || null,
    },
  ];

  const ruleLines = rules?.rule_lines ?? [];

  return (
    <section data-testid="fare-rules-accordion" aria-labelledby={`${baseId}-heading`}>
      <h3 id={`${baseId}-heading`} className="text-sm font-semibold text-jp-text">
        Fare policy
      </h3>
      <div className="mt-2.5 overflow-hidden rounded-jp-md border border-jp-border">
        {sections.map((section) => {
          if (!section.content) return null;
          const expanded = openSection === section.id;
          const panelId = `${baseId}-${section.id}`;
          return (
            <div key={section.id} className="border-b border-jp-border-soft last:border-b-0">
              <button
                type="button"
                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm font-medium text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                aria-expanded={expanded}
                aria-controls={panelId}
                onClick={() => setOpenSection(expanded ? null : section.id)}
              >
                {section.title}
                <span aria-hidden>{expanded ? "−" : "+"}</span>
              </button>
              {expanded ? (
                <div id={panelId} className="border-t border-jp-border-soft bg-jp-surface-muted px-3 py-2.5">
                  <SafeRuleText text={section.content} />
                </div>
              ) : null}
            </div>
          );
        })}
        {ruleLines.length > 0 ? (
          <div className="border-t border-jp-border px-3 py-2.5">
            <p className="mb-2 text-sm font-medium text-jp-text">Full fare conditions</p>
            {ruleLines.map((line, index) => (
              <SafeRuleText key={`rule-line-${index}`} text={line} />
            ))}
          </div>
        ) : null}
        {sections.every((s) => !s.content) && ruleLines.length === 0 ? (
          <p className="px-3 py-2.5 text-sm text-jp-text-muted">Fare rules were not provided by the airline.</p>
        ) : null}
      </div>
    </section>
  );
}
