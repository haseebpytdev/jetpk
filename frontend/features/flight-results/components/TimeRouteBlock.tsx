import { cn } from "@/lib/cn";
import { StopsAndLayover } from "./StopsAndLayover";

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
  className,
}: TimeRouteBlockProps) {
  return (
    <div className={cn("grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-4", className)}>
      <div className="text-left">
        <p className="text-lg font-semibold text-jp-text sm:text-xl">{departureTime ?? "—"}</p>
        <p className="text-xs text-jp-text-muted sm:text-sm">{originCode ?? "—"}</p>
      </div>
      <div className="flex min-w-[7rem] flex-col items-center px-1 text-center" data-testid="center-route-block">
        <span className="text-[10px] text-jp-text-muted sm:text-xs">{duration ?? ""}</span>
        <span className="mt-1 flex w-full items-center" aria-hidden="true"><span className="h-px flex-1 bg-jp-border" /><span className="px-1 text-jp-primary">✈</span><span className="h-px flex-1 bg-jp-border" /></span>
        <StopsAndLayover stops={stops} stopsLabel={stopsLabel} viaCodes={viaCodes} layoverSummary={layoverSummary} className="mt-1" />
      </div>
      <div className="text-right">
        <p className="text-lg font-semibold text-jp-text sm:text-xl">
          {arrivalTime ?? "—"}
          {arrivalDayOffset ? (
            <span className="ml-1 align-super text-[10px] font-normal text-jp-text-muted">{arrivalDayOffset}</span>
          ) : null}
        </p>
        <p className="text-xs text-jp-text-muted sm:text-sm">{destinationCode ?? "—"}</p>
      </div>
    </div>
  );
}
