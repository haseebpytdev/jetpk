"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  loadCommunicationSettings,
  testCommunicationEmail,
  updateCommunicationSettings,
} from "@/services/operational-api";

type SmtpFormState = {
  email_enabled: boolean;
  smtp_enabled: boolean;
  smtp_host: string;
  smtp_port: string;
  smtp_username: string;
  smtp_password: string;
  smtp_encryption: "tls" | "ssl" | "none";
  mail_from_name: string;
  mail_from_email: string;
  reply_to_email: string;
  smtp_password_masked: string | null;
  smtp_password_set: boolean;
};

const emptyForm: SmtpFormState = {
  email_enabled: false,
  smtp_enabled: false,
  smtp_host: "",
  smtp_port: "587",
  smtp_username: "",
  smtp_password: "",
  smtp_encryption: "tls",
  mail_from_name: "",
  mail_from_email: "",
  reply_to_email: "",
  smtp_password_masked: null,
  smtp_password_set: false,
};

function settingsToForm(settings: Record<string, unknown> | undefined): SmtpFormState {
  if (!settings) return emptyForm;
  const encryption = String(settings.smtp_encryption ?? "tls");
  return {
    email_enabled: Boolean(settings.email_enabled),
    smtp_enabled: Boolean(settings.smtp_enabled),
    smtp_host: String(settings.smtp_host ?? ""),
    smtp_port: String(settings.smtp_port ?? "587"),
    smtp_username: String(settings.smtp_username ?? ""),
    smtp_password: "",
    smtp_encryption: encryption === "ssl" || encryption === "none" ? encryption : "tls",
    mail_from_name: String(settings.mail_from_name ?? ""),
    mail_from_email: String(settings.mail_from_email ?? ""),
    reply_to_email: String(settings.reply_to_email ?? ""),
    smtp_password_masked: typeof settings.smtp_password_masked === "string" ? settings.smtp_password_masked : null,
    smtp_password_set: Boolean(settings.smtp_password_set),
  };
}

