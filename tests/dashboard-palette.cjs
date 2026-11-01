const {JSDOM}=require('jsdom');
const fs=require('fs');
const css=fs.readFileSync('public/assets/style.css','utf8');
if (!/--accent:#E8FF00/i.test(css) || !/--accent-text:#586200/i.test(css) || !/--chart-accent:#586200/i.test(css)) throw Error('Palette ou contraste du thème absents');
if (/255,212,0|#ffd400|#ffb800/i.test(css)) throw Error('Ancien jaune dans la feuille de style');
const dom=new JSDOM('<button id="themeToggle"></button><canvas id="revenueChart"></canvas><canvas id="planChart"></canvas><canvas id="reportChart"></canvas><canvas id="fitnessChart"></canvas>',{runScripts:'outside-only',url:'https://example.test/'});
const w=dom.window, charts=[];
w.matchMedia=()=>({matches:true});
w.getComputedStyle=()=>({getPropertyValue:(key)=>key==='--chart-accent' ? (w.document.documentElement.classList.contains('light') ? '#586200' : '#E8FF00') : '#777'});
w.HTMLCanvasElement.prototype.getContext=()=>({createLinearGradient:()=>({addColorStop(){}})});
w.Chart=class { static defaults={font:{}}; constructor(ctx,config){this.config=config;this.data=config.data;this.options=config.options;charts.push(this);} update(){} };
w.dashboardData={planLabels:['Mensuel'],planValues:[1],labels:['Jan'],values:[1]}; w.reportData={labels:['Jan'],income:[1],expense:[1]}; w.fitnessData={labels:['Jan'],values:[1]};
w.eval(fs.readFileSync('public/assets/app.js','utf8'));
const check=color=>{
  for(const c of charts) {
    const ds=c.data.datasets[0];
    if(c.config.type==='line' && ds.borderColor!==color) throw Error('Couleur de courbe incorrecte');
    if(c.config.type==='bar' && ds.backgroundColor!==color) throw Error('Couleur du rapport incorrecte');
    if(c.config.type==='doughnut' && ds.backgroundColor[0]!==color) throw Error('Couleur du diagramme incorrecte');
  }
};
if(charts.length!==4) throw Error('Graphiques absents'); check('#E8FF00'); w.document.querySelector('#themeToggle').click(); check('#586200'); w.document.querySelector('#themeToggle').click(); check('#E8FF00');
dom.window.close(); console.log('Palette : jaune du site et contraste des graphiques clair/sombre OK');
