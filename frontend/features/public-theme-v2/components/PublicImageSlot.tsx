import type { ImgHTMLAttributes } from "react";

type PublicImageSlotProps = Omit<ImgHTMLAttributes<HTMLImageElement>, "alt"> & {
  alt: string;
};

export function PublicImageSlot({ alt, className, ...props }: PublicImageSlotProps) {
  return (
    <div className={["jp-v2-image-slot", className].filter(Boolean).join(" ")}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img alt={alt} loading="lazy" {...props} />
    </div>
  );
}
