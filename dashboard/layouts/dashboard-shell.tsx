"use client";

import { useState, type ReactNode } from "react";
import { DashboardHeader } from "@/components/dashboard/header";
import { DashboardSidebar } from "@/components/dashboard/sidebar";

export function DashboardShell({ children }: { children: ReactNode }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-jp-surface text-gray-900">
      <DashboardSidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
      <div className="flex min-w-0 flex-1 flex-col">
        <DashboardHeader onMenuClick={() => setSidebarOpen(true)} />
        <main className="flex-1 overflow-x-hidden p-4 sm:p-6">{children}</main>
        <footer className="flex flex-col gap-2 border-t border-jp-border bg-white px-4 py-4 text-xs text-jp-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <span>© 2026 JetPakistan.pk — preview dashboard</span>
          <span>Version 2.0.0-preview</span>
        </footer>
      </div>
    </div>
  );
}
