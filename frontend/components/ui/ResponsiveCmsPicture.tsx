"use client";

import { ImageSlot } from "@/components/ui/ImageSlot";
import { cn } from "@/lib/cn";

export type ResponsiveCmsSource = {
  type: string;
  srcSet?: string;
  srcset?: string;
  media?: string;
};

export type ResponsiveCmsMedia = {
  fallbackUrl?: string;
  fallback_url?: string;
  width?: number;
  height?: number;
  sizes?: string;
  sources?: ResponsiveCmsSource[];
  hasResponsiveVariants?: boolean;
  has_responsive_variants?: boolean;
  preloads?: Array<{ href: string; type?: string | null; media?: string }>;
};

type ResponsiveCmsPictureProps = {
  src: string;
  alt: string;
  width: number;
  height: number;
  sizes: string;
  responsive?: ResponsiveCmsMedia | null;
  priority?: boolean;
  className?: string;
  objectFit?: "cover" | "contain";
  objectPosition?: string;
  fillContainer?: boolean;
  fallbackLabel?: string;
  brandedFallback?: boolean;
  decorative?: boolean;
  "data-testid"?: string;
};

function normalizeSources(responsive?: ResponsiveCmsMedia | null): ResponsiveCmsSource[] {
  if (!responsive?.sources?.length) return [];
  return responsive.sources
    .map((source) => ({
      type: source.type,
      srcSet: source.srcSet ?? source.srcset ?? "",
      media: source.media,
    }))
    .filter((source) => source.srcSet.trim() !== "");
}

/**
 * Homepage-scoped CMS picture element. Falls back to ImageSlot when no derivatives exist.
 * Does not change global ImageSlot /storage bypass behavior.
 */
export function ResponsiveCmsPicture({
  src,
  alt,
  width,
  height,
  sizes,
  responsive,
  priority = false,
  className,
  objectFit = "cover",
  objectPosition = "center",
  fillContainer = false,
  fallbackLabel,
  brandedFallback = false,
  decorative = false,
  "data-testid": testId,
}: ResponsiveCmsPictureProps) {
  const sources = normalizeSources(responsive);
  const fallback =
    (responsive?.fallbackUrl ?? responsive?.fallback_url ?? src).trim() || src;
  const resolvedWidth = responsive?.width && responsive.width > 0 ? responsive.width : width;
  const resolvedHeight = responsive?.height && responsive.height > 0 ? responsive.height : height;
  const resolvedSizes = responsive?.sizes?.trim() || sizes;

  if (sources.length === 0) {
    return (
      <ImageSlot
        src={src}
        alt={alt}
        width={resolvedWidth}
        height={resolvedHeight}
        sizes={resolvedSizes}
        priority={priority}
        className={className}
        objectFit={objectFit}
        objectPosition={objectPosition}
        fillContainer={fillContainer}
        fallbackLabel={fallbackLabel}
        brandedFallback={brandedFallback}
        decorative={decorative}
      />
    );
  }

  return (
    <picture
      className={cn(fillContainer ? "absolute inset-0 block h-full w-full" : "block h-full w-full", className)}
      data-testid={testId ?? "responsive-cms-picture"}
      data-responsive-cms="1"
    >
      {sources.map((source, index) => (
        <source
          key={`${source.type}-${source.media ?? "w"}-${index}`}
          type={source.type}
          media={source.media}
          srcSet={source.srcSet}
          sizes={source.media ? undefined : resolvedSizes}
        />
      ))}
      <img
        src={fallback}
        alt={decorative ? "" : alt}
        width={resolvedWidth}
        height={resolvedHeight}
        sizes={resolvedSizes}
        loading={priority ? "eager" : "lazy"}
        fetchPriority={priority ? "high" : "low"}
        decoding="async"
        className={cn(
          "h-full w-full",
          objectFit === "contain" ? "object-contain" : "object-cover",
        )}
        style={{ objectPosition }}
      />
    </picture>
  );
}
