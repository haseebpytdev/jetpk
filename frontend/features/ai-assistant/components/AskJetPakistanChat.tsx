"use client";

import { usePublicFloatingLayoutOptional } from "@/features/public-floating/PublicFloatingLayoutProvider";
import {
  useCallback,
  useEffect,
  useState,
  type ComponentType,
  type ReactNode,
} from "react";
import styles from "./AskJetPakistanChat.module.css";

type AskJetPakistanChatProps = {
  enabled: boolean;
};

type PanelProps = {
  enabled: boolean;
  autoOpen?: boolean;
};

/**
 * Phase-D Hypothesis C: keep Ask FAB in the initial shell; load the heavy
 * chat panel only on first open (or hash deep-link) via imperative import —
 * avoid next/dynamic module-scope prefetch into the initial graph.
 */
export function AskJetPakistanChat({ enabled }: AskJetPakistanChatProps) {
  const floatingLayout = usePublicFloatingLayoutOptional();
  const [Panel, setPanel] = useState<ComponentType<PanelProps> | null>(null);
  const [autoOpen, setAutoOpen] = useState(false);
  const [loadError, setLoadError] = useState(false);

  const loadPanel = useCallback(async (openImmediately: boolean) => {
    if (Panel) {
      if (openImmediately) setAutoOpen(true);
      return;
    }
    try {
      const mod = await import(
        /* webpackChunkName: "ask-jetpakistan-panel" */
        "./AskJetPakistanPanel"
      );
      setPanel(() => mod.AskJetPakistanPanel);
      if (openImmediately) setAutoOpen(true);
    } catch {
      setLoadError(true);
    }
  }, [Panel]);

  useEffect(() => {
    if (!enabled) return;

    const syncHash = () => {
      if (typeof window !== "undefined" && window.location.hash === "#ask-jetpakistan") {
        void loadPanel(true);
      }
    };

    syncHash();
    window.addEventListener("hashchange", syncHash);
    return () => window.removeEventListener("hashchange", syncHash);
  }, [enabled, loadPanel]);

  if (!enabled) {
    return null;
  }

  if (Panel) {
    return <Panel enabled={enabled} autoOpen={autoOpen} />;
  }

  if (floatingLayout?.dockOpen) {
    return null;
  }

  return (
    <div className={styles.fabWrap} data-jp-ask-fab-wrap="">
      <span className={styles.fabTooltip} role="tooltip">
        Ask JetPakistan
        <small>Get instant travel help</small>
      </span>
      <button
        type="button"
        data-testid="ask-jetpakistan-fab"
        aria-label="Ask JetPakistan"
        disabled={loadError}
        onClick={() => {
          void loadPanel(true);
        }}
        className={styles.fab}
      >
        <AssistantFabIcon />
      </button>
    </div>
  );
}

function IconBase({ children }: { children: ReactNode }) {
  return (
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true" focusable="false">
      {children}
    </svg>
  );
}

function AssistantFabIcon() {
  return (
    <IconBase>
      <path
        d="M5 5.8h14a2 2 0 0 1 2 2v7.4a2 2 0 0 1-2 2h-7.2L7.2 20v-2.8H5a2 2 0 0 1-2-2V7.8a2 2 0 0 1 2-2Z"
        stroke="currentColor"
        strokeWidth="1.7"
        strokeLinejoin="round"
      />
      <path
        d="m8.1 13.4 7.5-4.5.9.6-2.6 2.1 2.2 1.4-.8.7-3-1-2.4 1.9-.7-.4 1.4-2.3-2.5 1.5Z"
        fill="currentColor"
      />
    </IconBase>
  );
}
