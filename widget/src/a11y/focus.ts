const FOCUSABLE =
  'button:not([disabled]),input:not([disabled]):not([tabindex="-1"]),textarea:not([disabled]),[href],[tabindex]:not([tabindex="-1"])';

function visible(el: HTMLElement): boolean {
  return !el.closest('[hidden]') && el.getClientRects().length > 0;
}

export function focusables(container: HTMLElement): HTMLElement[] {
  return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(visible);
}

/** Keeps Tab / Shift+Tab inside the container (dialog focus trap). */
export function trapTab(event: KeyboardEvent, container: HTMLElement, active: Element | null): void {
  if (event.key !== 'Tab') return;
  const items = focusables(container);
  if (items.length === 0) return;
  const first = items[0] as HTMLElement;
  const last = items[items.length - 1] as HTMLElement;
  const inside = active instanceof HTMLElement && container.contains(active);
  if (event.shiftKey && (active === first || !inside)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (active === last || !inside)) {
    event.preventDefault();
    first.focus();
  }
}

/** Polite live region for status announcements. */
export class LiveRegion {
  readonly el: HTMLElement;

  constructor() {
    this.el = document.createElement('div');
    this.el.className = 'sr';
    this.el.setAttribute('aria-live', 'polite');
    this.el.setAttribute('role', 'status');
  }

  announce(message: string): void {
    this.el.textContent = '';
    requestAnimationFrame(() => {
      this.el.textContent = message;
    });
  }
}
