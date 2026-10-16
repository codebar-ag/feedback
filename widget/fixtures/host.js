// Shared init for the fixture pages. Options come from the query string.
const q = new URLSearchParams(location.search);
const sid = q.get('sid') || 'manual';
const options = {
  projectKey: 'fbk_live_e2eAAAAAAAAAAAAAAAAAAA',
  endpoint: `${location.origin}/s/${sid}`,
  locale: q.get('locale') || 'de_CH',
};
if (q.has('identity')) options.identity = { id: '4711', name: 'Anna Muster', email: 'anna.muster@beispiel.ch' };
if (q.has('color')) options.colors = { primary: q.get('color') };
if (q.has('mode')) options.screenshot = { mode: q.get('mode') };
if (q.has('messages')) options.messages = JSON.parse(q.get('messages'));
if (q.has('persist')) options.draft = { persist: q.get('persist') !== '0' };
if (q.has('position')) options.position = q.get('position');

export async function boot(init) {
  window.feedback = await init(options);
  document.documentElement.dataset.feedbackReady = '1';
}

// SPA navigation without reload.
document.addEventListener('click', (e) => {
  const link = e.target.closest('[data-spa]');
  if (!link) return;
  e.preventDefault();
  history.pushState({}, '', link.getAttribute('href') + location.search);
  document.querySelector('h1').textContent = 'SPA-Seite';
  document.title = 'SPA-Seite';
});
