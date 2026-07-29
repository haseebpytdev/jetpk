type TurnstileValidationMessageProps = {
  message: string;
  id?: string;
};

export function TurnstileValidationMessage({ message, id }: TurnstileValidationMessageProps) {
  return (
    <p id={id} className="text-jp-sm text-red-700" role="alert" data-testid="turnstile-validation-message">
      {message}
    </p>
  );
}
