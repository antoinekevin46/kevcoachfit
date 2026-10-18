/* Local geometry only: no tile server, tracking, API key or geolocation request. */
(() => {
 'use strict';
 document.querySelectorAll('[data-kcf-map]').forEach(root => {
  const svg=root.querySelector('svg'),stage=root.querySelector('.kcf-map-stage');
  const towns=[...root.querySelectorAll('[data-town]')],select=root.querySelector('[data-map-select]');
  let mode=root.dataset.profile==='sport'?'courses':'protection';
  let selected='',zoom=1,panX=0,panY=0,width=800,height=520,fitScale=1,centerX=500,centerY=360,dragEnabled=false;
  const points=towns.map(t=>({x:Number(t.dataset.x),y:Number(t.dataset.y)}));
  const minX=Math.min(...points.map(p=>p.x))-70,maxX=Math.max(...points.map(p=>p.x))+90,minY=Math.min(...points.map(p=>p.y))-60,maxY=Math.max(...points.map(p=>p.y))+60;
  centerX=(minX+maxX)/2;centerY=(minY+maxY)/2;
  const courseIds=['46047','46215','46290'];
  const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));
  const stateCopy=()=>{
   if(mode==='courses')return {name:'Cours en salle',description:'Calès et Payrac : cours actuels. Saint-Projet : cours seniors à venir. Les points indiquent les communes, pas l’entrée exacte des salles.',legend:'Lieu de cours · pointillé : à venir',scope:'Les cours se déroulent dans les salles, aux créneaux indiqués. Le coaching à domicile correspond à une autre prestation.'};
   if(mode==='coaching')return {name:'Coaching à domicile',description:'Point de référence : Loupiac 46350. Le coaching se déroule chez vous, dans un secteur d’environ 15 minutes de trajet.',legend:'Loupiac · point de référence',scope:'Les communes voisines sont des repères. Kévin confirme votre adresse et le trajet avant le rendez-vous ; la carte ne calcule pas un temps de conduite.'};
   return {name:'A.K Nuisibles',description:'Base de départ : Loupiac. Environ 20 minutes autour de Payrac : Loupiac, Souillac et Gourdon sont cités dans le flyer. Activité en préparation.',legend:'Payrac · point de référence',scope:'La présence d’une commune ne garantit pas la prise en charge de chaque adresse. Appelez Kévin pour confirmer le secteur et la disponibilité.'};
  };
  const describe=()=>{
   const copy=stateCopy(),option=[...select.options].find(o=>o.value===selected);
   root.querySelector('[data-map-name]').textContent=option?.value?option.textContent:copy.name;
   let text=option?.value?option.dataset.description:copy.description;
   if(mode==='coaching'&&option?.value&&option.value!=='46178')text=option.textContent+' · commune repère. Pour un coaching, vérifiez avec Kévin que votre adresse se trouve à environ 15 minutes de trajet de Loupiac.';
   root.querySelector('[data-map-description]').textContent=text;
   root.querySelector('[data-map-legend]').textContent=copy.legend;root.querySelector('[data-map-scope]').textContent=copy.scope;
  };
  const draw=()=>{
   const scale=fitScale*zoom;
   panX=clamp(panX,-width*2,width*2);panY=clamp(panY,-height*2,height*2);
   stage.setAttribute('transform',`translate(${width/2+panX} ${height/2+panY}) scale(${scale}) translate(${-centerX} ${-centerY})`);
   towns.forEach(t=>{
    const id=t.dataset.town,active=mode==='courses'?courseIds.includes(id):id===(mode==='coaching'?'46178':'46215');
    t.classList.toggle('is-active',active);t.classList.toggle('is-selected',id===selected);t.classList.toggle('is-future',mode==='courses'&&id==='46290');
    t.classList.toggle('show-label',t.classList.contains('is-major')||id===selected||zoom>=1.85);
    t.querySelector('.kcf-map-dot').setAttribute('r',(active||id===selected?5.5:3)/scale);
    t.querySelector('.kcf-map-halo').setAttribute('r',(id===selected?13:10)/scale);
    const label=t.querySelector('text');label.setAttribute('font-size',`${13/scale}px`);label.setAttribute('x',10/scale);label.setAttribute('y',-10/scale);
    const screenX=width/2+panX+(Number(t.dataset.x)-centerX)*scale;label.setAttribute('text-anchor',screenX>width/2?'end':'start');label.setAttribute('x',(screenX>width/2?-10:10)/scale);
    if(id===selected){label.setAttribute('text-anchor','middle');label.setAttribute('x',0);label.setAttribute('y',-18/scale);}
   });
   // Keep selected and active labels readable instead of stacking village names.
   const occupied=[],viewport=svg.getBoundingClientRect();
   const priority=t=>(t.dataset.town===selected?100:0)+(t.classList.contains('is-active')?50:0)+(t.classList.contains('is-major')?10:0);
   towns.forEach(t=>t.querySelector('text').style.display='');
   [...towns].filter(t=>t.classList.contains('show-label')).sort((a,b)=>priority(b)-priority(a)).forEach(t=>{
    const label=t.querySelector('text'),box=label.getBoundingClientRect();
    const outside=box.left<viewport.left+4||box.right>viewport.right-4||box.top<viewport.top+4||box.bottom>viewport.bottom-4;
    const overlaps=occupied.some(b=>box.left<b.right+5&&box.right>b.left-5&&box.top<b.bottom+4&&box.bottom>b.top-4);
    if(t.dataset.town!==selected&&(outside||overlaps))label.style.display='none';else occupied.push(box);
   });
   root.querySelectorAll('[data-boundary]').forEach(path=>path.classList.toggle('is-selected',path.dataset.boundary===selected));
   root.querySelector('[data-map-action="in"]').disabled=zoom>=5;
   root.querySelector('[data-map-action="out"]').disabled=zoom<=1;
   root.dataset.zoom=zoom.toFixed(2);root.dataset.mode=mode;
  };
  const resize=()=>{
   const rect=svg.getBoundingClientRect();width=Math.max(240,rect.width);height=Math.max(280,rect.height);
   svg.setAttribute('viewBox',`0 0 ${width} ${height}`);fitScale=Math.min((width-48)/(maxX-minX),(height-48)/(maxY-minY));draw();
  };
  const setZoom=(value,point)=>{
   const next=clamp(value,1,5),factor=next/zoom;
   if(point){panX=point.x-width/2-(point.x-width/2-panX)*factor;panY=point.y-height/2-(point.y-height/2-panY)*factor;}
   else{panX*=factor;panY*=factor;}
   zoom=next;draw();
  };
  const reset=()=>{zoom=1;panX=0;panY=0;selected='';select.value='';describe();draw();};
  const locate=id=>{
   const town=towns.find(t=>t.dataset.town===id);if(!town)return;
   selected=id;select.value=id;zoom=Math.max(2.1,zoom);
   panX=-(Number(town.dataset.x)-centerX)*fitScale*zoom;panY=-(Number(town.dataset.y)-centerY)*fitScale*zoom;
   describe();draw();
  };
  root.querySelectorAll('[data-map-mode]').forEach(button=>button.addEventListener('click',()=>{
   mode=button.dataset.mapMode;root.querySelectorAll('[data-map-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));reset();
  }));
  root.querySelectorAll('[data-map-action]').forEach(button=>button.addEventListener('click',()=>{
   const action=button.dataset.mapAction;
   if(action==='in')setZoom(zoom*1.4);if(action==='out')setZoom(zoom/1.4);if(action==='reset')reset();
   if(action==='drag'){dragEnabled=!dragEnabled;root.classList.toggle('is-draggable',dragEnabled);button.setAttribute('aria-pressed',String(dragEnabled));}
  }));
  select.addEventListener('change',()=>select.value?locate(select.value):reset());
  svg.addEventListener('click',event=>{if(moved)return;const town=event.target.closest('[data-town]');if(town)locate(town.dataset.town);});
  svg.addEventListener('keydown',event=>{
   if(event.target!==svg)return;
   if(['+','=','-','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home'].includes(event.key))event.preventDefault();
   if(['+','='].includes(event.key))setZoom(zoom*1.4);if(event.key==='-')setZoom(zoom/1.4);if(event.key==='Home')reset();
   if(event.key==='ArrowLeft')panX+=40;if(event.key==='ArrowRight')panX-=40;if(event.key==='ArrowUp')panY+=40;if(event.key==='ArrowDown')panY-=40;draw();
  });
  const pointers=new Map();let moved=false,lastDistance=0,downTown='';
  const pinchDistance=()=>{const [a,b]=[...pointers.values()];return a&&b?Math.hypot(a.x-b.x,a.y-b.y):0;};
  svg.addEventListener('pointerdown',event=>{
   moved=false;downTown=event.target.closest('[data-town]')?.dataset.town||'';if(event.pointerType!=='mouse'&&!dragEnabled)return;
   pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});lastDistance=pinchDistance();
   svg.setPointerCapture(event.pointerId);
  });
  svg.addEventListener('pointermove',event=>{
   const previous=pointers.get(event.pointerId);if(!previous)return;
   pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});const distance=pinchDistance();
   if(distance&&lastDistance){if(Math.abs(distance-lastDistance)>1)moved=true;const rect=svg.getBoundingClientRect();const ps=[...pointers.values()];setZoom(zoom*distance/lastDistance,{x:(ps[0].x+ps[1].x)/2-rect.left,y:(ps[0].y+ps[1].y)/2-rect.top});}
   else{const dx=event.clientX-previous.x,dy=event.clientY-previous.y;if(Math.abs(dx)+Math.abs(dy)>2)moved=true;panX+=dx;panY+=dy;draw();}
   lastDistance=distance;
  });
  const release=event=>{if(event.type==='pointerup'&&!moved&&downTown){locate(downTown);moved=true;}pointers.delete(event.pointerId);lastDistance=pinchDistance();};
  svg.addEventListener('pointerup',release);svg.addEventListener('pointercancel',release);
  svg.addEventListener('wheel',event=>{if(!event.ctrlKey)return;event.preventDefault();const rect=svg.getBoundingClientRect();setZoom(zoom*(event.deltaY<0?1.15:1/1.15),{x:event.clientX-rect.left,y:event.clientY-rect.top});},{passive:false});
  new ResizeObserver(resize).observe(svg);describe();resize();
 });
})();
