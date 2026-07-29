import { PrimaryButton } from "@/components/ui/PrimaryButton";

type ContinueToPassengersButtonProps = {
  loading?: boolean;
  disabled?: boolean;
  label?: string;
  onClick: () => void;
};

export function ContinueToPassengersButton({
  loading,
  disabled,
  label = "Continue to passengers",
  onClick,
}: ContinueToPassengersButtonProps) {
  return (
    <PrimaryButton
      type="button"
      className="w-full"
      data-testid="continue-to-passengers"
      disabled={disabled || loading}
      aria-busy={loading}
      onClick={onClick}
    >
      {loading ? "Confirming fare…" : label}
    </PrimaryButton>
  );
}
