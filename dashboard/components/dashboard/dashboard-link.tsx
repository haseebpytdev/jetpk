"use client";

import Link from "next/link";
import type { ComponentProps } from "react";
import { useDashboardHref } from "@/lib/dashboard-navigation";

type Props = Omit<ComponentProps<typeof Link>, "href"> & {
  href: string;
};

/** In-app link relative to the current admin/staff dashboard mount. */
export function DashboardLink({ href, ...props }: Props) {
  return <Link href={useDashboardHref(href)} {...props} />;
}
