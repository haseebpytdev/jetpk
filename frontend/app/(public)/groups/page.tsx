import { redirect } from "next/navigation";

/** Bookmark-safe hub: Groups live at /groups/search (no bare presentation shell). */
export default function GroupsHubPage() {
  redirect("/groups/search");
}
