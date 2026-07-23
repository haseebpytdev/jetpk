import { Button } from "@/components/ui/button";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";

export function ErrorState({
  title,
  message,
  referenceId,
  onRetry,
}: {
  title: string;
  message: string;
  referenceId: string;
  onRetry?: () => void;
}) {
  return (
    <Card role="alert" className="border-red-200 bg-red-50/50">
      <CardTitle className="text-red-900">{title}</CardTitle>
      <CardDescription className="mt-2 text-red-800">{message}</CardDescription>
      <p className="mt-2 text-xs text-red-700">Reference: {referenceId}</p>
      {onRetry ? (
        <Button className="mt-4" variant="secondary" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </Card>
  );
}
