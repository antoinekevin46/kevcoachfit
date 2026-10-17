/* Effects live in the theme: no animation or zoom extension needed. */
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  // A disclosure below the header: no modal, focus trap or scroll lock.
  document.querySelectorAll('.kcf-site-header .wp-block-navigation').forEach((nav,index)=>{
    const source=nav.querySelector('.wp-block-navigation__container');
    if(!source)return;
    const links=source.cloneNode(true);
    [nav,links,...links.querySelectorAll('*')].forEach(element=>{
      [...element.attributes].filter(a=>a.name.startsWith('data-wp-')).forEach(a=>element.removeAttribute(a.name));
    });
    nav.classList.add('kcf-dropdown-nav');
    const toggle=document.createElement('button');toggle.type='button';
    toggle.className='wp-block-navigation__responsive-container-open kcf-menu-toggle';
    toggle.innerHTML='<span class="kcf-menu-lines" aria-hidden="true"></span>';
    const panel=document.createElement('div');panel.id='kcf-navigation-'+index;
    panel.className='kcf-dropdown-panel';panel.append(links);
    toggle.setAttribute('aria-controls',panel.id);
    const setOpen=(open,restoreFocus=false)=>{
      panel.classList.toggle('is-menu-open',open);
      toggle.setAttribute('aria-expanded',String(open));
      toggle.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');
      if(restoreFocus)toggle.focus();
    };
    nav.replaceChildren(toggle,panel);setOpen(false);
    toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true'));
    links.addEventListener('click',event=>{if(event.target.closest('a'))setOpen(false);});
    document.addEventListener('click',event=>{if(!nav.contains(event.target))setOpen(false);});
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape'&&toggle.getAttribute('aria-expanded')==='true'){setOpen(false,true);event.preventDefault();}
    });
    nav.addEventListener('focusout',()=>{requestAnimationFrame(()=>{if(!nav.contains(document.activeElement))setOpen(false);});});
    matchMedia('(min-width:600px)').addEventListener('change',()=>setOpen(false));
    document.addEventListener('kcf:close-menus',()=>setOpen(false));
  });

  document.querySelectorAll('[data-kcf-session-choices]').forEach(select=>{
    const summary=select.form?.querySelector('.kcf-session-preview');
    if(!summary)return;
    const update=()=>{summary.textContent=select.value?(select.selectedOptions[0]?.textContent||''):'';};
    select.addEventListener('change',update);update();
  });
  // Native editable table on desktop, labelled schedule cards on small screens.
  document.querySelectorAll('.kcf-schedule-table table').forEach(table=>{
    table.closest('.kcf-schedule-table').setAttribute('data-kcf-cards','');
    table.setAttribute('role','table');
    const labels=[...table.querySelectorAll('thead th')].map(th=>th.textContent);
    table.querySelectorAll('tr').forEach(row=>row.setAttribute('role','row'));
    table.querySelectorAll('th').forEach(th=>th.setAttribute('role','columnheader'));
    table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((td,i)=>{td.dataset.label=labels[i]||'';td.setAttribute('role','cell');}));
  });
  if (!reduce && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {if(entry.isIntersecting){entry.target.classList.add('kcf-arrive');observer.unobserve(entry.target);}});
    }, {threshold:.08});
    document.querySelectorAll('.kcf-portal,.kcf-story-band,.kcf-final-cta').forEach(element=>observer.observe(element));
  }
  let dialog, fullImage, returnFocus, previousOverflow, zoomButton;
  const openImage = (image, trigger) => {
    if(!dialog){
      dialog=document.createElement('dialog');dialog.className='kcf-lightbox';dialog.setAttribute('aria-label','Image agrandie');
      const toolbar=document.createElement('div');toolbar.className='kcf-lightbox-toolbar';
      const close=document.createElement('button');close.type='button';close.textContent='Fermer ×';close.addEventListener('click',()=>dialog.close());
      zoomButton=document.createElement('button');zoomButton.type='button';zoomButton.textContent='Zoom +';zoomButton.setAttribute('aria-pressed','false');
      fullImage=document.createElement('img');
      zoomButton.addEventListener('click',()=>{
        const zoomed=dialog.classList.toggle('is-zoomed');
        fullImage.style.width=zoomed?Math.min(fullImage.naturalWidth,Math.max(fullImage.clientWidth*2,600))+'px':'';
        zoomButton.textContent=zoomed?'Ajuster':'Zoom +';zoomButton.setAttribute('aria-pressed',String(zoomed));
        dialog.scrollTop=0;dialog.scrollLeft=0;
      });
      toolbar.append(zoomButton,close);dialog.append(toolbar,fullImage);document.body.append(dialog);
      dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
      dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;returnFocus?.focus();});
    }
    returnFocus=trigger;previousOverflow=document.body.style.overflow;
    dialog.classList.remove('is-zoomed');fullImage.style.width='';zoomButton.textContent='Zoom +';zoomButton.setAttribute('aria-pressed','false');
    fullImage.src=image.currentSrc||image.src;fullImage.alt=image.alt;
    if(typeof dialog.showModal==='function'){dialog.showModal();document.body.style.overflow='hidden';}
    else{window.open(fullImage.src,'_blank','noopener,noreferrer');}
  };
  document.querySelectorAll('.kcf-page .wp-block-image:not(.kcf-hero-photo):not(.kcf-pest-photo) img').forEach(image=>{
    if(image.closest('a,button,.kcf-source-fragment,.kcf-wine-intro .kcf-wine-atmosphere'))return;
    const figure=image.closest('figure');figure.classList.add('kcf-zoom-figure');
    const button=document.createElement('button');button.type='button';button.className='kcf-image-zoom';button.setAttribute('aria-label','Agrandir : '+(image.alt||'image du produit'));
    image.before(button);button.append(image);button.addEventListener('click',()=>openImage(image,button));
    if(!matchMedia('(hover: hover) and (pointer: fine)').matches)return;
    const lens=document.createElement('span');lens.className='kcf-lens';lens.setAttribute('aria-hidden','true');figure.append(lens);
    button.addEventListener('pointermove',event=>{
      const rect=button.getBoundingClientRect();const d=Math.min(160,rect.width/2);const z=2.3;
      const x=event.clientX-rect.left,y=event.clientY-rect.top;
      lens.style.width=d+'px';lens.style.height=d+'px';lens.style.left=Math.max(0,Math.min(rect.width-d,x-d/2))+'px';lens.style.top=Math.max(0,Math.min(rect.height-d,y-d/2))+'px';
      lens.style.backgroundImage='url("'+(image.currentSrc||image.src).replaceAll('"','%22')+'")';lens.style.backgroundSize=(rect.width*z)+'px '+(rect.height*z)+'px';lens.style.backgroundPosition=(-(x*z-d/2))+'px '+(-(y*z-d/2))+'px';lens.hidden=false;lens.classList.add('is-visible');
    });
    button.addEventListener('pointerleave',()=>lens.classList.remove('is-visible'));
  });
})();
