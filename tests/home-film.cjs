const {JSDOM}=require('jsdom');
const fs=require('fs');
function setup(reduce=false) {
  const dom=new JSDOM('<section data-film><video data-film-video data-src="https://example.test/film.mp4" muted loop playsinline></video><button data-film-toggle hidden></button></section>',{url:'https://example.test/',runScripts:'outside-only'});
  const w=dom.window, film=w.document.querySelector('[data-film]'), video=film.querySelector('video'), button=film.querySelector('button');
  const motion={matches:reduce,addEventListener(_event,fn){this.change=fn;}};
  w.matchMedia=()=>motion; w.requestAnimationFrame=()=>{};
  Object.defineProperty(w.document,'hidden',{value:false,configurable:true});
  w.IntersectionObserver=class{constructor(cb){this.cb=cb; w.observer=this;} observe(){} disconnect(){} };
  let plays=0, pauses=0; video.play=()=>{plays++;return Promise.resolve();}; video.pause=()=>{pauses++;};
  w.eval(fs.readFileSync('public/assets/home.js','utf8'));
  const appear=visible=>w.observer.cb([{target:film,isIntersecting:visible}]);
  return {dom,w,video,button,motion,appear,get plays(){return plays},get pauses(){return pauses}};
}
let a=setup(); if(!a.button || a.button.hidden || a.video.hasAttribute('src')) throw Error('Chargement prématuré');
a.appear(true); if(a.plays!==1 || !a.video.getAttribute('src') || !a.button.textContent.includes('PAUSE')) throw Error('Lecture automatique');
a.button.click(); if(!a.button.textContent.includes('LIRE')) throw Error('Pause'); a.appear(false); a.appear(true); if(a.plays!==1) throw Error('Pause non conservée');
a.button.click(); if(a.plays!==2) throw Error('Reprise');
a.appear(false); if(a.pauses<2) throw Error('Vidéo hors écran'); a.dom.window.close();
let b=setup(true); b.appear(true); if(b.plays || b.video.hasAttribute('src')) throw Error('Mouvement réduit'); b.button.click(); if(b.plays!==1) throw Error('Lecture volontaire'); b.motion.matches=false; b.motion.change(); b.motion.matches=true; b.motion.change(); if(!b.button.textContent.includes('LIRE')) throw Error('Changement de préférence'); b.dom.window.close();
console.log('Accueil : vidéo, visibilité, pause et mouvement réduit OK');
