import type { FareFamilyOption } from "../types";

type FareFamilyDetailsProps = {
  options: FareFamilyOption[];
  selectedKey: string;
  onSelect: (key: string) => void;
  disabled?: boolean;
};

export function FareFamilyDetails({ options, selectedKey, onSelect, disabled }: FareFamilyDetailsProps) {
  if (options.length <= 1) return null;

  return (
    <section data-testid="fare-family-details" aria-labelledby="fare-family-heading">
      <h3 id="fare-family-heading" className="text-sm font-semibold text-jp-text">
        Fare options
      </h3>
      <div className="mt-2 flex gap-2 overflow-x-auto pb-1">
        {options.map((option) => {
          const selected = option.option_key === selectedKey;
          return (
            <button
              key={option.option_key}
              type="button"
              disabled={disabled}
              aria-pressed={selected}
              onClick={() => onSelect(option.option_key)}
              className={`min-w-[9rem] shrink-0 rounded-jp-md border px-3 py-2 text-left text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary ${
                selected ? "border-jp-primary bg-jp-primary/5" : "border-jp-border bg-jp-surface"
              }`}
            >
              <p className="font-medium text-jp-text">{option.brand_name ?? option.name ?? "Fare"}</p>
              {option.price_display ? <p className="text-jp-text-muted">{option.price_display}</p> : null}
              {option.baggage ? <p className="mt-1 text-xs text-jp-text-muted">{option.baggage}</p> : null}
            </button>
          );
        })}
      </div>
    </section>
  );
}
