"use client";

import { useEffect, useMemo, useState } from "react";
import type { FlightOffer } from "../types";
import {
  buildFlightShareText,
  buildSafePublicResultsShareUrl,
  buildWhatsAppShareUrl,
  copyTextToClipboard,
  createFlightShortShareUrl,
} from "../utils/share-flight";

function CopyIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" strokeWidth="1.75" />
      <path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" stroke="currentColor" strokeWidth="1.75" />
    </svg>
  );
}

function WhatsAppIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12.04 2C6.58 2 2.15 6.4 2.15 11.82c0 1.96.52 3.87 1.5 5.55L2 22l4.8-1.57a10 10 0 0 0 5.24 1.44h.01c5.46 0 9.89-4.4 9.89-9.82C21.94 6.4 17.5 2 12.04 2Zm0 17.91h-.01a8.1 8.1 0 0 1-4.12-1.13l-.3-.18-2.85.93.96-2.77-.19-.29a8.03 8.03 0 0 1-1.24-4.3c0-4.44 3.65-8.05 8.14-8.05 2.18 0 4.22.84 5.76 2.37a8 8 0 0 1 2.38 5.7c0 4.44-3.65 8.05-8.13 8.05Zm4.46-5.98c-.24-.12-1.44-.71-1.66-.79-.22-.08-.39-.12-.55.12-.16.24-.63.79-.77.95-.14.16-.29.18-.53.06-.24-.12-1.02-.37-1.94-1.19-.72-.63-1.2-1.41-1.34-1.65-.14-.24-.01-.37.11-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.43-.06-.12-.55-1.32-.75-1.81-.2-.48-.4-.41-.55-.42h-.47c-.16 0-.43.06-.65.3-.22.24-.86.84-.86 2.04s.88 2.37 1 2.53c.12.16 1.73 2.64 4.2 3.7.59.25 1.05.4 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.44-.59 1.64-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28Z" />
    </svg>
  );
}

type ResultShareActionsProps = {
  offer: FlightOffer;
  searchParams?: URLSearchParams;
  displayAmount?: number | null;
};

/**
 * Shared Copy / WhatsApp actions for One Way and Return result cards.
 * Uses existing /api/public/share/flight short-link authority.
 */
export function ResultShareActions({ offer, searchParams, displayAmount }: ResultShareActionsProps) {
  const [copied, setCopied] = useState(false);
  const [shareExpiryLabel, setShareExpiryLabel] = useState<string | null>(null);

  const shareParams = useMemo(
    () =>
      searchParams ??
      (typeof window !== "undefined" ? new URLSearchParams(window.location.search) : new URLSearchParams()),
    [searchParams],
  );
  const shareUrl = useMemo(() => buildSafePublicResultsShareUrl(shareParams), [shareParams]);
  const [resolvedShareUrl, setResolvedShareUrl] = useState(shareUrl);

  useEffect(() => {
    setResolvedShareUrl(shareUrl);
    setShareExpiryLabel(null);
  }, [shareUrl]);

  const ensureShortShare = async (): Promise<string> => {
    const origin = (
      offer.segments?.[0]?.origin_airport_code ??
      offer.departure_airport_code ??
      shareParams.get("from") ??
      ""
    ).toString();
    const destination = (
      offer.segments?.[offer.segments.length - 1]?.destination_airport_code ??
      offer.arrival_airport_code ??
      shareParams.get("to") ??
      ""
    ).toString();
    const depart = shareParams.get("depart") ?? "";
    if (!origin || !destination || !depart) {
      return shareUrl;
    }

    const short = await createFlightShortShareUrl({
      origin,
      destination,
      depart_date: depart,
      return_date: shareParams.get("return_date"),
      trip_type: shareParams.get("trip_type") ?? "one_way",
      adults: Number(shareParams.get("adults") ?? 1),
      children: Number(shareParams.get("children") ?? 0),
      infants: Number(shareParams.get("infants") ?? 0),
      cabin: shareParams.get("cabin") ?? "economy",
      display_fare: displayAmount ?? offer.final_customer_price ?? null,
      airline_code: offer.airline_code,
      airline_name: offer.airline_name,
    });

    if (!short?.url) {
      return shareUrl;
    }

    setResolvedShareUrl(short.url);
    if (short.expires_at) {
      try {
        const when = new Date(short.expires_at);
        setShareExpiryLabel(when.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" }));
      } catch {
        setShareExpiryLabel(null);
      }
    }
    return short.url;
  };

  const handleCopy = async () => {
    const url = await ensureShortShare();
    const text = buildFlightShareText(offer, displayAmount ?? offer.final_customer_price, url, shareExpiryLabel);
    const ok = await copyTextToClipboard(text);
    if (!ok) return;
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="flex items-center gap-1" data-testid="result-share-actions">
      <button
        type="button"
        className="inline-flex h-11 w-11 items-center justify-center rounded-jp-md border border-jp-border text-jp-text-muted hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary sm:h-8 sm:w-8"
        aria-label={copied ? "Copied flight details" : "Copy flight details"}
        title={copied ? "Copied" : "Copy"}
        data-testid="result-copy-share"
        onClick={() => void handleCopy()}
      >
        <CopyIcon className="h-3.5 w-3.5" />
      </button>
      <a
        href={buildWhatsAppShareUrl(
          buildFlightShareText(
            offer,
            displayAmount ?? offer.final_customer_price,
            resolvedShareUrl,
            shareExpiryLabel,
          ),
        )}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex h-11 w-11 items-center justify-center rounded-jp-md border border-jp-border text-jp-text-muted hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary sm:h-8 sm:w-8"
        aria-label="Share on WhatsApp"
        title="WhatsApp"
        data-testid="result-whatsapp-share"
        onClick={(event) => {
          event.preventDefault();
          void (async () => {
            const url = await ensureShortShare();
            const text = buildFlightShareText(
              offer,
              displayAmount ?? offer.final_customer_price,
              url,
              shareExpiryLabel,
            );
            window.open(buildWhatsAppShareUrl(text), "_blank", "noopener,noreferrer");
          })();
        }}
      >
        <WhatsAppIcon className="h-3.5 w-3.5" />
      </a>
      {copied ? (
        <span className="text-[10px] font-medium text-jp-primary" aria-live="polite">
          Copied
        </span>
      ) : null}
    </div>
  );
}
