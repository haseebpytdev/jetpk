"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardPortal } from "@/lib/portal-context";
import { Button } from "@/components/ui/button";

type ProfileFormState = {
  name: string;
  email: string;
  username: string;
  phone: string;
  city: string;
  country_code: string;
};

type ProfilePayload = {
  ok?: boolean;
  message?: string;
  profile?: {
    name?: string;
    email?: string;
    username?: string;
    phone?: string | null;
    city?: string | null;
    country_code?: string | null;
    photo_url?: string | null;
  };
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
  country_code: "",
};

async function fetchSessionLite(portal: string): Promise<SessionLite> {
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
    return {
      displayName: "Session unavailable",
      email: "—",
      roles: [],
      accountType: "unknown",
      accountStatus: "unknown",
    };
  }
  return {
    displayName: payload.data.displayName ?? "Signed in",
    email: payload.data.email ?? "—",
    roles: payload.data.roles ?? [],
    accountType: payload.data.accountType ?? "unknown",
    accountStatus: payload.data.accountStatus ?? "unknown",
  };
}

export function ProfilePageContent() {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const router = useRouter();
  const [session, setSession] = useState<SessionLite | null>(null);
  const [form, setForm] = useState<ProfileFormState>(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [photoUrl, setPhotoUrl] = useState<string | null>(null);
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [removePhoto, setRemovePhoto] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);
      const nextSession = await fetchSessionLite(portal);
      if (cancelled) {
        return;
      }
      setSession(nextSession);

      if (!isLive) {
        setForm({
          name: nextSession.displayName,
          email: nextSession.email === "—" ? "" : nextSession.email,
          username: "preview.user",
          phone: "",
          city: "",
          country_code: "PK",
        });
        setLoading(false);
        return;
      }

      const result = await laravelRequest<ProfilePayload>("/profile?format=json", {
        method: "GET",
        headers: { Accept: "application/json" },
        retryCsrfOnce: false,
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
        });
        setError(result.message ?? "Could not load editable profile fields.");
        setLoading(false);
        return;
      }

      const profile = result.data.profile ?? {};
      setForm({
        name: String(profile.name ?? nextSession.displayName ?? ""),
        email: String(profile.email ?? ""),
        username: String(profile.username ?? ""),
        phone: String(profile.phone ?? ""),
        city: String(profile.city ?? ""),
        country_code: String(profile.country_code ?? ""),
      });
      setPhotoUrl(typeof profile.photo_url === "string" ? profile.photo_url : null);
      setLoading(false);
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
    if (removePhoto) {
      formData.append("remove_profile_photo", "1");
    }
    if (photoFile) {
      formData.append("profile_photo", photoFile);
    }

    const result = await laravelRequest<ProfilePayload>("/profile?format=json", {
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
    const profile = result.data.profile;
    if (profile) {
      setForm((prev) => ({
        ...prev,
        name: String(profile.name ?? prev.name),
        email: String(profile.email ?? prev.email),
        username: String(profile.username ?? prev.username),
        phone: String(profile.phone ?? ""),
        city: String(profile.city ?? ""),
        country_code: String(profile.country_code ?? ""),
      }));
      setPhotoUrl(typeof profile.photo_url === "string" ? profile.photo_url : null);
    }
    setPhotoFile(null);
    setRemovePhoto(false);
    router.refresh();
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
            <dd className="capitalize">{session?.accountType?.replaceAll("_", " ") ?? "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Status</dt>
            <dd className="capitalize">{session?.accountStatus ?? "—"}</dd>
          </div>
        </dl>
        <p className="mt-3 text-xs text-jp-muted">
          Role, permissions, and protected account state are not editable here.
        </p>
        <button
          type="button"
          className="mt-4 text-sm font-medium text-jp-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
          onClick={() => window.dispatchEvent(new Event("jp-backoffice-tour-restart"))}
          data-testid="profile-take-dashboard-tour"
        >
          Take Dashboard Tour
        </button>
      </section>

      <section className="rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
        <h2 className="font-display text-lg font-semibold text-gray-900">Contact profile</h2>
        {loading ? <p className="mt-3 text-sm text-jp-muted">Loading profile…</p> : null}
        {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
        {success ? <p className="mt-3 text-sm text-green-700">{success}</p> : null}
        {!loading ? (
          <div className="mt-4 grid gap-3">
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 overflow-hidden rounded-full border border-jp-border bg-gray-50">
                {photoUrl && !removePhoto ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photoUrl} alt="Profile photo" className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full w-full items-center justify-center text-xs text-jp-muted">No photo</div>
                )}
              </div>
              <div className="space-y-2">
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  disabled={!isLive}
                  data-testid="profile-photo"
                  onChange={(e) => {
                    setPhotoFile(e.target.files?.[0] ?? null);
                    setRemovePhoto(false);
                    const file = e.target.files?.[0];
                    if (file) {
                      setPhotoUrl(URL.createObjectURL(file));
                    }
                  }}
                />
                <button
                  type="button"
                  className="text-xs text-red-700 underline disabled:opacity-50"
                  disabled={!isLive || (!photoUrl && !photoFile)}
                  onClick={() => {
                    setRemovePhoto(true);
                    setPhotoFile(null);
                    setPhotoUrl(null);
                  }}
                >
                  Remove photo
                </button>
                <p className="text-xs text-jp-muted">JPEG, PNG, WebP or GIF. Max 2 MB. City does not affect security-event location.</p>
              </div>
            </div>
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
