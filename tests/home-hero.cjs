const {JSDOM}=require('jsdom');
const fs=require('fs');
function make(reduced=false) {
 const dom=new JSDOM(`<section class="hero"><div class="hero-slide is-active"></div><div class="hero-slide"></div><div class="hero-slide"></div><div class="hero-slide"></div><div data-hero-message><h1>Message un</h1></div><div data-hero-message hidden><h2>Message deux</h2></div><div data-hero-message hidden><h2>Message trois</h2></div><div data-hero-message hidden><h2>Message quatre</h2></div><div data-hero-controls hidden><span data-hero-count></span><button data-hero-prev></button><button data-hero-next></button><button data-hero-toggle></button></div><span data-hero-announcement></span></section>`,{url:'https://example.test/',runScripts:'outside-only'});
 const w=dom.window, hero=w.document.querySelector('.hero');
 w.matchMedia=()=>({matches:reduced}); w.requestAnimationFrame=()=>{}; w.setInterval=(fn, delay)=>{w.tick=fn; w.intervalDelay=delay;};
 Object.defineProperty(w.document,'hidden',{value:false}); hero.getBoundingClientRect=()=>({top:0,bottom:500});
 w.eval(fs.readFileSync('public/assets/home.js','utf8'));
 return {dom,w,hero,slides:hero.querySelectorAll('.hero-slide'), messages:hero.querySelectorAll('[data-hero-message]')};
}
const check=(a,index)=>{for(let i=0;i<4;i++) if(a.slides[i].classList.contains('is-active')!==(i===index) || a.messages[i].hidden===(i===index)) throw Error('Image et message désynchronisés : '+index);};
let a=make(); if(a.w.intervalDelay !== 2000) throw Error('La bannière doit défiler toutes les deux secondes'); if(a.hero.querySelector('[data-hero-controls]').hidden) throw Error('Commandes absentes'); check(a,0);
a.w.tick(); check(a,1);
a.hero.querySelector('[data-hero-next]').click(); a.w.tick(); check(a,2); if(!a.hero.querySelector('[data-hero-announcement]').textContent.includes('Message trois')) throw Error('Annonce absente');
a.hero.querySelector('[data-hero-toggle]').click(); a.w.tick(); check(a,3); a.w.tick(); check(a,0); a.dom.window.close();
let b=make(true); b.w.tick(); check(b,0); if(!b.hero.querySelector('[data-hero-toggle]').hidden) throw Error('Réduction du mouvement');
b.hero.querySelector('[data-hero-prev]').click(); check(b,3); b.dom.window.close();
console.log('Accueil : quatre diapositives, navigation et réduction du mouvement OK');
