interface UADataLike {
  brands?: { brand: string; version: string }[];
  mobile?: boolean;
  platform?: string;
  getHighEntropyValues?: (hints: string[]) => Promise<Record<string, unknown>>;
}

let hints: Record<string, unknown> | null = null;

/** Prefetches high entropy Client Hints (Chromium only), called at init. */
export async function prefetchHints(): Promise<void> {
  const data = (navigator as Navigator & { userAgentData?: UADataLike }).userAgentData;
  if (!data) return;
  hints = { brands: data.brands, mobile: data.mobile, platform: data.platform };
  try {
    const high = await data.getHighEntropyValues?.(['platformVersion', 'fullVersionList', 'model', 'architecture', 'bitness']);
    if (high) hints = { ...hints, ...high };
  } catch {
    // Keep the low entropy values.
  }
}

/** Browser and system data for the "client" field (concept 4.7). */
export function collectClient(): Record<string, unknown> {
  const doc = document.documentElement;
  return {
    userAgent: navigator.userAgent,
    ...(hints ? { uaData: hints } : {}),
    viewport: { w: innerWidth, h: innerHeight },
    screen: { w: screen.width, h: screen.height },
    page: {
      w: Math.max(doc.scrollWidth, document.body?.scrollWidth ?? 0),
      h: Math.max(doc.scrollHeight, document.body?.scrollHeight ?? 0),
    },
    scroll: { x: Math.round(scrollX), y: Math.round(scrollY) },
    dpr: devicePixelRatio,
    colorDepth: screen.colorDepth,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    language: navigator.language,
    maxTouchPoints: navigator.maxTouchPoints,
    online: navigator.onLine,
  };
}
