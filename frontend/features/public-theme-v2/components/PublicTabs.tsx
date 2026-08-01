"use client";

import { useCallback, useId, useRef, useState, type KeyboardEvent, type ReactNode } from "react";

export type TabItem = {
  id: string;
  label: string;
  panel: ReactNode;
};

type PublicTabsProps = {
  tabs: TabItem[];
  "aria-label": string;
};

export function PublicTabs({ tabs, "aria-label": ariaLabel }: PublicTabsProps) {
  const baseId = useId();
  const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);

  const focusTab = useCallback((index: number) => {
    setActiveIndex(index);
    tabRefs.current[index]?.focus();
  }, []);

  const onKeyDown = useCallback(
    (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
      let next = index;
      if (event.key === "ArrowRight") {
        next = (index + 1) % tabs.length;
      } else if (event.key === "ArrowLeft") {
        next = (index - 1 + tabs.length) % tabs.length;
      } else if (event.key === "Home") {
        next = 0;
      } else if (event.key === "End") {
        next = tabs.length - 1;
      } else {
        return;
      }
      event.preventDefault();
      focusTab(next);
    },
    [tabs.length, focusTab],
  );

  return (
    <div className="jp-v2-tabs">
      <div role="tablist" aria-label={ariaLabel} className="jp-v2-tabs__list">
        {tabs.map((tab, index) => {
          const tabId = `${baseId}-tab-${tab.id}`;
          const panelId = `${baseId}-panel-${tab.id}`;
          const selected = index === activeIndex;

          return (
            <button
              key={tab.id}
              ref={(el) => {
                tabRefs.current[index] = el;
              }}
              id={tabId}
              role="tab"
              type="button"
              aria-selected={selected}
              aria-controls={panelId}
              tabIndex={selected ? 0 : -1}
              className="jp-v2-tabs__tab"
              onClick={() => setActiveIndex(index)}
              onKeyDown={(e) => onKeyDown(e, index)}
            >
              {tab.label}
            </button>
          );
        })}
      </div>
      {tabs.map((tab, index) => {
        const tabId = `${baseId}-tab-${tab.id}`;
        const panelId = `${baseId}-panel-${tab.id}`;
        const selected = index === activeIndex;

        return (
          <div
            key={tab.id}
            id={panelId}
            role="tabpanel"
            aria-labelledby={tabId}
            hidden={!selected}
            tabIndex={0}
            className="jp-v2-tabs__panel"
          >
            {tab.panel}
          </div>
        );
      })}
    </div>
  );
}
