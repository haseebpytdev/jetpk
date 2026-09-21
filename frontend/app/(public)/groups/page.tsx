import type { Metadata } from "next";
import { GroupsLandingPage } from "@/features/group-ticketing/components/GroupsLandingPage";
import { publicSeoToMetadata } from "@/features/public-content";

export const revalidate = 300;
export const dynamic = "force-static";

export const metadata: Metadata = publicSeoToMetadata(
  {
    title: "Group ticketing — JetPakistan",
    description: "Explore JetPakistan group flight packages and inventory.",
    robots: "index,follow",
  },
  "/groups",
);

/**
 * Groups discovery landing. Direct client import (no next/dynamic) so soft-nav
 * Link clicks stay client-side; next/dynamic previously correlated with full
 * document navigations in soft-nav matrix.
 */
export default function GroupsHubPage() {
  return <GroupsLandingPage />;
}
