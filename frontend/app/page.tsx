import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { getPublicSession } from "@/services/session";

export default async function HomePage() {
  const session = await getPublicSession();

  return (
    <PublicShell session={session}>
      <HomepageContent />
    </PublicShell>
  );
}
