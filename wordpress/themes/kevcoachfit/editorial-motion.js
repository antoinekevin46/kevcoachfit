/* Clear editorial movement; all content remains present without JavaScript. */
(() => {
  'use strict';
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const buttons=[...document.querySelectorAll('[data-kcf-motion-toggle]')];
  const updateButtons=()=>buttons.forEach(button=>{
    const paused=document.body.classList.contains('kcf-motion-paused');
    button.textContent=paused?'Reprendre':'Pause';
    button.setAttribute('aria-pressed',String(paused));
    button.setAttribute('aria-label',paused?'Reprendre les animations du site':'Mettre les animations du site en pause');
    button.hidden=reduced.matches;
  });
  buttons.forEach(button=>button.addEventListener('click',()=>{document.body.classList.toggle('kcf-motion-paused');updateButtons();}));
  updateButtons();
  if(reduced.matches)return;
  document.body.classList.add('kcf-motion-enabled');
  document.querySelectorAll('.kcf-sport-hero,.kcf-pest-hero,.kcf-terroir-hero').forEach(el=>el.classList.add('kcf-hero-live'));
  if(!('IntersectionObserver' in window))return;
  const selector='.kcf-choice-card,.kcf-program-card,.kcf-ak-step,.kcf-ak-extra-card,.kcf-ak-hours-card,.kcf-venue-card,.kcf-lexicon-card,.kcf-terroir-story,.kcf-contact-sport,.kcf-route-panel,.kcf-social-follow,.kcf-portal';
  const observer=new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(!entry.isIntersecting)return;
      const siblings=[...entry.target.parentElement.children];
      entry.target.style.setProperty('--kcf-reveal-delay',`${Math.min(siblings.indexOf(entry.target)%3,2)*120}ms`);
      entry.target.classList.add('kcf-motion-entered');
      observer.unobserve(entry.target);
    });
  },{threshold:.08,rootMargin:'0px 0px -12px 0px'});
  document.querySelectorAll(selector).forEach(el=>observer.observe(el));
})();
