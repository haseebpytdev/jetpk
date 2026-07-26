"use client";

import {
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { ReportChartSegment, ReportSeries } from "@/types/report";

function AccessibleSummary({ title, segments }: { title: string; segments: { label: string; value: number; formattedValue: string }[] }) {
  if (segments.length === 0) {
    return <p className="sr-only">{title}: no data for current filters.</p>;
  }
  const summary = segments.map((s) => `${s.label} ${s.formattedValue}`).join(", ");
  return <p className="sr-only">{`${title}: ${summary}`}</p>;
}

export function ReportTimeSeriesChart({ series, description }: { series: ReportSeries | undefined; description: string }) {
  if (!series) {
    return (
      <Card>
        <CardTitle>{description}</CardTitle>
        <p className="mt-4 text-sm text-jp-muted">No data for the selected period.</p>
      </Card>
    );
  }
  const data = series.points.map((p) => ({ name: p.label, value: p.value }));
  return (
    <Card data-testid={`report-chart-${series.key}`}>
      <CardTitle>{series.label}</CardTitle>
      <CardDescription className="mt-1">{description}</CardDescription>
      <AccessibleSummary title={series.label} segments={series.points.map((p) => ({ label: p.label, value: p.value, formattedValue: String(p.value) }))} />
      {data.length === 0 ? (
        <p className="mt-4 text-sm text-jp-muted">No data for the selected period.</p>
      ) : (
        <div className="mt-4 h-64 w-full min-w-0">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
              <XAxis dataKey="name" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="value" stroke="#10B981" strokeWidth={2} dot={false} name={series.label} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}
      <table className="mt-3 w-full text-sm">
        <caption className="sr-only">{series.label} data table</caption>
        <thead>
          <tr className="text-left text-jp-muted">
            <th scope="col">Period</th>
            <th scope="col" className="text-right">
              Value
            </th>
          </tr>
        </thead>
        <tbody>
          {series.points.map((p) => (
            <tr key={p.periodStart}>
              <td>{p.label}</td>
              <td className="text-right tabular-nums">{p.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

export function ReportDonutChart({
  title,
  description,
  chartKey,
  segments,
}: {
  title: string;
  description: string;
  chartKey: string;
  segments: ReportChartSegment[];
}) {
  return (
    <Card data-testid={`report-chart-${chartKey}`}>
      <CardTitle>{title}</CardTitle>
      <CardDescription className="mt-1">{description}</CardDescription>
      <AccessibleSummary title={title} segments={segments} />
      {segments.length === 0 ? (
        <p className="mt-4 text-sm text-jp-muted">No data for the selected period.</p>
      ) : (
        <div className="mt-4 h-64 w-full min-w-0">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie data={segments} dataKey="value" nameKey="label" innerRadius={55} outerRadius={90} paddingAngle={2}>
                {segments.map((entry) => (
                  <Cell key={entry.id} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>
      )}
      <ul className="mt-3 space-y-1 text-sm" data-testid={`report-chart-${chartKey}-segments`}>
        {segments.map((s) => (
          <li key={s.id} className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-2">
              <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: s.color }} aria-hidden />
              {s.label}
            </span>
            <span className="tabular-nums text-jp-muted">
              {s.formattedValue}
              {s.sharePercent !== null ? ` (${s.sharePercent}%)` : ""}
            </span>
          </li>
        ))}
      </ul>
    </Card>
  );
}

export function ReportBarList({
  title,
  description,
  chartKey,
  segments,
}: {
  title: string;
  description: string;
  chartKey: string;
  segments: ReportChartSegment[];
}) {
  const max = Math.max(...segments.map((s) => s.value), 1);
  return (
    <Card data-testid={`report-chart-${chartKey}`}>
      <CardTitle>{title}</CardTitle>
      <CardDescription className="mt-1">{description}</CardDescription>
      <AccessibleSummary title={title} segments={segments} />
      {segments.length === 0 ? (
        <p className="mt-4 text-sm text-jp-muted">No data for the selected period.</p>
      ) : (
        <ul className="mt-4 space-y-3">
          {segments.map((s) => (
            <li key={s.id}>
              <div className="flex items-center justify-between gap-2 text-sm">
                <span>{s.label}</span>
                <span className="tabular-nums text-jp-muted">{s.formattedValue}</span>
              </div>
              <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100" role="img" aria-label={`${s.label} ${s.formattedValue}`}>
                <div className="h-full rounded-full" style={{ width: `${(s.value / max) * 100}%`, backgroundColor: s.color }} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
