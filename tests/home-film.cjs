const {JSDOM}=require('jsdom');
const fs=require('fs');
function setup(reduce=false) {
  const dom=new JSDOM('<section data-film><video data-film-video data-src="https://example.test/film.mp4" muted loop playsinline></video></section>',{url:'https://example.test/',runScripts:'outside-only'});
  const w=dom.window, film=w.document.querySelector('[data-film]'), video=film.querySelector('video');
  const motion={matches:reduce,addEventListener(_event,fn){this.change=fn;}};
  w.matchMedia=()=>motion; w.requestAnimationFrame=()=>{};
  Object.defineProperty(w.document,'hidden',{value:false,configurable:true});
  w.IntersectionObserver=class{constructor(cb){this.cb=cb; w.observer=this;} observe(){} disconnect(){} };
  let plays=0, pauses=0; video.play=()=>{plays++;return Promise.resolve();}; video.pause=()=>{pauses++;};
  w.eval(fs.readFileSync('public/assets/home.js','utf8'));
  const appear=visible=>w.observer.cb([{target:film,isIntersecting:visible}]);
  return {dom,w,video,motion,appear,get plays(){return plays},get pauses(){return pauses}};
}
let a=setup(); if(a.video.hasAttribute('src')) throw Error('Chargement prématuré');
a.appear(true); if(a.plays!==1 || !a.video.getAttribute('src')) throw Error('Lecture automatique');
a.appear(false); if(a.pauses<2) throw Error('Vidéo hors écran'); a.appear(true); if(a.plays!==2) throw Error('Reprise');
Object.defineProperty(a.w.document,'hidden',{value:true,configurable:true}); a.w.document.dispatchEvent(new a.w.Event('visibilitychange')); if(a.pauses<3) throw Error('Vidéo en arrière-plan'); a.dom.window.close();
let b=setup(true); b.appear(true); if(b.plays || b.video.hasAttribute('src')) throw Error('Mouvement réduit'); b.motion.matches=false; b.motion.change(); if(b.plays!==1) throw Error('Reprise après changement de préférence'); b.motion.matches=true; b.motion.change(); if(b.pauses<2) throw Error('Arrêt lors de la réduction des mouvements'); b.dom.window.close();
console.log('Accueil : vidéo automatique, visibilité et mouvement réduit OK');
