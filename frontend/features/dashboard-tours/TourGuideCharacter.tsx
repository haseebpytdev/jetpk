"use client";

type TourGuideCharacterProps = {
  className?: string;
};

/** Lightweight SVG travel concierge. Static under prefers-reduced-motion. */
export function TourGuideCharacter({ className = "" }: TourGuideCharacterProps) {
  return (
    <svg
      viewBox="0 0 96 96"
      role="img"
      aria-label="JetPakistan travel guide"
      className={`jp-tour-guide ${className}`}
      width={72}
      height={72}
    >
      <defs>
        <style>{`
          .jp-tour-guide .jp-tour-wave {
            transform-origin: 72px 48px;
            animation: jp-tour-wave 2.4s ease-in-out infinite;
          }
          @media (prefers-reduced-motion: reduce) {
            .jp-tour-guide .jp-tour-wave { animation: none; }
          }
          @keyframes jp-tour-wave {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(12deg); }
          }
        `}</style>
      </defs>
      <circle cx="48" cy="48" r="46" fill="#0B3D2E" />
      <circle cx="48" cy="36" r="16" fill="#F5E6D3" />
      <rect x="28" y="52" width="40" height="28" rx="10" fill="#1F6B4A" />
      <circle cx="42" cy="34" r="2" fill="#0B3D2E" />
      <circle cx="54" cy="34" r="2" fill="#0B3D2E" />
      <path d="M42 40c2 2 10 2 12 0" stroke="#0B3D2E" strokeWidth="1.5" fill="none" strokeLinecap="round" />
      <path className="jp-tour-wave" d="M68 48c6-2 10 4 8 10" stroke="#C4A35A" strokeWidth="3" fill="none" strokeLinecap="round" />
      <path d="M20 22l10 4-4 3z" fill="#C4A35A" />
    </svg>
  );
}
