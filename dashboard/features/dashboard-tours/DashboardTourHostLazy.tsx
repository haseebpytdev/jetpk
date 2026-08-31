"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

const LazyHost = dynamic(
  () => import("./DashboardTourHost").then((mod) => mod.DashboardTourHost),
  { ssr: false, loading: () => null },
) as ComponentType<{ portal: "admin" | "staff" }>;

export function DashboardTourHostLazy({ portal }: { portal: "admin" | "staff" }) {
  return <LazyHost portal={portal} />;
}
