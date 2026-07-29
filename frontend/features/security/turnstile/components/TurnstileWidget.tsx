"use client";

import { useEffect, useRef, useState } from "react";
import { loadTurnstileScript } from "../utils/script-loader";

type TurnstileWidgetProps = {
  siteKey: string;
  onToken: (token: string) => void;
  onExpire: () => void;
  onError: () => void;
  onReady?: () => void;
  onScriptError?: () => void;
  resetSignal?: number;
  compact?: boolean;
};

export function TurnstileWidget({
  siteKey,
  onToken,
  onExpire,
  onError,
  onReady,
  onScriptError,
  resetSignal = 0,
  compact = false,
}: TurnstileWidgetProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetIdRef = useRef<string | null>(null);
  const [scriptFailed, setScriptFailed] = useState(false);
  const onTokenRef = useRef(onToken);
  const onExpireRef = useRef(onExpire);
  const onErrorRef = useRef(onError);
  const onReadyRef = useRef(onReady);
  const onScriptErrorRef = useRef(onScriptError);

  onTokenRef.current = onToken;
  onExpireRef.current = onExpire;
  onErrorRef.current = onError;
  onReadyRef.current = onReady;
  onScriptErrorRef.current = onScriptError;

  useEffect(() => {
    let cancelled = false;
    const container = containerRef.current;
    if (!container) return undefined;

    void loadTurnstileScript()
      .then(() => {
        if (cancelled || !containerRef.current || !window.turnstile) return;

        if (widgetIdRef.current) {
          window.turnstile.remove(widgetIdRef.current);
          widgetIdRef.current = null;
        }

        widgetIdRef.current = window.turnstile.render(containerRef.current, {
          sitekey: siteKey,
          theme: "light",
          size: compact ? "compact" : "normal",
          callback: (token) => onTokenRef.current(token),
          "expired-callback": () => onExpireRef.current(),
          "error-callback": () => onErrorRef.current(),
        });
        onReadyRef.current?.();
      })
      .catch(() => {
        if (!cancelled) {
          setScriptFailed(true);
          onScriptErrorRef.current?.();
        }
      });

    return () => {
      cancelled = true;
      if (widgetIdRef.current && window.turnstile) {
        window.turnstile.remove(widgetIdRef.current);
        widgetIdRef.current = null;
      }
    };
  }, [siteKey, compact, resetSignal]);

  if (scriptFailed) {
    return null;
  }

  return (
    <div
      ref={containerRef}
      className="min-h-[65px] overflow-hidden"
      data-testid="turnstile-widget"
      aria-label="Security verification"
    />
  );
}
