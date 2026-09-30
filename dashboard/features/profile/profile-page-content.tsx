"use client";

import { useEffect, useState } from "react";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { getLaravelApiBase } from "@/lib/read-only/laravel/api-base";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardPortal } from "@/lib/portal-context";
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

type SessionLite = {
  displayName: string;
  email: string;
  roles: string[];
  accountType: string;
  accountStatus: string;
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

async function fetchSessionLite(portal: string): Promise<SessionLite> {
  const unavailable: SessionLite = {
    displayName: "Session unavailable",
    email: "—",
    roles: [],
    accountType: "unknown",
    accountStatus: "unknown",
  };

  try {
    const response = await fetch(`/api/dashboard/session?portal=${encodeURIComponent(portal)}`, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    const payload = (await response.json()) as {
      data?: {
        displayName?: string;
        email?: string | null;
        roles?: string[];
        accountType?: string;
        accountStatus?: string;
      };
    };
    if (!response.ok || !payload.data) {
      return unavailable;
    }
    return {
      displayName: payload.data.displayName ?? "Signed in",
      email: payload.data.email ?? "—",
      roles: payload.data.roles ?? [],
      accountType: payload.data.accountType ?? "unknown",
      accountStatus: payload.data.accountStatus ?? "unknown",
    };
  } catch {
    return unavailable;
  }
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
  const [session, setSession] = useState<SessionLite | null>(null);
  const [accountType, setAccountType] = useState<string>("");
  const [accountStatus, setAccountStatus] = useState<string>("");
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
        const nextSession = await fetchSessionLite(portal);
        if (cancelled) {
          return;
        }
        setSession(nextSession);
        setAccountType(nextSession.accountType);
        setAccountStatus(nextSession.accountStatus);

        if (!isLive) {
          setForm({
            name: nextSession.displayName,
            email: nextSession.email === "—" ? "" : nextSession.email,
            username: "preview.user",
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
          setForm({
            name: nextSession.displayName,
            email: "",
            username: "",
            phone: "",
            city: "",
            country_code: "",
            whatsapp: "",
          });
          setError(result.message ?? "Could not load editable profile fields.");
          return;
        }

        setForm(applyPayloadToForm(result.data, nextSession.displayName));
        if (result.data.account) {
          setAccountType(result.data.account.account_type || nextSession.accountType);
          setAccountStatus(result.data.account.status || nextSession.accountStatus);
        }
        setPhotoUrl(
          typeof result.data.profile?.profile_photo_url === "string"
            ? result.data.profile.profile_photo_url
            : null,
        );
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : "Could not load profile.");
          setForm((prev) => ({ ...prev }));
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
  }, [isLive, portal]);

  async function onSave() {
    if (!isLive || saving) {
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

  return (
    <div className="mx-auto max-w-2xl space-y-6" data-testid="my-profile-page">
      <section className="rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
        <h2 className="font-display text-lg font-semibold text-gray-900">Account</h2>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Signed in as</dt>
            <dd className="font-medium">{session?.displayName ?? "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Role</dt>
            <dd>{session?.roles?.[0] ?? "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Account type</dt>
            <dd className="capitalize">{(accountType || session?.accountType || "—").replaceAll("_", " ")}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Status</dt>
            <dd className="capitalize">{accountStatus || session?.accountStatus || "—"}</dd>
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
        {!loading ? (
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
                disabled={!isLive}
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
                disabled={!isLive}
                data-testid="profile-email"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Username
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.username}
                onChange={(e) => setForm((prev) => ({ ...prev, username: e.target.value }))}
                disabled={!isLive}
                data-testid="profile-username"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Phone
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.phone}
                onChange={(e) => setForm((prev) => ({ ...prev, phone: e.target.value }))}
                disabled={!isLive}
                data-testid="profile-phone"
              />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              WhatsApp
              <input
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={form.whatsapp}
                onChange={(e) => setForm((prev) => ({ ...prev, whatsapp: e.target.value }))}
                disabled={!isLive}
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
                  disabled={!isLive}
                  data-testid="profile-city"
                />
              </label>
              <label className="block text-xs font-medium text-jp-muted">
                Country code
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={form.country_code}
                  onChange={(e) => setForm((prev) => ({ ...prev, country_code: e.target.value }))}
                  disabled={!isLive}
                  data-testid="profile-country"
                />
              </label>
            </div>
            <div className="pt-2">
              <Button type="button" disabled={!isLive || saving} onClick={onSave} data-testid="profile-save">
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
