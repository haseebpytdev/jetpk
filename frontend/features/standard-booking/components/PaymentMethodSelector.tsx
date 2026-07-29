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
      <div className="mt-3 space-y-2">
        {methods.map((method) => (
          <label
            key={method.code}
            className="flex cursor-pointer gap-3 rounded-jp-md border border-jp-border p-3 has-[:checked]:border-jp-primary"
            data-testid={`payment-method-${method.code}`}
          >
            <input
              type="radio"
              name="payment_method"
              value={method.code}
              checked={selected === method.code}
              disabled={disabled || !method.available}
              onChange={() => onSelect(method.code)}
              className="mt-1"
            />
            <span>
              <span className="block text-jp-sm font-semibold">{method.label}</span>
              <span className="block text-jp-xs text-jp-muted">{method.description}</span>
            </span>
          </label>
        ))}
      </div>
    </fieldset>
  );
}
