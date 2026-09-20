/** Soft-nav: instant pending UI when entering the (public) segment from `/`. */
export default function PublicSegmentLoading() {
  return (
    <div className="mx-auto w-full max-w-jp-container px-jp-xl py-jp-4xl">
      <div className="min-h-[16rem] animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
    </div>
  );
}
