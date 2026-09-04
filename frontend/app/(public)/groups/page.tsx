import { GroupsLandingPage } from "@/features/group-ticketing/components/GroupsLandingPage";

export const revalidate = 60;
export const dynamic = "force-static";

/**
 * Groups discovery landing. Direct client import (no next/dynamic) so soft-nav
 * Link clicks stay client-side; next/dynamic previously correlated with full
 * document navigations in soft-nav matrix.
 */
export default function GroupsHubPage() {
  return <GroupsLandingPage />;
}
