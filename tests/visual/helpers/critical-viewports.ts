export type CriticalViewport = {
  name: string;
  width: number;
  height: number;
};

export const PUBLIC_DROPDOWN_VIEWPORTS: CriticalViewport[] = [
  { name: 'mobile360', width: 360, height: 800 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'laptop1280', width: 1280, height: 800 },
  { name: 'desktop1440', width: 1440, height: 900 },
];

/** Guest public pages — full width ladder for fast critical responsive checks. */
export const PUBLIC_CRITICAL_VIEWPORTS: CriticalViewport[] = PUBLIC_DROPDOWN_VIEWPORTS;

export const AGENT_CRITICAL_VIEWPORTS: CriticalViewport[] = [
  { name: 'mobile360', width: 360, height: 800 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'desktop1440', width: 1440, height: 900 },
];
