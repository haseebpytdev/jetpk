import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export default async function AuthGroupLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();
  return <PublicShell session={session}>{children}</PublicShell>;
}
