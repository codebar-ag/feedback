/**
 * Design tokens and styles. Font sizes: 13 (labels,
 * errors), 15 (text, fields, buttons), 18 (status title), 14 for the toolbar
 * "Done" button. No other sizes, no dark mode.
 */
export const TOKENS = {
  '--fb-ink': '#101F3C',
  '--fb-ink-muted': '#5B6B85',
  '--fb-surface': '#FFFFFF',
  '--fb-surface-sunken': '#F5F7FA',
  '--fb-border': '#E2E8F0',
  '--fb-scrim': 'rgba(16,31,60,.45)',
  '--fb-annotation': '#E02B20',
  '--fb-focus': '#1D4ED8',
  '--fb-danger': '#B42318',
  '--fb-success': '#157F3B',
} as const;

export const MARKER_COLOR = 'rgba(255,214,0,.5)';

const vars = Object.entries(TOKENS)
  .map(([k, v]) => `${k}:${v}`)
  .join(';');

export const CSS = `
:host{all:initial}
.r{${vars};--fb-primary:#000000;--fb-on-primary:#FFFFFF;--fb-strong:#000000;--fb-on-strong:#FFFFFF;--fb-head-line:transparent;--fb-ring:0 0 0 0 transparent;font:15px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--fb-ink);-webkit-text-size-adjust:100%;text-align:left}
.r *,.r *::before,.r *::after{box-sizing:border-box}
.r [hidden]{display:none!important}
.r :where(button){font:inherit;color:inherit;cursor:pointer;margin:0;-webkit-tap-highlight-color:transparent}
.r :focus-visible{outline:3px solid var(--fb-focus);outline-offset:2px}
.r :where(svg){width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none;display:block}
.sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
.l{position:fixed;right:20px;bottom:20px;z-index:var(--fb-z);width:56px;height:56px;border-radius:50%;border:0;padding:0;background:var(--fb-strong);color:var(--fb-on-strong);display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(16,31,60,.28),var(--fb-ring);transition:transform .15s}
.l:hover{transform:scale(1.06)}
.l svg{width:26px;height:26px}
.l.tx{width:auto;padding:0 20px 0 16px;border-radius:999px;font-weight:700}
.left .l{right:auto;left:20px}
.left .p{right:auto;left:20px}
.p{position:fixed;right:20px;bottom:92px;z-index:var(--fb-z);width:380px;max-height:calc(100dvh - 112px);display:flex;flex-direction:column;background:var(--fb-surface);border-radius:20px;box-shadow:0 18px 50px rgba(16,31,60,.32);overflow:hidden;animation:fbpop .18s ease-out}
@keyframes fbpop{from{opacity:0;transform:translateY(8px)}}
.away .p,.away .l{visibility:hidden}
.hd{flex:none;display:flex;align-items:center;justify-content:space-between;padding:10px 12px 10px 20px;background:var(--fb-primary);color:var(--fb-on-primary);border-bottom:1px solid var(--fb-head-line)}
.hd>svg{width:24px;height:24px}
.x{width:34px;height:34px;border:0;border-radius:10px;background:transparent;display:flex;align-items:center;justify-content:center;padding:0}
.x:hover{background:rgba(16,31,60,.12)}
.bd{overflow:auto;min-height:0}
.fm{padding:16px 20px 18px;display:flex;flex-direction:column;gap:8px;margin:0}
.f{display:flex;flex-direction:column;gap:4px;min-width:0}
.lb{font-size:13px;font-weight:600;line-height:1.45}
.rq::after{content:" *"}
.r :where(input,textarea){font:inherit;font-size:15px;color:var(--fb-ink);background:var(--fb-surface);border:1px solid var(--fb-border);border-radius:12px;padding:10px 12px;width:100%;margin:0;box-shadow:none;appearance:none}
.r :where(input){height:44px}
.r :where(textarea){min-height:170px;resize:vertical;display:block}
.r ::placeholder{color:var(--fb-ink-muted);opacity:1}
.r input:focus,.r textarea:focus{outline:3px solid var(--fb-focus);outline-offset:2px}
.r [aria-invalid="true"]{border-color:var(--fb-danger)}
.e{margin:0;height:16px;font-size:13px;line-height:16px;color:var(--fb-danger);overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.e:empty{visibility:hidden}
.id{display:flex;flex-direction:column;background:var(--fb-surface-sunken);border:1px solid var(--fb-border);border-radius:12px;padding:8px 12px;line-height:1.35;overflow-wrap:anywhere}
.id b{font-size:15px;font-weight:600}
.id span{font-size:13px;color:var(--fb-ink-muted)}
.tr{display:flex;align-items:center;gap:8px}
.ib{width:44px;height:44px;border-radius:12px;border:1px solid var(--fb-border);background:var(--fb-surface);display:flex;align-items:center;justify-content:center;padding:0;flex:none}
.ib:hover{border-color:var(--fb-ink-muted)}
.ib.bad{border-color:var(--fb-danger);color:var(--fb-danger)}
.th{width:72px;height:44px;border-radius:8px;border:1px solid var(--fb-border);object-fit:cover;object-position:top left;display:block;background:var(--fb-surface-sunken);flex:none}
.sp{width:18px;height:18px;border-radius:50%;border:2.5px solid currentColor;border-right-color:transparent;animation:fbrot .7s linear infinite;flex:none}
@keyframes fbrot{to{transform:rotate(360deg)}}
.hp{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}
.s{width:100%;border:0;border-radius:999px;padding:13px 18px;font-size:15px;font-weight:700;line-height:1.45;background:var(--fb-strong);color:var(--fb-on-strong);display:flex;align-items:center;justify-content:center;gap:10px;margin-top:4px}
.s:hover{filter:brightness(1.08)}
.s:disabled{opacity:.8;cursor:progress}
.st{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;padding:34px 24px 30px}
.ic{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--fb-success);background:#DAEBE0}
.st.err .ic{color:var(--fb-danger);background:#F6E5E3}
.ic svg{width:28px;height:28px;stroke-width:2.6}
.st h2{margin:6px 0 0;font-size:18px;font-weight:700;line-height:1.3}
.st p{margin:0;color:var(--fb-ink-muted);font-size:15px}
.pl{margin-top:10px;border:1px solid var(--fb-border);background:var(--fb-surface);border-radius:999px;padding:10px 24px;font-size:15px;font-weight:600}
.pl:hover{border-color:var(--fb-ink-muted)}
.cap{position:fixed;inset:0;z-index:var(--fb-z);display:flex;align-items:center;justify-content:center;background:var(--fb-scrim);color:#FFFFFF}
.cap .sp{width:44px;height:44px;border-width:4px}
.an{position:absolute;left:0;top:0;z-index:var(--fb-z);overflow:hidden}
.an img{display:block;width:100%;height:100%;max-width:none}
.sc{position:fixed;inset:0;z-index:var(--fb-z);background:var(--fb-scrim);pointer-events:none}
.cv{position:fixed;left:0;top:0;z-index:var(--fb-z);width:100vw;height:100vh;cursor:crosshair;touch-action:none;display:block}
.tb{position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:var(--fb-z);display:flex;align-items:center;gap:2px;background:var(--fb-surface);border-radius:999px;padding:6px;box-shadow:0 8px 28px rgba(16,31,60,.3);max-width:calc(100vw - 16px)}
.tb button{width:40px;height:40px;border:0;border-radius:50%;background:transparent;color:var(--fb-ink);display:flex;align-items:center;justify-content:center;padding:0;flex:none}
.tb button:hover{background:var(--fb-surface-sunken)}
.tb [aria-pressed="true"]{background:var(--fb-surface-sunken);box-shadow:inset 0 0 0 2px var(--fb-ink)}
.dv{width:1px;height:24px;background:var(--fb-border);margin:0 4px;flex:none}
.tb .fin{width:auto;border-radius:999px;padding:0 16px;background:var(--fb-strong);color:var(--fb-on-strong);font-size:14px;font-weight:700}
.tb .fin:hover{background:var(--fb-strong);filter:brightness(1.06)}
@media (max-width:480px){
.p{left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom) + var(--fb-kb,0px));width:auto;max-height:calc(100dvh - 24px - env(safe-area-inset-top) - env(safe-area-inset-bottom) - var(--fb-kb,0px))}
.left .p{left:12px;right:12px}
.l{right:calc(20px + env(safe-area-inset-right));bottom:calc(20px + env(safe-area-inset-bottom))}
.left .l{left:calc(20px + env(safe-area-inset-left))}
.open .l{display:none}
.tb{top:calc(12px + env(safe-area-inset-top))}
.tb button{width:44px;height:44px}
.tb .fin{padding:0 14px}
}
@media (prefers-reduced-motion:reduce){.r *{animation:none!important;transition:none!important}}
`;
