/* Local demo only. No tracking, payment SDK or third-party service. */
(() => {
  'use strict';
  const money = cents => new Intl.NumberFormat('fr-FR', {style:'currency',currency:'EUR'}).format(cents/100);
  const api = async (route, data) => {
    const response = await fetch(kcfrApp.endpoint+route, {method:'POST', credentials:'same-origin',headers:{'Content-Type':'application/json','X-KCFR-Nonce':kcfrApp.nonce,'X-WP-Nonce':kcfrApp.restNonce}, body:JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'La demande n’a pas pu être enregistrée.');
    return result;
  };
  document.querySelectorAll('[data-kcfr-session-choices]').forEach(select=>{select.addEventListener('change',()=>{const note=select.form.querySelector('.kcfr-session-preview');if(note)note.textContent=select.value?select.selectedOptions[0].textContent:'';});});
  const statusLabel = {reserved:'Place réservée ',waiting:'En liste d’attente',offered:'Une place vous est proposée',expired:'Le délai de confirmation a expiré',cancelled:'Inscription annulée'};
  document.querySelectorAll('[data-kcfr-form]').forEach(form => {
    const result = form.querySelector('.kcfr-form-result');
    const submit = form.querySelector('button[type=submit]');
    const total = form.querySelector('[data-order-total]');
    if(total) form.addEventListener('input', () => {
      total.textContent = money([...form.querySelectorAll('[data-unit-cents]')].reduce((sum,input)=>sum+Math.max(0,Number(input.value)||0)*Number(input.dataset.unitCents),0));
    });
    form.addEventListener('submit', async event => {
      event.preventDefault(); if(!form.reportValidity())return;
      const data = {}; const items = {};
      for(const [key,value] of new FormData(form)) {
        const match = key.match(/^items\[([a-z0-9-]+)\]$/);
        if(match) items[match[1]]=Number(value); else data[key]=value;
      }
      if(data.kind==='order')data.items=items;
      submit.disabled=true; result.textContent='Enregistrement…';
      try {
        const answer=await api(form.dataset.kcfrForm,data);
        result.replaceChildren(document.createTextNode(answer.message+(answer.reference?' Référence : '+answer.reference:'')));
        if(answer.management_url){
          const link=document.createElement('a');link.href=answer.management_url;link.textContent='Suivre ou annuler mon inscription';link.className='kcfr-management-link';result.append(link);
        }
        result.dataset.state='success';result.focus();
      } catch(error) {result.textContent=error.message;result.dataset.state='error';result.focus();}
      finally {submit.disabled=false;}
    });
  });
  const manager=document.querySelector('[data-kcfr-management]');
  if(manager){
    const match=location.hash.match(/^#(\d+)\.([a-f0-9]{64})$/);
    if(!match){manager.textContent='Ouvrez le lien privé obtenu après votre inscription pour retrouver votre séance.';return;}
    const refresh=async operation=>{
      manager.setAttribute('aria-busy','true');
      try {
        const value=await api('manage',{id:Number(match[1]),key:match[2],operation});
        manager.replaceChildren();
        const heading=document.createElement('h2');heading.textContent=statusLabel[value.status]||value.status;manager.append(heading);
        const date=document.createElement('p');date.textContent=value.title+' · '+value.venue+' · '+new Intl.DateTimeFormat('fr-FR',{dateStyle:'full',timeStyle:'short',timeZone:'Europe/Paris'}).format(new Date(value.starts_at));manager.append(date);
        if(value.offered_until&&value.status==='offered'){
          const expiry=document.createElement('p');expiry.textContent='Confirmez avant le '+new Intl.DateTimeFormat('fr-FR',{dateStyle:'short',timeStyle:'short',timeZone:'Europe/Paris'}).format(new Date(value.offered_until));manager.append(expiry);
          addButton('Accepter la place',()=>refresh('confirm'));
        }
        if(['offered','waiting'].includes(value.status)||(value.status==='reserved'&&Date.now()<=new Date(value.starts_at).getTime()-7200000)){addButton('Annuler mon inscription',()=>{if(window.confirm('Annuler cette inscription de démonstration ?'))refresh('cancel');});}
        addButton('Actualiser le suivi',()=>refresh('status'));
        const note=document.createElement('p');note.className='kcfr-field-note';note.textContent=kcfrApp.live?'Conservez ce lien privé. Les SMS ne sont pas activés.':'Test : aucun e-mail ni SMS n’est envoyé.';manager.append(note);
      }catch(error){manager.textContent=error.message;}
      finally{manager.removeAttribute('aria-busy');}
    };
    const addButton=(label,callback)=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.addEventListener('click',callback);manager.append(b);};
    refresh('status');
  }
})();
