import { DepositsWorkspace } from "@/features/deposits/deposits-workspace";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { getDepositsPage } from "@/services/deposit-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

export type DepositsModuleErrorKind = "unauthenticated" | "forbidden" | "unavailable" | "sanitized" | "rethrow";

export function classifyDepositsModuleError(error: unknown): DepositsModuleErrorKind {
  if (!(error instanceof ReadOnlyServiceError)) {
    return "rethrow";
  }
  const code = error.envelope.error.code;
  if (code === "unauthenticated") return "unauthenticated";
  if (code === "forbidden") return "forbidden";
  if (code === "unavailable") return "unavailable";
  return "sanitized";
}

function DepositsModuleError({ error }: { error: unknown }) {
  const kind = classifyDepositsModuleError(error);
  if (kind === "unauthenticated") return <UnauthorizedState />;
  if (kind === "forbidden") return <ForbiddenState resource="agent deposits" />;
  if (kind === "unavailable") return <ServiceUnavailableState />;
  if (kind === "sanitized" && error instanceof ReadOnlyServiceError) {
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  throw error;
}

export async function DepositsPageContent() {
  try {
    const result = await getDepositsPage();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          title="Agent deposits"
          description="Review pending agent deposit proofs and wallet postings."
        />
        <DataSourceNoticeSlot />
        <DepositsWorkspace deposits={result.deposits} />
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader
          title="Agent deposits"
          description="Review pending agent deposit proofs and wallet postings."
        />
        <DepositsModuleError error={error} />
      </PageContainer>
    );
  }
}
