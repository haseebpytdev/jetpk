"use client";

import { cn } from "@/lib/cn";
import Image from "next/image";
import { useState } from "react";

type AssetSlotProps = {
  src?: string | null;
  alt: string;
  width: number;
  height: number;
  className?: string;
  objectFit?: "cover" | "contain";
  objectPosition?: string;
  priority?: boolean;
  sizes?: string;
  /** Hero band uses a calm branded gradient when no approved asset is present. */
  variant?: "default" | "hero-neutral" | "card-neutral";
};

export function AssetSlot({
  src,
  alt,
  width,
  height,
  className,
  objectFit = "cover",
  objectPosition = "center",
  priority = false,
  sizes,
  variant = "default",
}: AssetSlotProps) {
  const [failed, setFailed] = useState(false);
  const hasImage = Boolean(src) && !failed;

  if (!hasImage) {
    return (
      <div
        className={cn(
          variant === "hero-neutral"
            ? "bg-gradient-to-br from-[#0d4d32] via-[#1a6b45] to-[#4a9fd4]"
            : variant === "card-neutral"
              ? "bg-gradient-to-br from-jp-primary-soft to-jp-surface-muted"
              : "bg-gradient-to-br from-jp-primary-soft/80 to-jp-surface-muted",
          className,
        )}
        style={{ width: "100%", height: "100%" }}
        data-asset-state="missing"
        role="img"
        aria-label={alt}
      />
    );
  }

  return (
    <div className={cn("relative h-full w-full overflow-hidden", className)} data-asset-state="image">
      <Image
        src={src!}
        alt={alt}
        width={width}
        height={height}
        sizes={sizes}
        priority={priority}
        loading={priority ? undefined : "lazy"}
        className={cn(
          "h-full w-full",
          objectFit === "cover" ? "object-cover" : "object-contain",
        )}
        style={{ objectPosition }}
        onError={() => setFailed(true)}
      />
    </div>
  );
}
