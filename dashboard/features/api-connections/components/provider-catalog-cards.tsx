"use client";

import { Card } from "@/components/ui/card";
import type { ProviderCatalog } from "@/features/settings/components/api-connections-workspace";

type ProviderCardMeta = {
  key: string;
  label: string;
  channel?: string;
  description?: string;
  configured?: boolean;
  icon?: string;
  capabilities?: string[];
  readiness?: string;
};

type Props = {
  providers: ProviderCatalog[];
  providerCards?: ProviderCardMeta[];
  selectedKey: string;
  onSelect: (key: string) => void;
};

function mergeProviderMeta(provider: ProviderCatalog, cards: ProviderCardMeta[]): ProviderCardMeta & ProviderCatalog {
  const card = cards.find((item) => item.key === provider.key);
  return { ...provider, ...card, label: card?.label ?? provider.label };
}

export function ProviderCatalogCards({ providers, providerCards = [], selectedKey, onSelect }: Props) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-testid="api-provider-catalog-cards">
      {providers.map((provider) => {
        const meta = mergeProviderMeta(provider, providerCards);
        const selected = selectedKey === provider.key;
        return (
          <button
            key={provider.key}
            type="button"
            className={`text-left ${selected ? "ring-2 ring-jp-accent" : ""}`}
            onClick={() => onSelect(provider.key)}
            data-testid={`api-provider-card-${provider.key}`}
          >
            <Card className="flex h-full flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-2">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-jp-accent/10 text-sm font-semibold text-jp-accent">
                  {(meta.icon ?? meta.label.slice(0, 2)).toUpperCase()}
                </div>
                <span className="rounded-full border border-jp-border px-2 py-0.5 text-[11px]">
                  {meta.installed ? (meta.configured ? "Configured" : meta.readiness ?? "Ready") : "Not installed"}
                </span>
              </div>
              <div>
                <p className="font-semibold text-gray-900">{meta.label}</p>
                {meta.channel ? <p className="text-xs text-jp-muted">{meta.channel}</p> : null}
              </div>
              {(meta.capabilities ?? []).length > 0 ? (
                <div className="flex flex-wrap gap-1">
                  {(meta.capabilities ?? []).map((cap) => (
                    <span key={cap} className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-jp-muted">
                      {cap}
                    </span>
                  ))}
                </div>
              ) : null}
              {meta.description ? <p className="text-xs text-jp-muted">{meta.description}</p> : null}
            </Card>
          </button>
        );
      })}
    </div>
  );
}

export function AddApiConnectionCard({ onClick }: { onClick: () => void }) {
  return (
    <button
      type="button"
      className="h-full min-h-[220px] w-full rounded-xl border-2 border-dashed border-jp-border bg-white p-4 text-left hover:border-jp-accent hover:bg-emerald-50/40"
      onClick={onClick}
      data-testid="api-connection-add-card"
    >
      <span className="text-2xl font-light text-jp-accent">+</span>
      <p className="mt-2 text-sm font-semibold text-gray-900">Add API Connection</p>
      <p className="mt-1 text-xs text-jp-muted">Choose a provider from the catalog and configure credentials securely.</p>
    </button>
  );
}