export function CommunicationsSmtpWorkspace() {
  const isLive = useDashboardLiveMode();
  const [form, setForm] = useState<SmtpFormState>(emptyForm);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [testRecipient, setTestRecipient] = useState("");
  const [testConfirmed, setTestConfirmed] = useState(false);

  useEffect(() => {
    if (!isLive) return;
    void (async () => {
      const result = await loadCommunicationSettings();
      if (!result.ok) {
        setError(result.message ?? "Unable to load communication settings.");
        return;
      }
      setForm(settingsToForm(result.settings as Record<string, unknown> | undefined));
    })();
  }, [isLive]);

  function patch<K extends keyof SmtpFormState>(key: K, value: SmtpFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function onSave(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    setBusy("save");
    setError(null);
    setMessage(null);
    const payload: Record<string, unknown> = {
      email_enabled: form.email_enabled,
      smtp_enabled: form.smtp_enabled,
      smtp_host: form.smtp_host || null,
      smtp_port: form.smtp_port ? Number(form.smtp_port) : null,
      smtp_username: form.smtp_username || null,
      smtp_encryption: form.smtp_encryption,
      mail_from_name: form.mail_from_name || null,
      mail_from_email: form.mail_from_email || null,
      reply_to_email: form.reply_to_email || null,
    };
    if (form.smtp_password.trim() !== "") {
      payload.smtp_password = form.smtp_password;
    }
    const result = await updateCommunicationSettings(payload);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Save failed.");
      return;
    }
    setForm(settingsToForm(result.settings as Record<string, unknown> | undefined));
    setMessage("SMTP / communications settings saved. Secrets remain masked.");
  }

  async function onTest() {
    if (busy) return;
    if (!testConfirmed) {
      setError("Confirm the test recipient before sending. No customer emails are sent from this control.");
      return;
    }
    if (!testRecipient.trim()) {
      setError("Enter a confirmed operator recipient email.");
      return;
    }
    setBusy("test");
    setError(null);
    setMessage(null);
    const result = await testCommunicationEmail(testRecipient.trim());
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? result.error_message ?? "Test email failed.");
      return;
    }
    setMessage(result.message ?? "Test email sent to the confirmed recipient only.");
    setTestConfirmed(false);
  }

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="smtp-settings-preview">
        SMTP / communications settings are available in live dashboard mode only. Production SMTP is not modified by
        this UI without an explicit Save.
      </p>
    );
  }

  return (
    <section className="space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="communications-smtp-workspace">
      <div>
        <h3 className="text-sm font-semibold text-gray-900">SMTP / communications</h3>
        <p className="mt-1 text-xs text-jp-muted">
          Wired to AgencyCommunicationSettingsController. Passwords are never echoed after save. Test send requires an
          explicit recipient confirmation and never broadcasts to customers.
        </p>
      </div>

      {error ? (
        <p className="text-sm text-red-600" role="alert">
          {error}
        </p>
      ) : null}
      {message ? <p className="text-sm text-green-700">{message}</p> : null}

      <form onSubmit={(e) => void onSave(e)} className="grid gap-3 sm:grid-cols-2" data-testid="smtp-settings-form">
        <label className="flex items-center gap-2 text-xs sm:col-span-2">
          <input type="checkbox" checked={form.email_enabled} onChange={(e) => patch("email_enabled", e.target.checked)} />
          Email channel enabled
        </label>
        <label className="flex items-center gap-2 text-xs sm:col-span-2">
          <input type="checkbox" checked={form.smtp_enabled} onChange={(e) => patch("smtp_enabled", e.target.checked)} />
          Custom SMTP enabled
        </label>
        <label className="block text-xs">
          SMTP host
          <input
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.smtp_host}
            onChange={(e) => patch("smtp_host", e.target.value)}
            data-testid="smtp-host"
          />
        </label>
        <label className="block text-xs">
          SMTP port
          <input
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.smtp_port}
            onChange={(e) => patch("smtp_port", e.target.value)}
            data-testid="smtp-port"
          />
        </label>
        <label className="block text-xs">
          SMTP username
          <input
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.smtp_username}
            onChange={(e) => patch("smtp_username", e.target.value)}
            autoComplete="off"
            data-testid="smtp-username"
          />
        </label>
        <label className="block text-xs">
          SMTP password {form.smtp_password_set ? `(stored: ${form.smtp_password_masked ?? "********"})` : "(not set)"}
          <input
            type="password"
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.smtp_password}
            onChange={(e) => patch("smtp_password", e.target.value)}
            placeholder={form.smtp_password_set ? "Leave blank to keep existing" : "Enter password"}
            autoComplete="new-password"
            data-testid="smtp-password"
          />
        </label>
        <label className="block text-xs">
          Encryption
          <select
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.smtp_encryption}
            onChange={(e) => patch("smtp_encryption", e.target.value as SmtpFormState["smtp_encryption"])}
            data-testid="smtp-encryption"
          >
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
            <option value="none">None</option>
          </select>
        </label>
        <label className="block text-xs">
          From name
          <input
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.mail_from_name}
            onChange={(e) => patch("mail_from_name", e.target.value)}
            data-testid="smtp-from-name"
          />
        </label>
        <label className="block text-xs">
          From email
          <input
            type="email"
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.mail_from_email}
            onChange={(e) => patch("mail_from_email", e.target.value)}
            data-testid="smtp-from-email"
          />
        </label>
        <label className="block text-xs">
          Reply-to email
          <input
            type="email"
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={form.reply_to_email}
            onChange={(e) => patch("reply_to_email", e.target.value)}
            data-testid="smtp-reply-to"
          />
        </label>
        <div className="sm:col-span-2">
          <button
            type="submit"
            className="min-h-11 rounded-xl bg-jp-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
            disabled={busy !== null}
            data-testid="smtp-save"
          >
            {busy === "save" ? "Saving…" : "Save SMTP settings"}
          </button>
        </div>
      </form>

      <div className="space-y-3 rounded-lg border border-dashed border-jp-border p-3" data-testid="smtp-test-panel">
        <p className="text-xs font-medium text-gray-900">Safe test send</p>
        <p className="text-xs text-jp-muted">
          Sends only to the address you type below after confirmation. Does not email customers or change production mail
          transport until Save is used.
        </p>
        <label className="block text-xs">
          Test recipient
          <input
            type="email"
            className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
            value={testRecipient}
            onChange={(e) => setTestRecipient(e.target.value)}
            data-testid="smtp-test-recipient"
          />
        </label>
        <label className="flex items-start gap-2 text-xs">
          <input
            type="checkbox"
            checked={testConfirmed}
            onChange={(e) => setTestConfirmed(e.target.checked)}
            data-testid="smtp-test-confirmation"
          />
          <span>I confirm this recipient is an operator mailbox. Do not send customer emails from this control.</span>
        </label>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-4 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          onClick={() => void onTest()}
          data-testid="smtp-test-send"
        >
          {busy === "test" ? "Sending test…" : "Send test email"}
        </button>
      </div>
    </section>
  );
}
