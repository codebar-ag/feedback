import { describe, expect, it, vi } from 'vitest';
import { LiveRegion, focusables, trapTab } from '../../src/a11y/focus';
import { composeShot } from '../../src/capture/compose';

describe('focus trap', () => {
  it('cycles Tab and Shift+Tab inside the container', () => {
    document.body.innerHTML =
      '<div id="c"><button id="a">a</button><input id="b" tabindex="-1"><textarea id="t"></textarea><button id="z" disabled>z</button></div>';
    const c = document.getElementById('c')!;
    for (const el of Array.from(c.querySelectorAll<HTMLElement>('*'))) el.getClientRects = () => [{}] as unknown as DOMRectList;
    expect(focusables(c).map((e) => e.id)).toEqual(['a', 't']);

    const tab = (shift: boolean, active: Element | null): KeyboardEvent => {
      const e = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: shift, cancelable: true });
      trapTab(e, c, active);
      return e;
    };
    expect(tab(false, document.getElementById('t')).defaultPrevented).toBe(true);
    expect(document.activeElement?.id).toBe('a');
    expect(tab(true, document.getElementById('a')).defaultPrevented).toBe(true);
    expect(document.activeElement?.id).toBe('t');
    expect(tab(false, document.getElementById('a')).defaultPrevented).toBe(false);
    expect(tab(false, document.body).defaultPrevented).toBe(true);

    const other = new KeyboardEvent('keydown', { key: 'a', cancelable: true });
    trapTab(other, c, null);
    expect(other.defaultPrevented).toBe(false);
  });

  it('announces in a polite live region', async () => {
    vi.stubGlobal('requestAnimationFrame', (fn: FrameRequestCallback) => setTimeout(() => fn(0), 0));
    const live = new LiveRegion();
    expect(live.el.getAttribute('aria-live')).toBe('polite');
    live.announce('Wird gesendet …');
    await new Promise((r) => setTimeout(r, 5));
    expect(live.el.textContent).toBe('Wird gesendet …');
    vi.unstubAllGlobals();
  });
});

describe('compose', () => {
  it('returns the clean image when there is nothing to burn', async () => {
    const blob = new Blob(['x']);
    expect(await composeShot(blob, [], 1, 0.82)).toBe(blob);
  });

  it('burns marks with the scale and encodes JPEG', async () => {
    const calls: unknown[][] = [];
    const ctx = new Proxy({} as Record<string, unknown>, {
      get: (t, p: string) => (p in t ? t[p] : (...a: unknown[]) => calls.push([p, ...a])),
      set: (t, p: string, v) => ((t[p] = v), true),
    });
    vi.stubGlobal('createImageBitmap', async () => ({ width: 2400, height: 6000 }));
    const getContext = vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(ctx as never);
    const toBlob = vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(function (
      this: HTMLCanvasElement,
      cb,
      type,
      quality,
    ) {
      calls.push(['toBlob', this.width, this.height, type, quality]);
      cb(new Blob(['burnt'], { type: 'image/jpeg' }));
    });

    const out = await composeShot(new Blob(['x']), [{ type: 'rect', x: 10, y: 20, w: 30, h: 40 }], 2, 0.82);
    expect(await out.text()).toBe('burnt');
    expect(calls).toContainEqual(['strokeRect', 20, 40, 60, 80]);
    expect(calls).toContainEqual(['toBlob', 2400, 6000, 'image/jpeg', 0.82]);

    await composeShot(new Blob(['x']), [], 1, 0.6, 0.5);
    expect(calls).toContainEqual(['toBlob', 1200, 3000, 'image/jpeg', 0.6]);
    getContext.mockRestore();
    toBlob.mockRestore();
    vi.unstubAllGlobals();
  });
});
