"use client";

import { RouteNavProgress } from "@/features/motion";
import { ToastProvider } from "@/components/ui/Toast";
import { type ReactNode } from "react";

export function AppInteractionProviders({ children }: { children: ReactNode }) {
  return (
    <ToastProvider>
      <RouteNavProgress />
      {children}
    </ToastProvider>
  );
}
