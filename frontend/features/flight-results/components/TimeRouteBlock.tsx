import { cn } from "@/lib/cn";

type TimeRouteBlockProps = {
  departureTime?: string;
  arrivalTime?: string;
  arrivalDayOffset?: string;
  originCode?: string;
  destinationCode?: string;
  duration?: string;
  className?: string;
};

export function TimeRouteBlock({
  departureTime,
  arrivalTime,
  arrivalDayOffset,
  originCode,
  destinationCode,
  duration,
  className,
}: TimeRouteBlockProps) {
  return (
    <div className={cn("grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-4", className)}>
      <div className="text-left">
        <p className="text-lg font-semibold text-jp-text sm:text-xl">{departureTime ?? "—"}</p>
        <p className="text-xs text-jp-text-muted sm:text-sm">{originCode ?? "—"}</p>
      </div>
      <div className="flex flex-col items-center px-1 text-center">
        <span className="text-[10px] text-jp-text-muted sm:text-xs">{duration ?? ""}</span>
        <span className="mt-1 h-px w-10 bg-jp-border sm:w-16" aria-hidden="true" />
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
