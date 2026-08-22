import { Suspense } from "react";
import { IntegrationsPageShell } from "@/features/integrations/integrations-page-shell";

export const metadata = { title: "Integrations — JetPakistan Dashboard" };

export default function IntegrationsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-jp-muted">Loading integrations…</div>}>
      <IntegrationsPageShell />
    </Suspense>
  );
}
