import { PageContainer } from "@/components/layout/PageContainer";
import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { SearchModule } from "@/features/search";
import { TrustBenefitsStrip } from "./TrustBenefitsStrip";

export function HomepageHero() {
  return (
    <section className="relative overflow-hidden border-b border-jp-border bg-gradient-to-b from-[#f4f9fd] via-white to-jp-page">
      <div className="pointer-events-none absolute inset-0" aria-hidden="true">
        <div className="absolute -right-16 top-8 h-64 w-64 rounded-full bg-jp-primary/10 blur-3xl" />
        <div className="absolute bottom-0 left-0 h-48 w-48 rounded-full bg-sky-200/40 blur-3xl" />
      </div>

      <PageContainer className="relative py-jp-3xl lg:py-jp-4xl">
        <div className="grid items-center gap-jp-xl lg:grid-cols-[1.05fr_0.95fr]">
          <div className="max-w-2xl">
            <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">
              Pakistan&apos;s trusted OTA
            </p>
            <h1 className="mt-3 font-display text-jp-h1 font-bold leading-tight text-jp-text">
              Explore the world with <span className="text-jp-primary">JetPakistan</span>
            </h1>
            <p className="mt-4 max-w-xl text-jp-body leading-relaxed text-jp-muted">
              Book flights with confidence — secure fares, dedicated support, and routes tailored for
              travelers across Pakistan and beyond.
            </p>
            <AnimatedFlightPath className="mt-6 max-w-md" />
          </div>

          <div className="relative mx-auto w-full max-w-xl lg:max-w-none">
            <HeroVisual />
          </div>
        </div>

        <div className="relative z-10 -mt-2 lg:-mt-8">
          <SearchModule />
          <TrustBenefitsStrip className="mt-jp-md" />
        </div>
      </PageContainer>
    </section>
  );
}

function HeroVisual() {
  return (
    <div className="relative aspect-[5/3] w-full overflow-hidden rounded-jp-xl bg-gradient-to-br from-sky-100 via-white to-jp-primary-soft">
      <svg
        viewBox="0 0 560 336"
        className="h-full w-full"
        role="img"
        aria-label="Jet aircraft flying over a Pakistani skyline"
        preserveAspectRatio="xMidYMid slice"
      >
        <defs>
          <linearGradient id="hero-sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#e8f4fc" />
            <stop offset="100%" stopColor="#f8fcff" />
          </linearGradient>
        </defs>
        <rect width="560" height="336" fill="url(#hero-sky)" />
        <ellipse cx="420" cy="70" rx="90" ry="36" fill="#fff" opacity="0.85" />
        <ellipse cx="120" cy="55" rx="70" ry="28" fill="#fff" opacity="0.7" />
        <path
          d="M0 250 L80 220 L140 235 L200 210 L260 228 L320 205 L380 218 L440 200 L500 215 L560 205 L560 336 L0 336 Z"
          fill="#d4e8d0"
          opacity="0.55"
        />
        <path
          d="M0 265 L60 250 L120 258 L180 242 L240 252 L300 240 L360 248 L420 235 L480 245 L560 238 L560 336 L0 336 Z"
          fill="#b8d4b0"
          opacity="0.7"
        />
        <rect x="248" y="168" width="18" height="72" fill="#8aa89a" rx="2" />
        <polygon points="257,168 272,148 242,148" fill="#6d8f80" />
        <rect x="198" y="198" width="14" height="42" fill="#9ab0a4" rx="1" />
        <rect x="302" y="192" width="12" height="38" fill="#9ab0a4" rx="1" />
        <rect x="348" y="205" width="10" height="30" fill="#a8bdb2" rx="1" />
        <g transform="translate(300 95)">
          <ellipse cx="40" cy="28" rx="52" ry="12" fill="#63b32e" opacity="0.15" />
          <path
            d="M0 30 L75 28 L68 22 L80 28 L68 34 L75 30 L0 32 Z"
            fill="#63b32e"
          />
          <path d="M8 28 L20 26 L18 30 L8 32 Z" fill="#4f9423" />
          <circle cx="72" cy="28" r="3" fill="#3f7820" />
        </g>
        <path
          d="M40 120 C120 100, 200 130, 280 110 S 440 90, 520 105"
          fill="none"
          stroke="#63b32e"
          strokeWidth="2"
          strokeDasharray="6 8"
          opacity="0.45"
        />
      </svg>
    </div>
  );
}
