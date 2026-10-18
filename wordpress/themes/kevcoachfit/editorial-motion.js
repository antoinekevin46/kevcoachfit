/* Single, modest entrance per card; no scroll capture, polling, tracking or remote calls. */
(() => {
  'use strict';
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const selector = '.kcf-choice-card,.kcf-program-card,.kcf-ak-step,.kcf-ak-extra-card,.kcf-ak-hours-card,.kcf-venue-card,.kcf-lexicon-card,.kcf-terroir-story,.kcf-contact-sport,.kcf-route-panel,.kcf-social-follow,.kcf-portal';
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('kcf-motion-entered');
      observer.unobserve(entry.target);
    });
  }, {threshold:0.05, rootMargin:'0px 0px -24px 0px'});
  document.querySelectorAll(selector).forEach(el => observer.observe(el));
})();
