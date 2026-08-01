import { TableLoadingSkeleton } from "@/components/ui/LoadingRegion";

export default function Loading() {
  return (
    <div className="p-4">
      <TableLoadingSkeleton label="Loading agent bookings" />
    </div>
  );
}
