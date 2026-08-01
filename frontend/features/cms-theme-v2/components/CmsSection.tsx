import type { ReactNode } from "react";
import { PublicContainer } from "@/features/public-theme-v2";

type CmsSectionProps = {
  children: ReactNode;
  width?: "default" | "narrow" | "wide";
};

export function CmsSection({ children, width = "default" }: CmsSectionProps) {
  return (
    <PublicContainer width={width}>
      {children}
    </PublicContainer>
  );
}
