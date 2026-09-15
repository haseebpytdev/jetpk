import type { Metadata } from "next";
import { GroupSearchPage } from "@/features/group-ticketing";

export const metadata: Metadata = {
  title: "Group search",
  robots: { index: false, follow: true },
};

export default function Page() {
  return <GroupSearchPage />;
}
