import type { ReactNode } from "react";

type Width = "default" | "narrow" | "wide";

type PublicContainerProps = {
  children: ReactNode;
  width?: Width;
  className?: string;
};

const widthClass: Record<Width, string> = {
  default: "jp-v2-container",
  narrow: "jp-v2-container jp-v2-container--narrow",
  wide: "jp-v2-container jp-v2-container--wide",
};

export function PublicContainer({ children, width = "default", className }: PublicContainerProps) {
  return (
    <div className={[widthClass[width], className].filter(Boolean).join(" ")}>
      {children}
    </div>
  );
}
