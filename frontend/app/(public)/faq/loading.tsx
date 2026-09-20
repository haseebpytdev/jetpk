import { PageContainer } from "@/components/layout/PageContainer";

/** Soft-nav: show shell immediately while CMS RSC streams. */
export default function FaqLoading() {
  return (
    <PageContainer className="py-jp-4xl">
      <div className="mt-jp-xl min-h-[16rem] animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
    </PageContainer>
  );
}
