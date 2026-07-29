type PartialResultsNoticeProps = {
  warnings?: string[];
};

export function PartialResultsNotice({ warnings }: PartialResultsNoticeProps) {
  if (!warnings?.length) return null;
  return (
    <div className="rounded-jp-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
      {warnings.map((warning) => (
        <p key={warning}>{warning}</p>
      ))}
    </div>
  );
}
