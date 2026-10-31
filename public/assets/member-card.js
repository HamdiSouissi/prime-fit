(() => {
  const box = document.getElementById('member-qr');
  const error = document.getElementById('card-error');
  if (box) {
    try {
      if (typeof QRCode === 'undefined') throw new Error('Le générateur QR n’a pas pu être chargé.');
      new QRCode(box, {text: box.dataset.qr, width: 220, height: 220, colorDark:'#000000', colorLight:'#ffffff', correctLevel: QRCode.CorrectLevel.M});
    } catch (e) { error.hidden = false; error.textContent = 'Impossible de générer le QR. Vérifiez JavaScript et rechargez la page.'; window.PrimeNotify?.show('error', error.textContent, {source: error}); }
  }
  document.getElementById('print-card')?.addEventListener('click', () => window.print());
  document.getElementById('download-qr')?.addEventListener('click', () => {
    const canvas = box?.querySelector('canvas');
    if (!canvas) { error.hidden = false; error.textContent = 'Téléchargement indisponible. Utilisez l’impression de la carte.'; window.PrimeNotify?.show('error', error.textContent, {source: error}); return; }
    const padded = document.createElement('canvas'); padded.width = canvas.width + 48; padded.height = canvas.height + 48;
    const context = padded.getContext('2d'); context.fillStyle = '#fff'; context.fillRect(0, 0, padded.width, padded.height); context.drawImage(canvas, 24, 24);
    const link = document.createElement('a'); link.download = 'carte-prime-fit-qr.png'; link.href = padded.toDataURL('image/png'); link.click();
  });
})();
