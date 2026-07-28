import { CmsModuleShell, CmsErrorShell } from "@/features/cms/cms-module-shell";
import { parseCmsQuery } from "@/lib/cms-query";
import { CmsServiceError, getCmsModule } from "@/services/cms-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import type { CmsModuleKey } from "@/types/cms";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
  module: CmsModuleKey;
};

export async function CmsPageContent({ searchParams, module }: Props) {
  const sp = await searchParams;
  const query = parseCmsQuery(sp);

  try {
    const result = await getCmsModule(query, module);
    return <CmsModuleShell module={module} result={result} />;
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="CMS" />
        <CmsModuleError error={e} />
      </PageContainer>
    );
  }
}

function CmsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="CMS" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof CmsServiceError) {
    return <CmsErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
