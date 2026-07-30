import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default async function CustomerLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();
  return <PublicShell session={session}>{children}</PublicShell>;
}
