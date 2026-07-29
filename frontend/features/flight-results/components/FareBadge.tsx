type FareBadgeProps = {
  refundable?: boolean;
  seatsLeft?: number | null;
};

export function FareBadge({ refundable, seatsLeft }: FareBadgeProps) {
  return (
    <div className="flex flex-wrap gap-2">
      {typeof refundable === "boolean" ? (
        <span className="rounded-full bg-jp-surface-muted px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-jp-text-muted">
          {refundable ? "Refundable" : "Non-refundable"}
        </span>
      ) : null}
      {typeof seatsLeft === "number" && seatsLeft > 0 && seatsLeft < 9 ? (
        <span className="rounded-full bg-jp-accent-soft px-2 py-0.5 text-[10px] font-medium text-jp-accent-hover">
          {seatsLeft} seat{seatsLeft === 1 ? "" : "s"} left
        </span>
      ) : null}
    </div>
  );
}
