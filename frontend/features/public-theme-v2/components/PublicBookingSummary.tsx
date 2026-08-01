export type SummaryLineItem = {
  label: string;
  value: string;
};

type PublicBookingSummaryProps = {
  title?: string;
  routeLabel?: string;
  items: SummaryLineItem[];
  totalLabel: string;
  totalValue: string;
};

export function PublicBookingSummary({
  title = "Booking summary",
  routeLabel,
  items,
  totalLabel,
  totalValue,
}: PublicBookingSummaryProps) {
  return (
    <aside className="jp-v2-summary" aria-label={title}>
      <h3 className="jp-v2-summary__title">{title}</h3>
      {routeLabel ? <p style={{ margin: "0 0 var(--jp-v2-space-md)", fontSize: "var(--jp-v2-text-sm)" }}>{routeLabel}</p> : null}
      <ul className="jp-v2-summary__list">
        {items.map((item) => (
          <li key={item.label} className="jp-v2-summary__row">
            <span>{item.label}</span>
            <span>{item.value}</span>
          </li>
        ))}
      </ul>
      <div className="jp-v2-summary__total">
        <span>{totalLabel}</span>
        <span>{totalValue}</span>
      </div>
    </aside>
  );
}
