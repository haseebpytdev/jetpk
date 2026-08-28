import { cn } from "@/lib/cn";
import { StopsAndLayover, type LayoverDetail } from "./StopsAndLayover";

type TimeRouteBlockProps = {
  departureTime?: string;
  arrivalTime?: string;
  arrivalDayOffset?: string;
  originCode?: string;
  destinationCode?: string;
  duration?: string;
  stops?: number;
  stopsLabel?: string;
  viaCodes?: string[];
  layoverSummary?: string[];
  layovers?: LayoverDetail[];
  /** Tighter typography for paired/horizontal journey layouts. */
  compact?: boolean;
  /** Hide stops/layover row (per-segment legs always omit journey-level stop labels). */
  hideStops?: boolean;
  className?: string;
};

export function TimeRouteBlock({
  departureTime,
  arrivalTime,
  arrivalDayOffset,
  originCode,
  destinationCode,
  duration,
  stops = 0,
  stopsLabel,
  viaCodes,
  layoverSummary,
  layovers,
  compact = false,
  hideStops = false,
  className,
}: TimeRouteBlockProps) {
  return (
    <div className={cn("grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-4", className)}>
      <div className="text-left">
        <p className={cn("font-semibold text-jp-text", compact ? "text-base sm:text-lg" : "text-lg sm:text-xl")}>
          {departureTime ?? "—"}
        </p>
        <p className={cn("text-jp-text-muted", compact ? "text-[11px] sm:text-xs" : "text-xs sm:text-sm")}>
          {originCode ?? "—"}
        </p>
      </div>
      <div
        className={cn(
          "flex max-w-full flex-col items-center overflow-visible px-1 text-center",
          compact ? "min-w-[5.5rem]" : "min-w-[7rem]",
        )}
        data-testid="center-route-block"
      >
        <span className="text-[10px] text-jp-text-muted sm:text-xs">{duration ?? ""}</span>
        <span className="mt-1 flex w-full items-center" aria-hidden="true"><span className="h-px flex-1 bg-jp-border" /><span className="px-1 text-jp-primary">✈</span><span className="h-px flex-1 bg-jp-border" /></span>
        {!hideStops ? (
          <StopsAndLayover
            stops={stops}
            stopsLabel={stopsLabel}
            viaCodes={viaCodes}
            layoverSummary={layoverSummary}
            layovers={layovers}
            className="mt-1"
          />
        ) : null}
      </div>
      <div className="text-right">
        <p className={cn("font-semibold text-jp-text", compact ? "text-base sm:text-lg" : "text-lg sm:text-xl")}>
          {arrivalTime ?? "—"}
          {arrivalDayOffset ? (
            <span className="ml-1 align-super text-[10px] font-normal text-jp-text-muted">{arrivalDayOffset}</span>
          ) : null}
        </p>
        <p className={cn("text-jp-text-muted", compact ? "text-[11px] sm:text-xs" : "text-xs sm:text-sm")}>
          {destinationCode ?? "—"}
        </p>
      </div>
    </div>
  );
}
