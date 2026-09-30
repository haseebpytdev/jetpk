"use client";

import { useEffect, useState } from "react";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { getLaravelApiBase } from "@/lib/read-only/laravel/api-base";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardSession } from "@/lib/session-context";
import { getDashboardSession } from "@/services/session-service";
import { Button } from "@/components/ui/button";
import type { ProfileJsonPayload, ProfileUpdateResponse } from "@/types/profile";

type ProfileFormState = {
  name: string;
  email: string;
  username: string;
  phone: string;
  city: string;
  country_code: string;
  whatsapp: string;
};

const emptyForm: ProfileFormState = {
  name: "",
  email: "",
  username: "",
  phone: "",
  city: "",
  country_code: "PK",
  whatsapp: "",
};

function profileUrl(): string {
  return `${getLaravelApiBase()}/profile?format=json`;
}

function applyPayloadToForm(payload: ProfileJsonPayload, fallbackName: string): ProfileFormState {
  return {
    name: String(payload.user?.name ?? fallbackName ?? ""),
    email: String(payload.user?.email ?? ""),
    username: String(payload.user?.username ?? ""),
    phone: String(payload.profile?.phone ?? ""),
    city: String(payload.profile?.city ?? ""),
    country_code: String(payload.profile?.country_code ?? "PK"),
    whatsapp: String(payload.profile?.whatsapp ?? ""),
  };
}

