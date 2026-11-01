const {JSDOM}=require('jsdom');
const fs=require('fs');
function make(reduced=false) {
 const dom=new JSDOM('<section class="hero"><div class="hero-slide is-active"></div><div class="hero-slide"></div><div class="hero-slide"></div><div data-hero-controls hidden><span data-hero-count></span><button data-hero-prev></button><button data-hero-next></button><button data-hero-toggle></button></div></section>',{url:'https://example.test/',runScripts:'outside-only'});
 const w=dom.window, hero=w.document.querySelector('.hero');
 w.matchMedia=()=>({matches:reduced}); w.requestAnimationFrame=()=>{}; w.setInterval=fn=>{w.tick=fn;};
 Object.defineProperty(w.document,'hidden',{value:false}); hero.getBoundingClientRect=()=>({top:0,bottom:500});
 w.eval(fs.readFileSync('public/assets/home.js','utf8'));
 return {dom,w,hero,slides:hero.querySelectorAll('.hero-slide')};
}
let a=make(); if(a.hero.querySelector('[data-hero-controls]').hidden) throw Error('Commandes absentes');
a.w.tick(); if(!a.slides[1].classList.contains('is-active')) throw Error('Défilement automatique');
a.hero.querySelector('[data-hero-next]').click(); a.w.tick(); if(!a.slides[2].classList.contains('is-active')) throw Error('Pause après navigation');
a.hero.querySelector('[data-hero-toggle]').click(); a.w.tick(); if(!a.slides[0].classList.contains('is-active')) throw Error('Reprise');
a.dom.window.close();
let b=make(true); b.w.tick(); if(!b.slides[0].classList.contains('is-active') || !b.hero.querySelector('[data-hero-toggle]').hidden) throw Error('Réduction du mouvement');
b.hero.querySelector('[data-hero-prev]').click(); if(!b.slides[2].classList.contains('is-active')) throw Error('Navigation manuelle'); b.dom.window.close();
console.log('Accueil : bannière, navigation et réduction du mouvement OK');
