"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  loadSeoGlobal,
  loadSeoOverview,
  loadSeoPage,
  loadSeoSocial,
  loadSeoVerification,
  publishSeoGlobal,
  publishSeoPage,
  publishSeoVerification,
  saveSeoGlobal,
  saveSeoPage,
  saveSeoSocial,
  saveSeoVerification,
} from "@/services/operational-api";

type SeoTab = "overview" | "global" | "social" | "verification" | "page";

type GlobalForm = {
  brand_name: string;
  title: string;
  description: string;
  title_suffix: string;
  canonical_domain: string;
  robots: string;
  og_title: string;
  og_description: string;
  og_image: string;
};

type PageForm = {
  title: string;
  description: string;
  canonical: string;
  index: boolean;
  follow: boolean;
  og_title: string;
  og_description: string;
  og_image: string;
  sitemap_eligible: boolean;
};

type PageRow = {
  source_type?: string;
  source_id?: string;
  label?: string;
  path?: string;
  title?: string;
  health_status?: string;
};

const emptyGlobal: GlobalForm = {
  brand_name: "",
  title: "",
  description: "",
  title_suffix: "",
  canonical_domain: "",
  robots: "index,follow",
  og_title: "",
  og_description: "",
  og_image: "",
};

const emptyPage: PageForm = {
  title: "",
  description: "",
  canonical: "",
  index: true,
  follow: true,
  og_title: "",
  og_description: "",
  og_image: "",
  sitemap_eligible: true,
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : {};
}

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function SeoWorkspace() {
  const isLive = useDashboardLiveMode();
  const [tab, setTab] = useState<SeoTab>("overview");
  const [stats, setStats] = useState<Record<string, unknown>>({});
  const [pages, setPages] = useState<PageRow[]>([]);
  const [globalForm, setGlobalForm] = useState<GlobalForm>(emptyGlobal);
  const [socialForm, setSocialForm] = useState({ og_title: "", og_description: "", og_image: "" });
  const [verificationForm, setVerificationForm] = useState({
    verification_google: "",
    verification_bing: "",
  });
  const [selectedPage, setSelectedPage] = useState<{ sourceType: string; sourceId: string; label: string } | null>(
    null,
  );
  const [pageForm, setPageForm] = useState<PageForm>(emptyPage);
  const [canPublishPage, setCanPublishPage] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const refreshOverview = useCallback(async () => {
    const result = await loadSeoOverview();
    if (!result.ok) {
      setError(result.message ?? "Could not load SEO overview.");
      return;
    }
    const payload = payloadOf(result);
    setStats(asRecord(payload.stats));
    setPages(Array.isArray(payload.pages) ? (payload.pages as PageRow[]) : []);
  }, []);

  const refreshGlobal = useCallback(async () => {
    const result = await loadSeoGlobal();
    if (!result.ok) {
      setError(result.message ?? "Could not load global SEO.");
      return;
    }
    const settings = asRecord(payloadOf(result).settings);
    setGlobalForm({
      brand_name: String(settings.brand_name ?? ""),
      title: String(settings.title ?? ""),
      description: String(settings.description ?? ""),
      title_suffix: String(settings.title_suffix ?? ""),
      canonical_domain: String(settings.canonical_domain ?? ""),
      robots: String(settings.robots ?? "index,follow"),
      og_title: String(settings.og_title ?? ""),
      og_description: String(settings.og_description ?? ""),
      og_image: String(settings.og_image ?? ""),
    });
  }, []);

  const refreshSocial = useCallback(async () => {
    const result = await loadSeoSocial();
    if (!result.ok) {
      setError(result.message ?? "Could not load social SEO.");
      return;
    }
    const social = asRecord(asRecord(payloadOf(result).social).global);
    setSocialForm({
      og_title: String(social.og_title ?? ""),
      og_description: String(social.og_description ?? ""),
      og_image: String(social.og_image ?? ""),
    });
  }, []);

  const refreshVerification = useCallback(async () => {
    const result = await loadSeoVerification();
    if (!result.ok) {
      setError(result.message ?? "Could not load verification SEO.");
      return;
    }
    const payload = payloadOf(result);
    const verification = asRecord(payload.verification);
    const global = asRecord(payload.global);
    setVerificationForm({
      verification_google: String(
        verification.google ?? verification.verification_google ?? global.verification_google ?? "",
      ),
      verification_bing: String(
        verification.bing ?? verification.verification_bing ?? global.verification_bing ?? "",
      ),
    });
  }, []);

  useEffect(() => {
    if (!isLive) return;
    void refreshOverview();
    void refreshGlobal();
  }, [isLive, refreshGlobal, refreshOverview]);

  useEffect(() => {
    if (!isLive) return;
    if (tab === "social") void refreshSocial();
    if (tab === "verification") void refreshVerification();
  }, [isLive, refreshSocial, refreshVerification, tab]);

  const openPage = async (row: PageRow) => {
    const sourceType = String(row.source_type ?? "");
    const sourceId = String(row.source_id ?? "");
    if (!sourceType || !sourceId) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await loadSeoPage(sourceType, sourceId);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Could not load page SEO.");
      return;
    }
    const page = asRecord(payloadOf(result).page);
    const form = asRecord(page.form);
    setSelectedPage({
      sourceType,
      sourceId,
      label: String(page.label ?? row.label ?? sourceId),
    });
    setPageForm({
      title: String(form.title ?? page.title ?? ""),
      description: String(form.description ?? page.description ?? ""),
      canonical: String(form.canonical ?? page.canonical ?? ""),
      index: form.index !== false && form.index !== "0",
      follow: form.follow !== false && form.follow !== "0",
      og_title: String(form.og_title ?? page.og_title ?? ""),
      og_description: String(form.og_description ?? page.og_description ?? ""),
      og_image: String(form.og_image ?? page.og_image ?? ""),
      sitemap_eligible: form.sitemap_eligible !== false && form.sitemap_eligible !== "0",
    });
    setCanPublishPage(sourceType === "managed" || sourceType === "custom");
    setTab("page");
  };

  const metricCards = useMemo(
    () => [
      { label: "Total pages", value: String(stats.total ?? 0) },
      { label: "Indexable", value: String(stats.indexable ?? 0) },
      { label: "Missing titles", value: String(stats.missing_titles ?? 0) },
      { label: "Needs attention", value: String(stats.attention ?? 0) },
    ],
    [stats],
  );

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="SEO" description="SEO management is available in live dashboard mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader
        title="SEO"
        description="Manage homepage, page, social, and verification metadata through the current Laravel SEO services."
      />

      <div className="flex flex-wrap gap-2">
        {(
          [
            ["overview", "Overview"],
            ["global", "Global"],
            ["social", "Social"],
            ["verification", "Verification"],
          ] as const
        ).map(([id, label]) => (
          <Button
            key={id}
            type="button"
            variant={tab === id ? "primary" : "secondary"}
            size="sm"
            onClick={() => setTab(id)}
          >
            {label}
          </Button>
        ))}
        {selectedPage ? (
          <Button type="button" variant={tab === "page" ? "primary" : "secondary"} size="sm" onClick={() => setTab("page")}>
            Page: {selectedPage.label}
          </Button>
        ) : null}
      </div>

      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      {tab === "overview" ? (
        <div className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {metricCards.map((metric) => (
              <Card key={metric.label}>
                <CardDescription>{metric.label}</CardDescription>
                <CardTitle className="mt-1 text-2xl">{metric.value}</CardTitle>
              </Card>
            ))}
          </div>
          <Card className="overflow-x-auto">
            <CardTitle className="mb-3">Pages</CardTitle>
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b text-jp-muted">
                  <th className="px-2 py-2 font-medium">Label</th>
                  <th className="px-2 py-2 font-medium">Path</th>
                  <th className="px-2 py-2 font-medium">Title</th>
                  <th className="px-2 py-2 font-medium">Health</th>
                  <th className="px-2 py-2 font-medium" />
                </tr>
              </thead>
              <tbody>
                {pages.map((row) => (
                  <tr key={`${row.source_type}-${row.source_id}`} className="border-b last:border-0">
                    <td className="px-2 py-2">{row.label ?? "—"}</td>
                    <td className="px-2 py-2 font-mono text-xs">{row.path ?? "—"}</td>
                    <td className="px-2 py-2">{row.title || "—"}</td>
                    <td className="px-2 py-2">{row.health_status ?? "—"}</td>
                    <td className="px-2 py-2 text-right">
                      <Button type="button" size="sm" variant="secondary" onClick={() => void openPage(row)}>
                        Edit
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
        </div>
      ) : null}

      {tab === "global" ? (
        <Card className="space-y-3">
          <CardTitle>Global SEO</CardTitle>
          <CardDescription>Draft changes require Publish to update the live public site.</CardDescription>
          {(
            [
              ["brand_name", "Brand name"],
              ["title", "Homepage title"],
              ["description", "Homepage description"],
              ["title_suffix", "Title suffix"],
              ["canonical_domain", "Canonical domain"],
              ["robots", "Robots"],
              ["og_title", "Open Graph title"],
              ["og_description", "Open Graph description"],
              ["og_image", "Open Graph image"],
            ] as const
          ).map(([key, label]) => (
            <label key={key} className="block space-y-1 text-sm">
              <span className="font-medium text-gray-900">{label}</span>
              {key === "description" || key === "og_description" ? (
                <textarea
                  className="min-h-24 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
                  value={globalForm[key]}
                  onChange={(event) => setGlobalForm((prev) => ({ ...prev, [key]: event.target.value }))}
                />
              ) : (
                <Input
                  value={globalForm[key]}
                  onChange={(event) => setGlobalForm((prev) => ({ ...prev, [key]: event.target.value }))}
                />
              )}
            </label>
          ))}
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError(null);
                setSuccess(null);
                const result = await saveSeoGlobal(globalForm);
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Save failed.");
                  return;
                }
                setSuccess(result.message ?? "Global SEO draft saved.");
                await refreshGlobal();
              }}
            >
              Save draft
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError(null);
                setSuccess(null);
                const result = await publishSeoGlobal();
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Publish failed.");
                  return;
                }
                setSuccess(result.message ?? "Global SEO published.");
                await refreshGlobal();
              }}
            >
              Publish
            </Button>
          </div>
        </Card>
      ) : null}

      {tab === "social" ? (
        <Card className="space-y-3">
          <CardTitle>Social defaults</CardTitle>
          {(
            [
              ["og_title", "Open Graph title"],
              ["og_description", "Open Graph description"],
              ["og_image", "Open Graph image"],
            ] as const
          ).map(([key, label]) => (
            <label key={key} className="block space-y-1 text-sm">
              <span className="font-medium text-gray-900">{label}</span>
              <Input
                value={socialForm[key]}
                onChange={(event) => setSocialForm((prev) => ({ ...prev, [key]: event.target.value }))}
              />
            </label>
          ))}
          <Button
            type="button"
            disabled={busy}
            onClick={async () => {
              setBusy(true);
              setError(null);
              setSuccess(null);
              const result = await saveSeoSocial(socialForm);
              setBusy(false);
              if (!result.ok) {
                setError(result.message ?? "Save failed.");
                return;
              }
              setSuccess(result.message ?? "Social defaults draft saved.");
              await refreshSocial();
            }}
          >
            Save draft
          </Button>
        </Card>
      ) : null}

      {tab === "verification" ? (
        <Card className="space-y-3">
          <CardTitle>Search verification</CardTitle>
          <label className="block space-y-1 text-sm">
            <span className="font-medium text-gray-900">Google verification</span>
            <Input
              value={verificationForm.verification_google}
              onChange={(event) =>
                setVerificationForm((prev) => ({ ...prev, verification_google: event.target.value }))
              }
            />
          </label>
          <label className="block space-y-1 text-sm">
            <span className="font-medium text-gray-900">Bing verification</span>
            <Input
              value={verificationForm.verification_bing}
              onChange={(event) =>
                setVerificationForm((prev) => ({ ...prev, verification_bing: event.target.value }))
              }
            />
          </label>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError(null);
                setSuccess(null);
                const result = await saveSeoVerification(verificationForm);
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Save failed.");
                  return;
                }
                setSuccess(result.message ?? "Verification draft saved.");
                await refreshVerification();
              }}
            >
              Save draft
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError(null);
                setSuccess(null);
                const result = await publishSeoVerification();
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Publish failed.");
                  return;
                }
                setSuccess(result.message ?? "Verification published.");
                await refreshVerification();
              }}
            >
              Publish
            </Button>
          </div>
        </Card>
      ) : null}

      {tab === "page" && selectedPage ? (
        <Card className="space-y-3">
          <CardTitle>{selectedPage.label}</CardTitle>
          <CardDescription>
            Source: {selectedPage.sourceType}/{selectedPage.sourceId}
          </CardDescription>
          {(
            [
              ["title", "Title"],
              ["description", "Description"],
              ["canonical", "Canonical"],
              ["og_title", "Open Graph title"],
              ["og_description", "Open Graph description"],
              ["og_image", "Open Graph image"],
            ] as const
          ).map(([key, label]) => (
            <label key={key} className="block space-y-1 text-sm">
              <span className="font-medium text-gray-900">{label}</span>
              {key === "description" || key === "og_description" ? (
                <textarea
                  className="min-h-24 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
                  value={pageForm[key]}
                  onChange={(event) => setPageForm((prev) => ({ ...prev, [key]: event.target.value }))}
                />
              ) : (
                <Input
                  value={pageForm[key]}
                  onChange={(event) => setPageForm((prev) => ({ ...prev, [key]: event.target.value }))}
                />
              )}
            </label>
          ))}
          <div className="flex flex-wrap gap-4 text-sm">
            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={pageForm.index}
                onChange={(event) => setPageForm((prev) => ({ ...prev, index: event.target.checked }))}
              />
              Index
            </label>
            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={pageForm.follow}
                onChange={(event) => setPageForm((prev) => ({ ...prev, follow: event.target.checked }))}
              />
              Follow
            </label>
            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={pageForm.sitemap_eligible}
                onChange={(event) =>
                  setPageForm((prev) => ({ ...prev, sitemap_eligible: event.target.checked }))
                }
              />
              Sitemap eligible
            </label>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError(null);
                setSuccess(null);
                const result = await saveSeoPage(selectedPage.sourceType, selectedPage.sourceId, pageForm);
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Save failed.");
                  return;
                }
                setSuccess(result.message ?? "Page SEO saved.");
                await refreshOverview();
              }}
            >
              Save
            </Button>
            {canPublishPage ? (
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={async () => {
                  setBusy(true);
                  setError(null);
                  setSuccess(null);
                  const result = await publishSeoPage(selectedPage.sourceType, selectedPage.sourceId);
                  setBusy(false);
                  if (!result.ok) {
                    setError(result.message ?? "Publish failed.");
                    return;
                  }
                  setSuccess(result.message ?? "Page SEO published.");
                  await refreshOverview();
                }}
              >
                Publish
              </Button>
            ) : null}
          </div>
        </Card>
      ) : null}
    </PageContainer>
  );
}
