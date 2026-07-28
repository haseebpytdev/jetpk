import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { getPublicSession } from "@/services/session";

export default async function PublicGroupLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();

  return <PublicShell session={session}>{children}</PublicShell>;
}
