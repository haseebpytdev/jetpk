type CmsActionNoticeProps = {
  tone: "success" | "error";
  message: string;
  testId?: string;
};

export function CmsActionNotice({ tone, message, testId }: CmsActionNoticeProps) {
  const isSuccess = tone === "success";

  return (
    <div
      role="status"
      aria-live="polite"
      className={`rounded-xl border px-4 py-3 text-sm ${
        isSuccess ? "border-emerald-200 bg-emerald-50 text-emerald-900" : "border-red-200 bg-red-50 text-red-800"
      }`}
      data-testid={testId ?? (isSuccess ? "cms-action-success" : "cms-action-error")}
    >
      <p className="font-semibold">{isSuccess ? "Completed" : "Action failed"}</p>
      <p className="mt-1">{message}</p>
    </div>
  );
}
