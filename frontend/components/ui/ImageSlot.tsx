"use client";

import { cn } from "@/lib/cn";
import Image from "next/image";
import { useState } from "react";
import { Skeleton } from "./Skeleton";

function shouldBypassNextOptimizer(src: string): boolean {
  return (
    src.startsWith("http://") ||
    src.startsWith("https://") ||
    src.startsWith("/storage/") ||
    src.startsWith("/client-assets/") ||
    src.startsWith("/themes/")
  );
}

type ImageSlotProps = {
  src?: string | null;
  alt: string;
  decorative?: boolean;
  width: number;
  height: number;
  className?: string;
  objectFit?: "cover" | "contain";
  objectPosition?: string;
  priority?: boolean;
  sizes?: string;
  fallbackLabel?: string;
  /** Soft JetPakistan brand motif instead of a generic photo icon. */
  brandedFallback?: boolean;
};

export function ImageSlot({
  src,
  alt,
  decorative = false,
  width,
  height,
  className,
  objectFit = "cover",
  objectPosition = "center",
  priority = false,
  sizes,
  fallbackLabel = "Image unavailable",
  brandedFallback = false,
}: ImageSlotProps) {
  const [loading, setLoading] = useState(Boolean(src));
  const [failed, setFailed] = useState(false);
  const resolvedAlt = decorative ? "" : alt;
  const aspectRatio = `${width} / ${height}`;

  if (!src || failed) {
    return (
      <div
        className={cn(
          "relative flex items-center justify-center overflow-hidden rounded-jp-md border border-jp-border",
          brandedFallback
            ? "bg-gradient-to-br from-jp-brand-soft via-jp-surface to-jp-surface-muted text-jp-brand"
            : "bg-jp-surface-muted text-jp-muted",
          className,
        )}
        style={{ aspectRatio, width: "100%", maxWidth: width }}
        role={decorative ? "presentation" : "img"}
        aria-label={decorative ? undefined : fallbackLabel}
        data-testid="image-slot-fallback"
      >
        {brandedFallback ? <BrandedFallbackMotif /> : <FallbackIcon />}
        {!decorative ? <span className="sr-only">{fallbackLabel}</span> : null}
      </div>
    );
  }

  return (
    <div
      className={cn("relative overflow-hidden rounded-jp-md", className)}
      style={{ aspectRatio, width: "100%", maxWidth: width }}
      data-testid="image-slot"
    >
      {loading ? (
        <Skeleton className="absolute inset-0 h-full w-full rounded-none" />
      ) : null}
      <Image
        src={src}
        alt={resolvedAlt}
        width={width}
        height={height}
        sizes={sizes}
        priority={priority}
        unoptimized={shouldBypassNextOptimizer(src)}
        loading={priority ? undefined : "lazy"}
        className={cn(
          "jp-image-fade-in h-full w-full",
          objectFit === "cover" ? "object-cover" : "object-contain",
        )}
        style={{ objectPosition }}
        onLoad={() => setLoading(false)}
        onError={() => {
          setLoading(false);
          setFailed(true);
        }}
      />
    </div>
  );
}

function FallbackIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-8 w-8 opacity-50" aria-hidden="true">
      <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" strokeWidth="1.5" fill="none" />
      <circle cx="8.5" cy="10" r="1.5" fill="currentColor" />
      <path d="M3 16l5-4 4 3 3-2 6 5" stroke="currentColor" strokeWidth="1.5" fill="none" />
    </svg>
  );
}

function BrandedFallbackMotif() {
  return (
    <svg viewBox="0 0 320 180" className="absolute inset-0 h-full w-full opacity-80" aria-hidden="true" fill="none">
      <circle cx="250" cy="64" r="46" stroke="currentColor" strokeWidth="1.5" className="text-jp-brand/30" />
      <path
        d="M24 128 C96 48, 168 148, 248 64"
        stroke="currentColor"
        strokeWidth="2"
        strokeDasharray="5 7"
        strokeLinecap="round"
        className="text-jp-brand/45"
      />
      <path d="M230 56 L266 72 L244 78 L238 98 Z" fill="currentColor" className="text-jp-brand/55" />
      <circle cx="248" cy="64" r="3.5" fill="currentColor" className="text-jp-brand" />
    </svg>
  );
}
