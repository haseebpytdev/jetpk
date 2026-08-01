"use client";

import {
  PublicAlert,
  PublicBadge,
  PublicBookingSummary,
  PublicButton,
  PublicCallout,
  PublicCard,
  PublicCheckbox,
  PublicContainer,
  PublicEmptyState,
  PublicErrorState,
  PublicFooterPrototype,
  PublicHeaderPrototype,
  PublicIconButton,
  PublicImageSlot,
  PublicLoadingState,
  PublicRadio,
  PublicSection,
  PublicSectionHeading,
  PublicSelect,
  PublicStepper,
  PublicTabs,
  PublicTextField,
  useThemeV2,
} from "@/features/public-theme-v2";
import { CmsPageRenderer } from "@/features/cms-theme-v2";
import { LAB_CMS_PAGE, LAB_STEPPER_STEPS, LAB_SUMMARY_ITEMS } from "../lab/fixtures";

export function ThemeLabContent() {
  const { theme } = useThemeV2();

  return (
    <>
      <PublicHeaderPrototype />
      <main id="main-content">
        <div className="jp-v2-lab-hero">
          <PublicContainer>
            <h1 className="jp-v2-lab-hero__title">Theme V2 Visual Lab</h1>
            <p className="jp-v2-lab-hero__meta">
              Development-only review surface — {theme} theme
            </p>
          </PublicContainer>
        </div>

        <PublicSection id="typography" aria-labelledby="lab-typography">
          <PublicContainer>
            <PublicSectionHeading id="lab-typography" title="Typography" body="Premium OTA type hierarchy for desktop and mobile." />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-type-scale">
                <p className="jp-v2-lab-type-scale__display-xl">Display XL — hero headlines</p>
                <p className="jp-v2-lab-type-scale__display-lg">Display LG — page titles</p>
                <p className="jp-v2-lab-type-scale__heading-md">Heading MD — section titles</p>
                <p className="jp-v2-lab-type-scale__card-title">Card title — component headings</p>
                <p className="jp-v2-lab-type-scale__body">Body base — primary reading text at 15–17px with comfortable line height for travel content.</p>
                <p className="jp-v2-lab-type-scale__muted">Supporting text — metadata, hints and secondary copy at 14px minimum.</p>
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="colors" aria-labelledby="lab-colors">
          <PublicContainer>
            <PublicSectionHeading id="lab-colors" title="Colors and surfaces" body="Surface hierarchy, status tones and brand accents." />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-swatch-grid">
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-brand)", color: "var(--jp-v2-text-inverse)" }}>Brand</div>
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-surface)" }}>Surface</div>
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-surface-soft)" }}>Surface soft</div>
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-success-soft)", color: "var(--jp-v2-success)" }}>Success</div>
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-warning-soft)", color: "var(--jp-v2-warning)" }}>Warning</div>
                <div className="jp-v2-lab-swatch" style={{ background: "var(--jp-v2-error-soft)", color: "var(--jp-v2-error)" }}>Error</div>
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="buttons" aria-labelledby="lab-buttons">
          <PublicContainer>
            <PublicSectionHeading id="lab-buttons" title="Buttons" body="Minimum 44px touch targets with visible focus rings." />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-row">
                <PublicButton variant="primary">Primary</PublicButton>
                <PublicButton variant="secondary">Secondary</PublicButton>
                <PublicButton variant="tertiary">Tertiary</PublicButton>
                <PublicIconButton label="More options">⋯</PublicIconButton>
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="fields" aria-labelledby="lab-fields">
          <PublicContainer>
            <PublicSectionHeading id="lab-fields" title="Fields and controls" />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-fields">
                <PublicTextField id="lab-name" label="Full name" hint="As on your passport" placeholder="Enter name" />
                <PublicSelect id="lab-cabin" label="Cabin class">
                  <option>Economy</option>
                  <option>Business</option>
                </PublicSelect>
                <PublicCheckbox id="lab-terms" label="I agree to the terms" />
                <PublicRadio id="lab-trip-oneway" name="lab-trip" label="One way" defaultChecked />
                <PublicRadio id="lab-trip-return" name="lab-trip" label="Return" />
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="tabs-badges" aria-labelledby="lab-tabs">
          <PublicContainer>
            <PublicSectionHeading id="lab-tabs" title="Tabs and badges" />
            <div className="jp-v2-lab-panel">
              <PublicTabs
                aria-label="Lab tabs"
                tabs={[
                  { id: "overview", label: "Overview", panel: <p>Overview panel content for route and fare details.</p> },
                  { id: "details", label: "Details", panel: <p>Details panel with baggage and policy information.</p> },
                ]}
              />
              <div className="jp-v2-lab-row" style={{ marginTop: "var(--jp-v2-space-lg)" }}>
                <PublicBadge>Default</PublicBadge>
                <PublicBadge tone="success">Success</PublicBadge>
                <PublicBadge tone="warning">Warning</PublicBadge>
                <PublicBadge tone="error">Error</PublicBadge>
                <PublicBadge tone="info">Info</PublicBadge>
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="cards" aria-labelledby="lab-cards">
          <PublicContainer>
            <PublicSectionHeading id="lab-cards" title="Cards and image slots" />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-grid jp-v2-lab-grid--2">
                <PublicCard>
                  <h3 className="jp-v2-lab-card-title">Card title</h3>
                  <p className="jp-v2-lab-card-body">Supporting card copy with sufficient weight and readable line length.</p>
                </PublicCard>
                <PublicImageSlot src="/images/home/hero-fallback.svg" alt="Sample flight imagery" />
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="alerts" aria-labelledby="lab-alerts">
          <PublicContainer>
            <PublicSectionHeading id="lab-alerts" title="Alerts and states" />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-states">
                <PublicCallout tone="info" heading="Information" body="A neutral informational callout with readable body text." />
                <PublicAlert tone="success" title="Success">Your changes were saved.</PublicAlert>
                <PublicEmptyState title="No results" description="Try adjusting your filters." />
                <PublicLoadingState label="Loading results" />
                <PublicErrorState title="Something went wrong" description="Please try again later." />
              </div>
            </div>
          </PublicContainer>
        </PublicSection>

        <PublicSection id="cms" aria-labelledby="lab-cms">
          <PublicContainer>
            <PublicSectionHeading id="lab-cms" title="CMS block examples" body="Rendered via isolated CmsPageRenderer with sanitization." />
          </PublicContainer>
          <CmsPageRenderer page={LAB_CMS_PAGE} showDevMarkers />
        </PublicSection>

        <PublicSection id="booking" aria-labelledby="lab-booking">
          <PublicContainer>
            <PublicSectionHeading id="lab-booking" title="Booking progress and summary" />
            <div className="jp-v2-lab-panel">
              <div className="jp-v2-lab-booking">
                <PublicStepper steps={LAB_STEPPER_STEPS} currentStepId="review" />
                <PublicBookingSummary
                  routeLabel="Sample route (lab fixture)"
                  items={LAB_SUMMARY_ITEMS}
                  totalLabel="Total"
                  totalValue="PKR 98,600"
                />
              </div>
            </div>
          </PublicContainer>
        </PublicSection>
      </main>
      <PublicFooterPrototype />
    </>
  );
}
