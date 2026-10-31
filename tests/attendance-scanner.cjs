const vm=require('vm'),fs=require('fs'); const els={};
for(const id of ['start-scanner','stop-scanner','qr-reader','scanner-message','scan-form','scan-value']) els[id]={hidden:false,disabled:false,value:'',textContent:'',handlers:{},addEventListener(k,fn){this.handlers[k]=fn},submit(){this.submissions=(this.submissions||0)+1}};
let onScan, stopped=0;
class Html5Qrcode {constructor(){this.isScanning=false} async start(camera,opts,success){this.isScanning=true;onScan=success} async stop(){this.isScanning=false;stopped++}}
const context={document:{getElementById:id=>els[id]},window:{isSecureContext:true},navigator:{mediaDevices:{getUserMedia(){}}},Html5Qrcode,Html5QrcodeSupportedFormats:{QR_CODE:0},console};
vm.runInNewContext(fs.readFileSync(require('path').join(__dirname,'../public/assets/attendance-scanner.js'),'utf8'),context);
(async()=>{await els['start-scanner'].handlers.click(); await onScan('invalid'); if(els['scan-form'].submissions) throw Error('invalid scan submitted'); const qr='PF1:'+'a'.repeat(64); await onScan(qr); await onScan(qr); if(els['scan-value'].value!==qr||els['scan-form'].submissions!==1||stopped!==1) throw Error('scan failed'); console.log('Scanner: QR invalide rejeté, QR valide envoyé une seule fois : OK');})().catch(e=>{console.error(e);process.exit(1)});
