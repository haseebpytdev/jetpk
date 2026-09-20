import { CardListLoadingSkeleton } from "@/components/ui/LoadingRegion";

export default function Loading() {
  return (
    <div className="mx-auto max-w-jp p-4">
      <CardListLoadingSkeleton label="Loading group packages" />
    </div>
  );
}
