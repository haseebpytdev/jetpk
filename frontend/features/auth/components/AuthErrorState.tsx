import { ErrorState } from "@/components/ui/ErrorState";

type AuthErrorStateProps = {
  message: string;
  onRetry?: () => void;
};

export function AuthErrorState({ message, onRetry }: AuthErrorStateProps) {
  return (
    <ErrorState
      message={message}
      onRetry={onRetry}
      testId="auth-error-state"
      className="text-left"
    />
  );
}
