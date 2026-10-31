const { JSDOM } = require('jsdom');
const fs = require('fs');
const code = fs.readFileSync('public/assets/app.js', 'utf8');
function run(finance) {
  const dom = new JSDOM(`<!doctype html><html><body>${finance ? '<canvas id="revenueChart"></canvas>' : ''}<canvas id="planChart"></canvas></body></html>`, { runScripts:'outside-only', url:'https://example.test' });
  const w=dom.window, instances=[];
  w.matchMedia=()=>({matches:true});
  w.HTMLCanvasElement.prototype.getContext=()=>({createLinearGradient:()=>({addColorStop(){}})});
  w.Chart=class { static defaults={font:{}}; constructor(ctx,config) { this.config=config; this.data=config.data; this.options=config.options; instances.push(this); } update() {} };
  w.dashboardData={planLabels:['Mensuel'],planValues:[1],...(finance ? {labels:['Jan'],values:[12]} : {})};
  w.eval(code);
  if (instances.length !== (finance ? 2 : 1) || !instances.some(c=>c.config.type==='doughnut')) throw Error('Graphiques manquants');
  if (!finance && instances.some(c=>c.config.type==='line')) throw Error('Graphique financier non autorisé');
  dom.window.close();
}
run(false); run(true);
console.log('Tableau de bord : graphiques selon le rôle OK');
