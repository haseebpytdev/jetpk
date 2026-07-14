export type ViewportName =
  | 'mobile360'
  | 'mobile390'
  | 'mobile430'
  | 'tablet768'
  | 'tabletLandscape1024'
  | 'laptop1280'
  | 'desktop1440'
  | 'desktop1920';

export type ViewportDef = {
  name: ViewportName;
  width: number;
  height: number;
};

export const ALL_VIEWPORTS: ViewportDef[] = [
  { name: 'mobile360', width: 360, height: 800 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'mobile430', width: 430, height: 932 },
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'tabletLandscape1024', width: 1024, height: 768 },
  { name: 'laptop1280', width: 1280, height: 800 },
  { name: 'desktop1440', width: 1440, height: 900 },
  { name: 'desktop1920', width: 1920, height: 1080 },
];

export const SCREENSHOT_VIEWPORTS: ViewportName[] = ['mobile390', 'tablet768', 'desktop1440'];

export const INTERACTIVE_VIEWPORTS: ViewportName[] = ['mobile390', 'desktop1440'];

export function viewportByName(name: ViewportName): ViewportDef {
  const vp = ALL_VIEWPORTS.find((v) => v.name === name);
  if (!vp) {
    throw new Error(`Unknown viewport: ${name}`);
  }

  return vp;
}
