import type { PaymentMethodCode, ReviewPaymentMethod } from "../types/review-payment";

type PaymentMethodSelectorProps = {
  methods: ReviewPaymentMethod[];
  selected: PaymentMethodCode;
  onSelect: (code: PaymentMethodCode) => void;
  disabled?: boolean;
};

export function PaymentMethodSelector({ methods, selected, onSelect, disabled }: PaymentMethodSelectorProps) {
  return (
    <fieldset className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="payment-method-selector">
      <legend className="text-jp-base font-semibold">Payment option</legend>
      <div className="mt-3 space-y-3">
        {methods.map((method) => {
          const checked = selected === method.code;
          return (
            <label
              key={method.code}
              className={`flex cursor-pointer gap-3 rounded-jp-lg border p-4 transition-colors has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary/5 ${
                checked ? "border-jp-primary" : "border-jp-border"
              } ${!method.available || disabled ? "opacity-60" : ""}`}
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
                <span className="mt-0.5 block text-jp-xs text-jp-muted">{method.description}</span>
              </span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
