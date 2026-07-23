import { cn } from "@/lib/utils";

const tones: Record<string, string> = {
  Confirmed: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Ticketed: "bg-blue-50 text-blue-800 ring-blue-600/20",
  "Pending Payment": "bg-amber-50 text-amber-900 ring-amber-600/20",
  Paid: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  Pending: "bg-amber-50 text-amber-900 ring-amber-600/20",
  Refunded: "bg-gray-100 text-gray-800 ring-gray-500/20",
};

export function Badge({ label, className }: { label: string; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset",
        tones[label] ?? "bg-gray-100 text-gray-800 ring-gray-500/20",
        className,
      )}
    >
      {label}
    </span>
  );
}
