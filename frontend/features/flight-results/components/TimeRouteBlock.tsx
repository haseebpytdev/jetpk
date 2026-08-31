import { cn } from "@/lib/cn";
import { compactEndpointDate } from "../utils/endpoint-date";
import { StopsAndLayover, type LayoverDetail } from "./StopsAndLayover";

type TimeRouteBlockProps = {
  departureTime?: string;
  arrivalTime?: string;
  arrivalDayOffset?: string;
  /** Authoritative segment/journey departure date display (not search date). */
  departureDate?: string;
  /** Authoritative segment/journey arrival date display (not search date). */
  arrivalDate?: string;
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

function EndpointColumn({
  date,
  time,
  airport,
  dayOffset,
  align,
  compact,
}: {
  date?: string;
  time?: string;
  airport?: string;
  dayOffset?: string;
  align: "left" | "right";
  compact: boolean;
}) {
  const compactDate = compactEndpointDate(date);
  return (
    <div className={cn("min-w-0", align === "left" ? "text-left" : "text-right")}>
      {compactDate ? (
        <p
          className={cn(
            "font-medium uppercase tracking-wide text-jp-text-muted",
            compact ? "text-[9px] sm:text-[10px]" : "text-[10px] sm:text-xs",
          )}
          data-testid="endpoint-date"
        >
          {compactDate}
        </p>
      ) : null}
      <p className={cn("font-semibold tabular-nums text-jp-text", compact ? "text-sm sm:text-base md:text-lg" : "text-base sm:text-lg md:text-xl")}>
        {time ?? "—"}
        {dayOffset ? (
          <span className="ml-1 align-super text-[10px] font-normal text-jp-text-muted">{dayOffset}</span>
        ) : null}
      </p>
      <p className={cn("truncate text-jp-text-muted", compact ? "text-[11px] sm:text-xs" : "text-xs sm:text-sm")}>
        {airport ?? "—"}
      </p>
    </div>
  );
}

export function TimeRouteBlock({
  departureTime,
  arrivalTime,
  arrivalDayOffset,
  departureDate,
  arrivalDate,
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
    <div className={cn("grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1.5 sm:gap-4", className)}>
      <EndpointColumn
        date={departureDate}
        time={departureTime}
        airport={originCode}
        align="left"
        compact={compact}
      />
      <div
        className={cn(
          "flex max-w-full flex-col items-center overflow-visible px-0.5 text-center sm:px-1",
          compact ? "min-w-[4.5rem] sm:min-w-[5.5rem]" : "min-w-[5rem] sm:min-w-[7rem]",
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
      <EndpointColumn
        date={arrivalDate}
        time={arrivalTime}
        airport={destinationCode}
        dayOffset={arrivalDayOffset}
        align="right"
        compact={compact}
      />
    </div>
  );
}