export function ProfilePageContent() {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const shellSession = useDashboardSession();
  const [displayName, setDisplayName] = useState(shellSession?.displayName ?? "");
  const [roles, setRoles] = useState<string[]>(shellSession?.roles ?? []);
  const [accountType, setAccountType] = useState(shellSession?.accountType ?? "");
  const [accountStatus, setAccountStatus] = useState(shellSession?.accountStatus ?? "");
  const [sessionUsable, setSessionUsable] = useState(Boolean(shellSession && !shellSession.unavailable));
  const [form, setForm] = useState<ProfileFormState>(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [photoUrl, setPhotoUrl] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        let next = shellSession;
        if (!next || next.unavailable) {
          next = await getDashboardSession({ portal });
        }
        if (cancelled) {
          return;
        }

        const usable = Boolean(next && !next.unavailable && next.sessionUsable !== false);
        setSessionUsable(usable);
        setDisplayName(next?.displayName ?? "");
        setRoles(next?.roles ?? []);
        setAccountType(next?.accountType ?? "");
        setAccountStatus(next?.accountStatus ?? "");

        if (!usable && isLive) {
          setError("Could not load your authenticated session. Sign in again and retry.");
          setForm(emptyForm);
          return;
        }

        if (!isLive) {
          // Fixture/preview builds only — never used as a silent production fallback.
          setForm({
            name: next?.displayName ?? "",
            email: next?.email && next.email !== "—" ? next.email : "",
            username: "",
            phone: "",
            city: "",
            country_code: "PK",
            whatsapp: "",
          });
          return;
        }

        const result = await laravelRequest<ProfileJsonPayload>(profileUrl(), {
          method: "GET",
          headers: { Accept: "application/json" },
          retryCsrfOnce: false,
          timeoutMs: 15000,
        });

        if (cancelled) {
          return;
        }

        if (!result.ok) {
          setForm(emptyForm);
          setError(result.message ?? "Could not load editable profile fields.");
          return;
        }

        setForm(applyPayloadToForm(result.data, next?.displayName ?? ""));
        if (result.data.account) {
          setAccountType(result.data.account.account_type || next?.accountType || "");
          setAccountStatus(result.data.account.status || next?.accountStatus || "");
        }
        setPhotoUrl(
          typeof result.data.profile?.profile_photo_url === "string"
            ? result.data.profile.profile_photo_url
            : null,
        );
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : "Could not load profile.");
          setForm(emptyForm);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [isLive, portal, shellSession]);

  async function onSave() {
    if (!isLive || saving || !sessionUsable) {
      return;
    }
    setSaving(true);
    setError(null);
    setSuccess(null);

    const formData = new FormData();
    formData.append("_method", "PATCH");
    formData.append("name", form.name);
    formData.append("email", form.email);
    formData.append("username", form.username);
    formData.append("phone", form.phone);
    formData.append("city", form.city);
    formData.append("country_code", form.country_code);
    formData.append("whatsapp", form.whatsapp);

    const result = await laravelRequest<ProfileUpdateResponse>(profileUrl(), {
      method: "POST",
      headers: { Accept: "application/json" },
      formData,
      retryCsrfOnce: true,
    });

    setSaving(false);
    if (!result.ok) {
      setError(result.message ?? "Profile update failed.");
      return;
    }

    setSuccess(result.data.message ?? "Profile updated.");
    const nested = result.data.profile;
    if (nested?.user && nested?.profile) {
      setForm(applyPayloadToForm(nested, form.name));
      if (nested.account) {
        setAccountType(nested.account.account_type);
        setAccountStatus(nested.account.status);
      }
      setPhotoUrl(
        typeof nested.profile.profile_photo_url === "string" ? nested.profile.profile_photo_url : null,
      );
    }
  }

  const normalizeAccountLabel = (value: string) => {
    const trimmed = (value || "").trim();
    if (!trimmed || trimmed.toLowerCase() === "unknown") {
      return "—";
    }
    return trimmed.replaceAll("_", " ");
  };
  const accountTypeLabel = normalizeAccountLabel(accountType);
  const accountStatusLabel = normalizeAccountLabel(accountStatus);

  return (
    <div className="mx-auto max-w-2xl space-y-6" data-testid="my-profile-page">
      <section className="rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
        <h2 className="font-display text-lg font-semibold text-gray-900">Account</h2>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Signed in as</dt>
            <dd className="font-medium" data-testid="profile-session-name">
              {displayName || "—"}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Role</dt>
            <dd data-testid="profile-session-role">{roles[0] ?? "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Account type</dt>
            <dd className="capitalize" data-testid="profile-session-account-type">
              {accountTypeLabel}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Status</dt>
            <dd className="capitalize" data-testid="profile-session-status">
              {accountStatusLabel}
            </dd>
          </div>
        </dl>
        <p className="mt-3 text-xs text-jp-muted">
          Role, permissions, and protected account state are not editable here.
        </p>
      </section>

      <section className="rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
        <h2 className="font-display text-lg font-semibold text-gray-900">Contact profile</h2>
        {loading ? <p className="mt-3 text-sm text-jp-muted">Loading profile…</p> : null}
        {error ? (
          <p className="mt-3 text-sm text-red-600" data-testid="profile-error">
            {error}
          </p>
        ) : null}
        {success ? (
          <p className="mt-3 text-sm text-green-700" data-testid="profile-success">
            {success}
          </p>
        ) : null}
        {!loading && (sessionUsable || !isLive) ? (
          <div className="mt-4 grid gap-3">
            {photoUrl ? (
              <div className="flex items-center gap-4">
                <div className="h-16 w-16 overflow-hidden rounded-full border border-jp-border bg-gray-50">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={photoUrl} alt="Profile photo" className="h-full w-full object-cover" />
                </div>
              </div>
            ) : null}
            <label className="block text-xs font-medium text-jp-muted">
              Full name
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.name}
                onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                disabled={!isLive || !sessionUsable}
                data-testid="profile-name"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Email
              <input
                type="email"
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.email}
                onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))}
                disabled={!isLive || !sessionUsable}
                data-testid="profile-email"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Username
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.username}
                onChange={(e) => setForm((prev) => ({ ...prev, username: e.target.value }))}
                disabled={!isLive || !sessionUsable}
                data-testid="profile-username"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Phone
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.phone}
                onChange={(e) => setForm((prev) => ({ ...prev, phone: e.target.value }))}
                disabled={!isLive || !sessionUsable}
                data-testid="profile-phone"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              WhatsApp
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.whatsapp}
                onChange={(e) => setForm((prev) => ({ ...prev, whatsapp: e.target.value }))}
                disabled={!isLive || !sessionUsable}
                data-testid="profile-whatsapp"
              />
            </label>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block text-xs font-medium text-jp-muted">
                City
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={form.city}
                  onChange={(e) => setForm((prev) => ({ ...prev, city: e.target.value }))}
                  disabled={!isLive || !sessionUsable}
                  data-testid="profile-city"
                />
              </label>
              <label className="block text-xs font-medium text-jp-muted">
                Country code
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={form.country_code}
                  onChange={(e) => setForm((prev) => ({ ...prev, country_code: e.target.value }))}
                  disabled={!isLive || !sessionUsable}
                  data-testid="profile-country"
                />
              </label>
            </div>
            <div className="pt-2">
              <Button
                type="button"
                disabled={!isLive || !sessionUsable || saving}
                onClick={onSave}
                data-testid="profile-save"
              >
                {saving ? "Saving…" : "Save profile"}
              </Button>
              {!isLive ? (
                <p className="mt-2 text-xs text-jp-muted">Profile edits are available in live dashboard mode.</p>
              ) : null}
            </div>
          </div>
        ) : null}
      </section>
    </div>
  );
}
