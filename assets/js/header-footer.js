/* ==========================================================
   XGOUD – Header & Footer System (interactions)
   Live-Ticker, Mega-Menu-Wizard, Fullscreen-Suche, Mobile-Menu.
   Wird über inc/setup.php im Footer geladen.
   Hinweis: METALS-Preise sind aktuell Demo-Daten. Später per
   REST/API anbinden (siehe README → Live-Preise).
   ========================================================== */
(function () {
  'use strict';

  /* ==========================================================
     DATA
     ========================================================== */
  const METALS = [
    { key:'goud',      name:'GOUD 999',      priceG:106.42, changeAbs: 0.45,   changePct: 0.42,  open:105.97, prev:105.65, state:'up' },
    { key:'zilver',    name:'ZILVER 999',    priceG:  1.24, changeAbs: 0.0022, changePct: 0.18,  open:1.237,  prev:1.231,  state:'up' },
    { key:'platina',   name:'PLATINA 999',   priceG: 37.14, changeAbs:-0.030,  changePct:-0.08,  open:37.17,  prev:37.20,  state:'down' },
    { key:'palladium', name:'PALLADIUM 999', priceG: 31.78, changeAbs: 0.085,  changePct: 0.27,  open:31.695, prev:31.60,  state:'up' },
  ];

  function fmt(n,d){ return n.toLocaleString('nl-NL',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function pad(n){ return String(n).padStart(2,'0'); }
  function nowStr()   { const n=new Date(); return `${pad(n.getHours())}:${pad(n.getMinutes())}`; }
  function nowStrSec(){ const n=new Date(); return `${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`; }
  function dateStr()  { const n=new Date(); return `${n.getDate()} ${['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'][n.getMonth()]} ${n.getFullYear()}`; }

  /* clocks */
  function updateClocks(){
    const t=document.getElementById('xgTime'); if(t) t.textContent=nowStr();
    const d=document.getElementById('xgDate'); if(d) d.textContent=dateStr();
    const ft=document.getElementById('ftTime'); if(ft) ft.textContent=nowStrSec();
    const fd=document.getElementById('ftDate'); if(fd) fd.textContent=dateStr();
  }
  updateClocks(); setInterval(updateClocks, 1000);

  /* spark SVG */
  function buildSeries(up){
    const pts=[]; let y=50;
    for(let i=0;i<16;i++){ y+=(Math.random()-.46)*10; y=Math.max(8,Math.min(92,y)); pts.push(y); }
    pts[0]=up?76:24; pts[pts.length-1]=up?24:76;
    return pts;
  }
  function sparkSVG(pts,up,w=320,h=100){
    const s=w/(pts.length-1);
    const d=pts.map((p,i)=>`${i===0?'M':'L'}${(i*s).toFixed(1)},${(h-(p/100*h)).toFixed(1)}`).join(' ');
    const fill=`${d} L${w},${h} L0,${h} Z`;
    const c=up?'#1f9d55':'#d93636';
    return `<svg width="100%" height="${h}" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
      <path d="${fill}" fill="${c}" opacity=".1"/>
      <path d="${d}" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
  }

  /* session rows */
  function sessionRows(m){
    const s=m.state, up=s==='up', dn=s==='down';
    const cls=up?'up':dn?'down':'neutral';
    const sign=up?'+':dn?'-':'';
    const absChange=Math.abs(m.changeAbs);
    return `
      <div class="xg-drop-session">
        <span class="xg-drop-sess-label"><span class="xg-drop-sess-dot neutral"></span>Sluiting gisteren (17:00)</span>
        <span class="xg-drop-sess-price">€ ${fmt(m.prev,2)}/g</span>
        <span class="xg-drop-sess-change neutral">—</span>
      </div>
      <div class="xg-drop-session">
        <span class="xg-drop-sess-label"><span class="xg-drop-sess-dot ${cls}"></span>Opening vandaag (09:00)</span>
        <span class="xg-drop-sess-price">€ ${fmt(m.open,2)}/g</span>
        <span class="xg-drop-sess-change ${cls}">${sign}€ ${fmt(absChange*.3,3)}</span>
      </div>
      <div class="xg-drop-session">
        <span class="xg-drop-sess-label"><span class="xg-drop-sess-dot ${cls}"></span>Huidig</span>
        <span class="xg-drop-sess-price">€ ${fmt(m.priceG,2)}/g</span>
        <span class="xg-drop-sess-change ${cls}">${up?'+':''}${fmt(m.changePct,2)}%</span>
      </div>`;
  }

  /* ==========================================================
     HEADER TICKER
     ========================================================== */
  const xgBlurOv = document.getElementById('xgBlurOverlay');
  let activeHDrop = null;

  function buildHdrTicker(){
    const track=document.getElementById('xgTrack'); if(!track) return;
    let html='';
    for(let c=0;c<2;c++){
      METALS.forEach((m,i)=>{
        const up=m.changePct>=0, dn=m.changePct<0;
        const cls=up?'up':dn?'down':'neutral';
        const sign=up?'+':'';
        html+=`<div class="xg-ti" data-idx="${i}">
          <div class="xg-ti-name">${m.name}</div>
          <div class="xg-ti-row">
            <span class="xg-ti-price">€ ${fmt(m.priceG,2)}</span>
            <span class="xg-ti-unit">/g</span>
            <span class="xg-ti-pct ${cls}">${sign}${fmt(m.changePct,2)}%</span>
            <span class="xg-ti-chev">▾</span>
          </div>
        </div>`;
      });
    }
    track.innerHTML=html;
    track.querySelectorAll('.xg-ti').forEach(el=>{
      el.addEventListener('click', e=>{
        e.stopPropagation();
        const idx=parseInt(el.dataset.idx);
        const was=el.classList.contains('active');
        closeHDrop();
        if(!was){ el.classList.add('active'); openHDrop(el, METALS[idx]); }
      });
    });
  }

  function openHDrop(el, m){
    const up=m.changePct>=0, dn=m.changePct<0;
    const cls=up?'up':dn?'down':'neutral';
    const sign=up?'+':'';
    const kg=m.priceG*1000;
    const div=document.createElement('div');
    div.className='xg-drop open';
    div.innerHTML=`
      <div class="xg-drop-head">
        <span class="xg-drop-title">${m.name}</span>
        <button class="xg-drop-close" id="hdClose">&#x2715;</button>
      </div>
      <div class="xg-drop-prices">
        <div class="xg-drop-pb">
          <div class="xg-drop-pb-label"><span class="xg-drop-pb-icon">g</span>Per gram</div>
          <div class="xg-drop-pb-value">€ ${fmt(m.priceG,2)}</div>
        </div>
        <div class="xg-drop-pb">
          <div class="xg-drop-pb-label"><span class="xg-drop-pb-icon">kg</span>Per kilo</div>
          <div class="xg-drop-pb-value">€ ${fmt(kg,0)}</div>
        </div>
      </div>
      <div class="xg-drop-sessions">${sessionRows(m)}</div>
      <div class="xg-drop-graph">
        <div class="xg-drop-graph-label">Verloop vandaag</div>
        ${sparkSVG(buildSeries(up),up)}
      </div>`;
    document.body.appendChild(div);
    activeHDrop=div;

    const rect=el.getBoundingClientRect();
    let left=rect.left;
    if(left+380>window.innerWidth-12) left=window.innerWidth-392;
    if(left<12) left=12;
    div.style.left=`${left}px`;
    div.style.top=`${rect.bottom+4}px`;

    // Blur from topbar-bottom down
    xgBlurOv.style.top = `var(--topbar-h)`;
    xgBlurOv.classList.add('active');
    document.getElementById('xgTrack').classList.add('paused');

    div.querySelector('#hdClose').addEventListener('click', e=>{ e.stopPropagation(); closeHDrop(); });
  }

  function closeHDrop(){
    if(activeHDrop){ activeHDrop.remove(); activeHDrop=null; }
    document.querySelectorAll('.xg-ti').forEach(i=>i.classList.remove('active'));
    if(xgBlurOv) xgBlurOv.classList.remove('active');
    const t=document.getElementById('xgTrack'); if(t) t.classList.remove('paused');
  }
  document.addEventListener('click', e=>{ if(activeHDrop&&!activeHDrop.contains(e.target)) closeHDrop(); });
  buildHdrTicker();

  /* ==========================================================
     FOOTER TICKER
     ========================================================== */
  const ftBlurOv = document.getElementById('ftBlurOv');
  let activeFtDrop = null;

  function buildFtTicker(){
    const track=document.getElementById('ftTrack'); if(!track) return;
    let html='';
    for(let c=0;c<2;c++){
      METALS.forEach((m,i)=>{
        const up=m.changePct>=0, dn=m.changePct<0;
        const cls=up?'up':dn?'down':'neutral';
        const sign=up?'+':'';
        html+=`<div class="ft-item" data-idx="${i}">
          <div class="ft-item-name">${m.name}</div>
          <div class="ft-item-row">
            <span class="ft-item-price">€ ${fmt(m.priceG,2)}</span>
            <span class="ft-item-unit">/g</span>
            <span class="ft-item-pct ${cls}">${sign}${fmt(m.changePct,2)}%</span>
            <span class="ft-item-chev">▾</span>
          </div>
        </div>`;
      });
    }
    track.innerHTML=html;
    track.querySelectorAll('.ft-item').forEach(el=>{
      el.addEventListener('click', e=>{
        e.stopPropagation();
        const idx=parseInt(el.dataset.idx);
        const was=el.classList.contains('active');
        closeFtDrop();
        if(!was){ el.classList.add('active'); openFtDrop(el, METALS[idx]); }
      });
    });
  }

  function openFtDrop(el, m){
    const up=m.changePct>=0, dn=m.changePct<0;
    const cls=up?'up':dn?'down':'neutral';
    const sign=up?'+':'';
    const kg=m.priceG*1000;
    const div=document.createElement('div');
    div.className='ft-dropup open';
    div.innerHTML=`
      <div class="ft-du-head">
        <span class="ft-du-title">${m.name}</span>
        <div class="ft-du-meta">
          <span class="ft-du-pct ${cls}">${sign}${fmt(m.changePct,2)}%</span>
          <button class="ft-du-close" id="ftClose">&#x2715;</button>
        </div>
      </div>
      <div class="ft-du-prices">
        <div class="ft-du-pb">
          <div class="ft-du-pb-label"><span class="ft-du-pb-icon">g</span>Per gram</div>
          <div class="ft-du-pb-value">€ ${fmt(m.priceG,2)}</div>
        </div>
        <div class="ft-du-pb">
          <div class="ft-du-pb-label"><span class="ft-du-pb-icon">kg</span>Per kilo</div>
          <div class="ft-du-pb-value">€ ${fmt(kg,0)}</div>
        </div>
      </div>
      <div class="ft-du-sessions">
        <div class="ft-du-sess">
          <span class="ft-du-sess-label"><span class="ft-du-sess-dot neutral"></span>Sluiting gisteren (17:00)</span>
          <span class="ft-du-sess-price">€ ${fmt(m.prev,2)}/g</span>
          <span class="ft-du-sess-change neutral">—</span>
        </div>
        <div class="ft-du-sess">
          <span class="ft-du-sess-label"><span class="ft-du-sess-dot ${cls}"></span>Opening (09:00)</span>
          <span class="ft-du-sess-price">€ ${fmt(m.open,2)}/g</span>
          <span class="ft-du-sess-change ${cls}">${sign}€ ${fmt(Math.abs(m.changeAbs)*.3,3)}</span>
        </div>
        <div class="ft-du-sess">
          <span class="ft-du-sess-label"><span class="ft-du-sess-dot ${cls}"></span>Huidig</span>
          <span class="ft-du-sess-price">€ ${fmt(m.priceG,2)}/g</span>
          <span class="ft-du-sess-change ${cls}">${sign}${fmt(m.changePct,2)}%</span>
        </div>
      </div>
      <div class="ft-du-graph">
        <div class="ft-du-graph-label">Verloop vandaag</div>
        ${sparkSVG(buildSeries(up),up)}
      </div>`;
    document.body.appendChild(div);
    activeFtDrop=div;
    if(ftBlurOv) ftBlurOv.classList.add('active');
    document.getElementById('ftTrack').classList.add('paused');

    requestAnimationFrame(()=>{
      const dh=div.offsetHeight;
      const rect=el.getBoundingClientRect();
      let left=rect.left;
      if(left+380>window.innerWidth-12) left=window.innerWidth-392;
      if(left<12) left=12;
      div.style.left=`${left}px`;
      div.style.top=`${rect.top-dh-10}px`;
    });

    div.querySelector('#ftClose').addEventListener('click', e=>{ e.stopPropagation(); closeFtDrop(); });
  }

  function closeFtDrop(){
    if(activeFtDrop){ activeFtDrop.remove(); activeFtDrop=null; }
    document.querySelectorAll('.ft-item').forEach(i=>i.classList.remove('active'));
    if(ftBlurOv) ftBlurOv.classList.remove('active');
    const t=document.getElementById('ftTrack'); if(t) t.classList.remove('paused');
  }
  document.addEventListener('click', e=>{ if(activeFtDrop&&!activeFtDrop.contains(e.target)) closeFtDrop(); });
  buildFtTicker();

  /* ==========================================================
     FULL-SCREEN SEARCH
     ========================================================== */
  const searchOv = document.getElementById('xgSearchOv');
  const searchIn = document.getElementById('xgSearchInput');
  const searchRs = document.getElementById('xgSearchResults');
  const ALL_TERMS = ['Goud verkopen','Goudprijs vandaag','Gouden ring taxatie','Krugerrand','Maple Leaf','Zilver baren','Zilver verkopen','Platina','Palladium','Diamanten','Saffieren','Rolex','Omega','Edelstenen','Horloges'];

  const searchBtn = document.getElementById('xgSearchBtn');
  if(searchBtn) searchBtn.addEventListener('click',()=>{
    searchOv.classList.add('open');
    setTimeout(()=>searchIn.focus(),80);
  });
  const searchCloseBtn = document.getElementById('xgSearchClose');
  if(searchCloseBtn) searchCloseBtn.addEventListener('click',()=>{
    searchOv.classList.remove('open'); searchIn.value='';
  });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && searchOv) searchOv.classList.remove('open'); });

  if(searchIn) searchIn.addEventListener('input',()=>{
    const q=searchIn.value.toLowerCase().trim();
    if(!q){ searchRs.innerHTML=`<div class="xg-sr-section"><div class="xg-sr-label">Typ om te zoeken</div></div>`; return; }
    const matches=ALL_TERMS.filter(s=>s.toLowerCase().includes(q)).slice(0,7);
    const iconSVG=`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>`;
    searchRs.innerHTML=matches.length
      ? `<div class="xg-sr-section"><div class="xg-sr-label">Resultaten</div>${matches.map(s=>`<div class="xg-sr-item"><span class="xg-sr-icon">${iconSVG}</span>${s}</div>`).join('')}</div>`
      : `<div class="xg-sr-section"><div style="padding:12px 0;color:var(--ink-soft);font-size:13px;">Geen resultaten gevonden</div></div>`;
  });

  /* ==========================================================
     MEGA MENU — WIZARD
     ========================================================== */
  const megaMenu    = document.getElementById('xgMega');
  const megaContent = document.getElementById('xgMegaContent');
  const navItems    = document.querySelectorAll('.xg-nav-item');

  const WIZ = {
    edelmetalen: {
      steps:['Kies uw metaal','Kies uw categorie'],
      opts:{
        goud:      {lbl:'Goud',      desc:'Baren, munten en sloop/sieraden',
          cats:{ baren:{lbl:'Baar',prods:['1g','5g','10g','20g','50g','100g','250g','500g','1 kg']},
                 munten:{lbl:'Munten',prods:['Krugerrand','Maple Leaf','Britannia','Philharmoniker','Dukaat']},
                 sloop:{lbl:'Sloop & sieraden',prods:['Ringen','Kettingen','Armbanden','Tandgoud']} }},
        zilver:    {lbl:'Zilver',    desc:'Baren, munten en sloop',
          cats:{ baren:{lbl:'Baar',prods:['100g','250g','500g','1 kg']},
                 munten:{lbl:'Munten',prods:['Maple Leaf','Britannia','Eagle']},
                 sloop:{lbl:'Sloop & bestek',prods:['Bestek','Sieraden','Munten (sloop)']} }},
        platina:   {lbl:'Platina',   desc:'Baren, munten en sloop',
          cats:{ baren:{lbl:'Baar',prods:['1g','10g','100g']},
                 munten:{lbl:'Munten',prods:['Platinum Eagle','Maple Leaf']},
                 sloop:{lbl:'Sloop & sieraden',prods:['Ringen','Kettingen']} }},
        palladium: {lbl:'Palladium', desc:'Baren en munten',
          cats:{ baren:{lbl:'Baar',prods:['1g','10g','100g']},
                 munten:{lbl:'Munten',prods:['Maple Leaf']} }}
      },
      ql: ['Gouden ring','Krugerrand','Goud baren','Tandgoud']
    },
    edelstenen: {
      steps:['Kies uw steen','Kies uw categorie'],
      opts:{
        diamant:{lbl:'Diamant',desc:'Geslepen en ruwe diamanten',
          cats:{ geslepen:{lbl:'Geslepen',prods:['0,25 ct','0,50 ct','1,00 ct','2,00 ct','3,00 ct']},
                 ruw:{lbl:'Ruw',prods:['1 ct','2 ct','5 ct']} }},
        saffier:{lbl:'Saffier',desc:'Natuurlijke saffieren',
          cats:{ blauw:{lbl:'Blauw',prods:['1 ct','2 ct','3 ct']},
                 geel:{lbl:'Geel',prods:['1 ct','2 ct']},
                 roze:{lbl:'Roze',prods:['1 ct','2 ct']} }},
        smaragd:{lbl:'Smaragd',desc:'Geslepen smaragden',
          cats:{ geslepen:{lbl:'Geslepen',prods:['0,50 ct','1,00 ct','2,00 ct']} }},
        robijn:{lbl:'Robijn',desc:'Geslepen robijnen',
          cats:{ geslepen:{lbl:'Geslepen',prods:['0,50 ct','1,00 ct','2,00 ct']} }}
      },
      ql:['Diamant 1 ct','Blauwe saffier','Smaragd']
    },
    horloges: {
      // Alleen de bekendste merken in de wizard (de overige merken staan wél als
      // pagina onder /verkopen/horloges/). Collectie = eindkeuze (geen extra stap).
      steps:['Kies uw merk','Kies uw collectie'],
      opts:{
        rolex:{lbl:'Rolex',desc:'Submariner, Daytona e.a.', cats:{ submariner:{lbl:'Submariner'}, datejust:{lbl:'Datejust'}, daytona:{lbl:'Daytona'}, 'gmt-master-ii':{lbl:'GMT-Master II'} }},
        omega:{lbl:'Omega',desc:'Speedmaster, Seamaster', cats:{ speedmaster:{lbl:'Speedmaster'}, seamaster:{lbl:'Seamaster'}, constellation:{lbl:'Constellation'} }},
        'patek-philippe':{lbl:'Patek Philippe',desc:'Nautilus, Aquanaut', cats:{ nautilus:{lbl:'Nautilus'}, aquanaut:{lbl:'Aquanaut'}, calatrava:{lbl:'Calatrava'} }},
        'audemars-piguet':{lbl:'Audemars Piguet',desc:'Royal Oak', cats:{ 'royal-oak':{lbl:'Royal Oak'}, 'royal-oak-offshore':{lbl:'Royal Oak Offshore'} }},
        cartier:{lbl:'Cartier',desc:'Santos, Tank', cats:{ santos:{lbl:'Santos'}, tank:{lbl:'Tank'}, 'ballon-bleu':{lbl:'Ballon Bleu'} }},
        breitling:{lbl:'Breitling',desc:'Navitimer e.a.', cats:{ navitimer:{lbl:'Navitimer'}, superocean:{lbl:'Superocean'}, chronomat:{lbl:'Chronomat'} }}
      },
      ql:['Rolex Submariner','Rolex Daytona','Omega Speedmaster']
    }
  };

  const STD = {
    dagprijzen:`<div class="xg-std-grid">
      <div class="xg-std-col"><h3>Dagprijzen</h3><a href="/dagprijzen/goudprijs/">Goudprijs vandaag</a><a href="/dagprijzen/zilverprijs/">Zilverprijs vandaag</a><a href="/dagprijzen/platinaprijs/">Platinaprijs vandaag</a><a href="/dagprijzen/palladiumprijs/">Palladiumprijs</a></div>
      <div class="xg-std-col"><h3>Overzicht</h3><a href="/dagprijzen/">Alle dagprijzen</a><a href="/inkoopprijzen/">Inkoopprijzen</a><a href="/verkopen/edelmetalen/">Edelmetaal verkopen</a></div>
      <div class="xg-std-col"><h3>Markt</h3><a href="/lexicon/spotprijs/">Wat is de spotprijs?</a><a href="/lexicon/troy-ounce/">Troy ounce</a><a href="/lexicon/fineness-zuiverheid/">Zuiverheid</a></div>
    </div>
    <div class="xg-std-quick"><span class="xg-ql-label">Snel:</span><a class="xg-ql" href="/dagprijzen/goudprijs/">Goudprijs</a><a class="xg-ql" href="/dagprijzen/zilverprijs/">Zilverprijs</a><a class="xg-ql" href="/dagprijzen/">Alle prijzen</a></div>`,
    service:`<div class="xg-std-grid">
      <div class="xg-std-col"><h3>Diensten</h3><a href="/service/gratis-taxatie/">Gratis taxatie</a><a href="/service/thuisbezoek/">Thuisbezoek</a><a href="/service/inruilen/">Inruilen</a></div>
      <div class="xg-std-col"><h3>Hulp</h3><a href="/service/faq/">FAQ</a><a href="/contact/">Contact</a><a href="/service/hoe-werkt-het/">Hoe werkt het?</a></div>
      <div class="xg-std-col"><h3>Locaties</h3><a href="/kantoren/">Alle vestigingen</a><a href="/kantoren/amsterdam/">Amsterdam</a><a href="/kantoren/rotterdam/">Rotterdam</a></div>
    </div>
    <div class="xg-std-quick"><span class="xg-ql-label">Snel:</span><a class="xg-ql" href="/service/gratis-taxatie/">Gratis taxatie</a><a class="xg-ql" href="/afspraak/">Afspraak plannen</a></div>`,
    overons:`<div class="xg-std-grid">
      <div class="xg-std-col"><h3>Over XGOUD</h3><a href="/over-ons/bedrijf/">Bedrijf</a><a href="/over-ons/team/">Team</a><a href="/over-ons/geschiedenis/">Geschiedenis</a></div>
      <div class="xg-std-col"><h3>Vertrouwen</h3><a href="/over-ons/beoordelingen/">Beoordelingen</a><a href="/over-ons/certificaten/">Certificaten</a><a href="/over-ons/partners/">Partners</a></div>
      <div class="xg-std-col"><h3>Media</h3><a href="/over-ons/nieuws/">Nieuws</a><a href="/over-ons/pers/">Pers</a><a href="/over-ons/vacatures/">Vacatures</a></div>
    </div>
    <div class="xg-std-quick"><span class="xg-ql-label">Snel:</span><a class="xg-ql" href="/over-ons/beoordelingen/">Reviews</a><a class="xg-ql" href="/kantoren/">Vestigingen</a></div>`
  };

  // Stap 1 van de wizard: productgroep. Daarna duikt hij in WIZ[groep].
  const GROUPS = {
    edelmetalen:{lbl:'Edelmetalen',desc:'Goud, zilver, platina, palladium'},
    edelstenen :{lbl:'Edelstenen', desc:'Diamant, saffier, smaragd, robijn'},
    horloges   :{lbl:'Horloges',   desc:'Rolex, Omega, Patek Philippe e.a.'}
  };

  let ws={step:1,group:null,l2k:null,l2l:null,l3k:null,l3l:null};
  let curMenu=null;
  let closeT=null;

  function openMega(key){
    clearTimeout(closeT);
    navItems.forEach(n=>n.classList.toggle('active', n.dataset.menu===key));
    if(key==='verkopen'){
      if(curMenu!=='verkopen'){ ws={step:1,group:null,l2k:null,l2l:null,l3k:null,l3l:null}; }
      renderWiz();
    } else if(STD[key]){
      megaContent.innerHTML=STD[key];
    } else { curMenu=null; return; }
    curMenu=key;
    megaMenu.classList.add('active');
  }
  function closeMega(){
    closeT=setTimeout(()=>{
      megaMenu.classList.remove('active');
      navItems.forEach(n=>n.classList.remove('active'));
      curMenu=null;
    },200);
  }
  navItems.forEach(i=>{ i.addEventListener('mouseenter',()=>openMega(i.dataset.menu)); i.addEventListener('mouseleave',closeMega); });
  if(megaMenu){
    megaMenu.addEventListener('mouseenter',()=>clearTimeout(closeT));
    megaMenu.addEventListener('mouseleave',closeMega);
  }

  function sc(n){ return ws.step>n?'xg-step done':ws.step===n?'xg-step active':'xg-step'; }

  // Labels per stap: [Productgroep, ...stappen van de gekozen groep].
  function wizLabels(){
    const base=['Productgroep'];
    return ws.group ? base.concat(WIZ[ws.group].steps.map(s=>s.replace('Kies uw ',''))) : base.concat(['Type','Categorie','Product']);
  }

  function renderWiz(){
    const labels=wizLabels(); const total=labels.length; const s=ws.step;
    const chosen=[ ws.group?GROUPS[ws.group].lbl:null, ws.l2l, ws.l3l ];
    const topLbl = s===1 ? 'Kies uw productgroep' : (WIZ[ws.group].steps[s-2]||'Uw keuze');
    const pct = total>1 ? ((s-1)/(total-1))*100 : 0;
    let dots='';
    for(let i=1;i<=total;i++){
      dots+=`<div class="${sc(i)}"><div class="xg-step-num">${s>i?'✓':i}</div><div class="xg-step-lbl">${chosen[i-1]||labels[i-1]}</div></div>`;
    }
    const qlSrc = ws.group ? WIZ[ws.group].ql : ['Goud verkopen','Rolex','Diamant'];
    const qlItems = qlSrc.map(l=>`<a class="xg-ql" href="#">${l}</a>`).join('');

    megaContent.innerHTML=`<div class="xg-wizard">
      <div class="xg-wiz-top">
        <div class="xg-wiz-label">${topLbl}</div>
        <div class="xg-wiz-search">
          <span class="xg-wiz-search-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg></span>
          <input type="text" placeholder="Snel zoeken…" id="xgWizSI">
        </div>
        ${s>1?'<button class="xg-back-btn" id="xgBackBtn">← Terug</button>':''}
      </div>
      <div class="xg-progress">
        <div class="xg-prog-line"><div class="xg-prog-fill" style="width:${pct}%"></div></div>
        <div class="xg-prog-steps">${dots}</div>
      </div>
      <div id="xgWizBody"></div>
      <div class="xg-quicklinks"><span class="xg-ql-label">Populair:</span>${qlItems}</div>
    </div>`;

    renderWizBody();

    const bb=document.getElementById('xgBackBtn');
    if(bb) bb.addEventListener('click',()=>{
      if(ws.step===2){ws.step=1;ws.group=null;}
      else if(ws.step===3){ws.step=2;ws.l2k=null;ws.l2l=null;}
      else if(ws.step===4){ws.step=3;ws.l3k=null;ws.l3l=null;}
      renderWiz();
    });
    const si=document.getElementById('xgWizSI');
    if(si) si.addEventListener('input',()=>{
      const q=si.value.toLowerCase();
      document.querySelectorAll('.xg-opt').forEach(o=>{ o.style.display=o.textContent.toLowerCase().includes(q)?'':'none'; });
    });
  }

  // Bestemmings-URL voor de wizard, binnen de /verkopen/-structuur, met preset.
  // s1 = type-slug (metaal/steen/merk), s2 = categorie-slug (categorie/collectie):
  //   /verkopen/{groep}/{s1}/{s2}/
  function wizDestination(group, s1, s2, prod){
    const q='?preset='+encodeURIComponent((s1||'')+'|'+(s2||'')+'|'+(prod||''));
    let path='/verkopen/'+group+'/';
    if(s1) path+=s1+'/';
    if(s1 && s2) path+=s2+'/';
    return path+q;
  }

  function renderWizBody(){
    const body=document.getElementById('xgWizBody'); const s=ws.step;
    // Stap 1: productgroep.
    if(s===1){
      let h='<div class="xg-options">';
      Object.entries(GROUPS).forEach(([k,v])=>{ h+=`<div class="xg-opt" data-g="${k}"><div class="xg-opt-title">${v.lbl}</div><div class="xg-opt-desc">${v.desc}</div></div>`; });
      body.innerHTML=h+'</div>';
      body.querySelectorAll('[data-g]').forEach(el=>el.addEventListener('click',()=>{ ws.group=el.dataset.g; ws.step=2; renderWiz(); }));
      return;
    }
    const d=WIZ[ws.group];
    // Stap 2: type (metaal/steen/merk).
    if(s===2){
      let h='<div class="xg-options">';
      Object.entries(d.opts).forEach(([k,v])=>{ h+=`<div class="xg-opt" data-l2="${k}"><div class="xg-opt-title">${v.lbl}</div><div class="xg-opt-desc">${v.desc||''}</div></div>`; });
      body.innerHTML=h+'</div>';
      body.querySelectorAll('[data-l2]').forEach(el=>el.addEventListener('click',()=>{ ws.l2k=el.dataset.l2; ws.l2l=d.opts[ws.l2k].lbl; ws.step=3; renderWiz(); }));
    // Stap 3 (laatste): categorie/collectie → naar de overzichtspagina met producten.
    } else {
      let h='<div class="xg-options">';
      Object.entries(d.opts[ws.l2k].cats).forEach(([k,v])=>{ h+=`<div class="xg-opt" data-l3="${k}"><div class="xg-opt-title">${v.lbl}</div></div>`; });
      body.innerHTML=h+'</div>';
      body.querySelectorAll('[data-l3]').forEach(el=>el.addEventListener('click',()=>{
        location.href=wizDestination(ws.group, ws.l2k, el.dataset.l3);
      }));
    }
  }

  /* ==========================================================
     SCROLL + MOBILE
     ========================================================== */
  window.addEventListener('scroll',()=>{
    const h=document.getElementById('xgHeader');
    if(h) h.classList.toggle('scrolled', window.scrollY>50);
  });

  const mobToggle = document.getElementById('xgMobToggle');
  if(mobToggle) mobToggle.addEventListener('click',()=>{
    document.getElementById('xgMobMenu').classList.add('open');
    document.body.style.overflow='hidden';
  });
  const mobClose = document.getElementById('xgMobClose');
  if(mobClose) mobClose.addEventListener('click',()=>{
    document.getElementById('xgMobMenu').classList.remove('open');
    document.body.style.overflow='';
    closeMobWiz();
  });

  /* ==========================================================
     MOBILE WIZARD  (drill-down in het mobiele menu-paneel)
     Hergebruikt WIZ-data + wizDestination(); eigen state (mws).
     ========================================================== */
  const mobNav     = document.getElementById('xgMobNav');
  const mobWiz     = document.getElementById('xgMobWiz');
  const mobWizBody = document.getElementById('xgMobWizBody');
  const mobWizBack = document.getElementById('xgMobWizBack');
  let mws = { step:1, group:null, l2k:null, l2l:null, l3k:null, l3l:null };

  function openMobWiz(){
    if(!mobWiz || !mobNav) return;
    mws = { step:1, group:null, l2k:null, l2l:null, l3k:null, l3l:null };
    mobNav.hidden = true;
    mobWiz.hidden = false;
    renderMobWiz();
  }
  function closeMobWiz(){
    if(!mobWiz || !mobNav) return;
    mobWiz.hidden = true;
    mobNav.hidden = false;
  }
  function renderMobWiz(){
    if(!mobWizBody) return;
    const s = mws.step;
    const crumb = ['Productgroep'];
    if(mws.group) crumb[0] = GROUPS[mws.group].lbl;
    if(mws.l2l) crumb.push(mws.l2l);
    if(mws.l3l) crumb.push(mws.l3l);
    const d = mws.group ? WIZ[mws.group] : null;
    const label = s===1 ? 'Kies uw productgroep' : (d.steps[s-2]||'Uw keuze');
    let items = '';
    if(s===1){
      Object.entries(GROUPS).forEach(([k,v])=>{
        items += `<button type="button" class="xg-mob-opt" data-g="${k}"><strong>${v.lbl}</strong><span>${v.desc||''}</span></button>`;
      });
    } else if(s===2){
      Object.entries(d.opts).forEach(([k,v])=>{
        items += `<button type="button" class="xg-mob-opt" data-l2="${k}"><strong>${v.lbl}</strong><span>${v.desc||''}</span></button>`;
      });
    } else {
      Object.entries(d.opts[mws.l2k].cats).forEach(([k,v])=>{
        items += `<button type="button" class="xg-mob-opt" data-l3="${k}"><strong>${v.lbl}</strong></button>`;
      });
    }
    mobWizBody.innerHTML =
      `<div class="xg-mob-wiz-crumb">${crumb.join(' › ')}</div>
       <div class="xg-mob-wiz-label">${label}</div>
       <div class="xg-mob-opts">${items}</div>`;

    mobWizBody.querySelectorAll('[data-g]').forEach(el=>el.addEventListener('click',()=>{ mws.group=el.dataset.g; mws.step=2; renderMobWiz(); }));
    mobWizBody.querySelectorAll('[data-l2]').forEach(el=>el.addEventListener('click',()=>{ mws.l2k=el.dataset.l2; mws.l2l=d.opts[mws.l2k].lbl; mws.step=3; renderMobWiz(); }));
    mobWizBody.querySelectorAll('[data-l3]').forEach(el=>el.addEventListener('click',()=>{
      location.href = wizDestination(mws.group, mws.l2k, el.dataset.l3);
    }));
  }
  if(mobWizBack) mobWizBack.addEventListener('click',()=>{
    if(mws.step===4){ mws.step=3; mws.l3k=null; mws.l3l=null; renderMobWiz(); }
    else if(mws.step===3){ mws.step=2; mws.l2k=null; mws.l2l=null; renderMobWiz(); }
    else if(mws.step===2){ mws.step=1; mws.group=null; renderMobWiz(); }
    else { closeMobWiz(); }
  });
  document.querySelectorAll('[data-mobwiz]').forEach(b=>{
    b.addEventListener('click',()=>openMobWiz());
  });

  /* Footer-accordion (alleen mobiel <=600px): klik op kop klapt kolom in/uit. */
  const FOOT_MQ = window.matchMedia('(max-width: 600px)');
  document.querySelectorAll('nav.footer__col .footer__heading').forEach(h=>{
    h.addEventListener('click',()=>{
      if(!FOOT_MQ.matches) return;
      h.parentElement.classList.toggle('is-open');
    });
  });

})();
