"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

const LazyHost = dynamic(
  () => import("./DashboardTourHost").then((mod) => mod.DashboardTourHost),
  { ssr: false, loading: () => null },
) as ComponentType<{ portal: "customer" | "agent" }>;

/** Lazy tour host — keeps the wizard chunk out of public flight pages. */
export function DashboardTourHostLazy({ portal }: { portal: "customer" | "agent" }) {
  return <LazyHost portal={portal} />;
}
