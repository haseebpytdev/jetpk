import { CmsModuleShell, CmsErrorShell } from "@/features/cms/cms-module-shell";
import { parseCmsQuery } from "@/lib/cms-query";
import { CmsServiceError, getCmsModule } from "@/services/cms-service";
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
    if (e instanceof CmsServiceError) {
      return <CmsErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
