import { PageContainer } from "@/components/layout/PageContainer";

/** Soft-nav: show shell immediately while support RSC streams. */
export default function SupportLoading() {
  return (
    <PageContainer className="py-jp-4xl">
      <div className="mt-jp-xl min-h-[12rem] animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
    </PageContainer>
  );
}
