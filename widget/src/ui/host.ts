import { CSS } from './tokens.css';

export interface Host {
  host: HTMLElement;
  shadow: ShadowRoot;
  root: HTMLDivElement;
}

/**
 * Host element with an open Shadow Root. Styles via adoptedStyleSheets
 * (CSP friendly), fallback <style nonce> for older browsers.
 */
export function createHost(zIndex: number, nonce?: string): Host {
  const host = document.createElement('codebar-feedback');
  host.setAttribute('data-feedback-ignore', '');
  const shadow = host.attachShadow({ mode: 'open' });
  if ('adoptedStyleSheets' in Document.prototype && 'replaceSync' in CSSStyleSheet.prototype) {
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(CSS);
    shadow.adoptedStyleSheets = [sheet];
  } else {
    const style = document.createElement('style');
    if (nonce) style.nonce = nonce;
    style.textContent = CSS;
    shadow.append(style);
  }
  const root = document.createElement('div');
  root.className = 'r';
  root.style.setProperty('--fb-z', String(zIndex));
  shadow.append(root);
  (document.body ?? document.documentElement).append(host);
  return { host, shadow, root };
}
