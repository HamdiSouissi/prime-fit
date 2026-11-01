const {JSDOM}=require('jsdom');
const fs=require('fs');
function make({seen=false,hash='',focus=false}={}) {
  const dom=new JSDOM('<button id="outside">Lien</button><dialog class="welcome-dialog"><button data-welcome-close>Fermer</button><a href="#contact" data-welcome-link>Contact</a></dialog>',{runScripts:'outside-only',url:'https://example.test/'+hash});
  const w=dom.window, dialog=w.document.querySelector('dialog');
  w.matchMedia=()=>({matches:true}); w.requestAnimationFrame=()=>{};
  Object.defineProperty(w.document,'hidden',{value:false});
  w.setTimeout=fn=>{w.openWelcome=fn;};
  dialog.showModal=()=>{dialog.setAttribute('open','');}; dialog.close=()=>dialog.removeAttribute('open');
  if(seen) w.sessionStorage.setItem('horizon-welcome-seen','1');
  if(focus) w.document.querySelector('#outside').focus();
  w.eval(fs.readFileSync('public/assets/home.js','utf8'));
  return {dom,w,dialog};
}
let a=make(); a.w.openWelcome();
if(!a.dialog.hasAttribute('open') || a.w.document.activeElement!==a.dialog.querySelector('button') || a.w.sessionStorage.getItem('horizon-welcome-seen')!=='1') throw Error('Ouverture ou mémorisation incorrecte');
a.dialog.querySelector('button').click(); if(a.dialog.hasAttribute('open')) throw Error('Fermeture incorrecte'); a.dom.window.close();
let b=make({seen:true}); if(b.w.openWelcome) throw Error('Fenêtre répétée'); b.dom.window.close();
let c=make({hash:'#contact'}); if(c.w.openWelcome) throw Error('Interruption du lien direct'); c.dom.window.close();
let d=make({focus:true}); d.w.openWelcome(); if(d.dialog.hasAttribute('open')) throw Error('Interruption de la navigation clavier'); d.dom.window.close();
let e=make(); e.w.openWelcome(); e.dialog.dispatchEvent(new e.w.MouseEvent('click',{bubbles:true})); if(e.dialog.hasAttribute('open')) throw Error('Fermeture par fond incorrecte'); e.dom.window.close();
console.log('Accueil : bienvenue et fréquence OK');
