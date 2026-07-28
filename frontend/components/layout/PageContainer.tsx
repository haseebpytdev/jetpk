import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type PageContainerProps = {
  children: ReactNode;
  className?: string;
};

export function PageContainer({ children, className }: PageContainerProps) {
  return (
    <div className={cn("mx-auto w-full max-w-jp-container px-jp-xl", className)}>{children}</div>
  );
}
