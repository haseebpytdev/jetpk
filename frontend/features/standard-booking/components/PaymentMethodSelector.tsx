import type { PaymentMethodCode, ReviewPaymentMethod } from "../types/review-payment";

type PaymentMethodSelectorProps = {
  methods: ReviewPaymentMethod[];
  selected: PaymentMethodCode;
  onSelect: (code: PaymentMethodCode) => void;
  disabled?: boolean;
  compact?: boolean;
};

export function PaymentMethodSelector({
  methods,
  selected,
  onSelect,
  disabled,
  compact = false,
}: PaymentMethodSelectorProps) {
  return (
    <fieldset
      className={
        compact
          ? "border-0 p-0"
          : "rounded-jp-lg border border-jp-border bg-jp-surface p-4"
      }
      data-testid="payment-method-selector"
    >
      {!compact ? <legend className="text-jp-base font-semibold">Payment option</legend> : null}
      <div className={compact ? "space-y-2" : "mt-3 space-y-3"}>
        {methods.map((method) => {
          const checked = selected === method.code;
          return (
            <label
              key={method.code}
              className={`flex cursor-pointer gap-3 rounded-jp-md border transition-colors has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary/5 ${
                compact ? "p-2.5" : "rounded-jp-lg p-4"
              } ${checked ? "border-jp-primary" : "border-jp-border"} ${
                !method.available || disabled ? "opacity-60" : ""
              }`}
              data-testid={`payment-method-${method.code}`}
            >
              <input
                type="radio"
                name="payment_method"
                value={method.code}
                checked={checked}
                disabled={disabled || !method.available}
                onChange={() => onSelect(method.code)}
                className="mt-1"
              />
              <span>
                <span className="block text-jp-sm font-semibold text-jp-text">{method.label}</span>
                {!compact ? (
                  <span className="mt-0.5 block text-jp-xs text-jp-muted">{method.description}</span>
                ) : null}
              </span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
