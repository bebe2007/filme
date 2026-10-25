(() => {
  const button = document.getElementById('catalogBackTop');
  if (!button) return;
  let queued = false;
  const update = () => { button.hidden = window.scrollY < 320; queued = false; };
  window.addEventListener('scroll', () => {
    if (!queued) { queued = true; window.requestAnimationFrame(update); }
  }, { passive: true });
  button.addEventListener('click', () => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
  });
  update();
})();
