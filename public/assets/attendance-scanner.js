(() => {
  const start = document.getElementById('start-scanner');
  const stop = document.getElementById('stop-scanner');
  const reader = document.getElementById('qr-reader');
  const message = document.getElementById('scanner-message');
  const form = document.getElementById('scan-form');
  let scanner = null, busy = false, submitted = false;
  if (!start) return;
  async function stopCamera() {
    if (scanner && scanner.isScanning) {
      try { await scanner.stop(); } catch (_) { /* browser may already have stopped the camera */ }
    }
    reader.hidden = true; stop.hidden = true; start.hidden = false;
  }
  start.addEventListener('click', async () => {
    if (busy) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) { message.textContent = 'Caméra indisponible : utilisez HTTPS ou localhost et autorisez la caméra.'; window.PrimeNotify?.show('error', message.textContent); return; }
    if (typeof Html5Qrcode === 'undefined') { message.textContent = 'Le lecteur QR n’a pas pu être chargé. Rechargez la page.'; window.PrimeNotify?.show('error', message.textContent); return; }
    busy = true; start.disabled = true; reader.hidden = false;
    try {
      scanner = new Html5Qrcode('qr-reader', {formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE], verbose: false});
      await scanner.start({facingMode: 'environment'}, {fps: 10, qrbox: {width: 240, height: 240}}, async text => {
        if (submitted) return;
        if (!/^PF1:[0-9a-f]{64}$/.test(text)) { message.textContent = 'Ce code n’est pas une carte Horizon Sportif de Bir El Hafey valide.'; return; }
        submitted = true; message.textContent = 'QR reconnu, vérification en cours…';
        document.getElementById('scan-value').value = text;
        await stopCamera(); form.submit();
      }, () => {});
      start.hidden = true; stop.hidden = false; message.textContent = 'Présentez le QR du membre devant la caméra.';
    } catch (_) { reader.hidden = true; message.textContent = 'Impossible d’ouvrir la caméra. Vérifiez les autorisations, HTTPS et la disponibilité de la caméra.'; window.PrimeNotify?.show('error', message.textContent); }
    finally { busy = false; start.disabled = false; }
  });
  stop.addEventListener('click', async () => { if (busy) return; busy = true; await stopCamera(); message.textContent = 'Caméra arrêtée.'; busy = false; });
})();
