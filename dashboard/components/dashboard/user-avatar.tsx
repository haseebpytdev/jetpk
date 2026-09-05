"use client";

type Props = {
  photoUrl?: string | null;
  initials: string;
  sizeClass?: string;
  textClass?: string;
};

export function DashboardUserAvatar({
  photoUrl,
  initials,
  sizeClass = "h-9 w-9",
  textClass = "text-xs font-semibold text-jp-accent-muted bg-jp-accent/15",
}: Props) {
  return (
    <span className={`relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full ${sizeClass}`}>
      {photoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={photoUrl}
          alt=""
          className="h-full w-full object-cover"
          onError={(event) => {
            event.currentTarget.style.display = "none";
            const fallback = event.currentTarget.nextElementSibling;
            if (fallback instanceof HTMLElement) {
              fallback.classList.remove("hidden");
            }
          }}
        />
      ) : null}
      <span className={`flex h-full w-full items-center justify-center ${textClass} ${photoUrl ? "hidden" : ""}`}>
        {initials}
      </span>
    </span>
  );
}
