import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import type { PublicSession } from "@/types/session";

type CustomerPortalPlaceholderProps = {
  session: PublicSession;
  title?: string;
};

export function CustomerPortalPlaceholder({
  session,
  title = "Customer portal",
}: CustomerPortalPlaceholderProps) {
  return (
    <PublicShell session={session}>
      <PageContainer className="py-16">
        <div className="mx-auto max-w-2xl rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center shadow-jp-sm">
          <h1 className="text-jp-h2 font-bold text-jp-text">{title}</h1>
          <p className="mt-3 text-jp-sm text-jp-muted">
            The JetPakistan customer dashboard is coming in a later phase. Laravel remains the final authorization guard
            for bookings and account data.
          </p>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
