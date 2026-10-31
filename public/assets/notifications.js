/* Messages et confirmations : amélioration progressive, les messages serveur restent visibles sans JavaScript. */
(() => {
  const dialog = document.createElement('dialog');
  if (typeof dialog.showModal !== 'function') return;
  dialog.className = 'prime-popup';
  dialog.setAttribute('aria-labelledby', 'prime-popup-title');
  dialog.setAttribute('aria-describedby', 'prime-popup-message');
  dialog.innerHTML = '<div class="prime-popup-accent"></div><div class="prime-popup-inner"><div class="prime-popup-head"><div class="prime-popup-icon" aria-hidden="true"></div><button type="button" class="prime-popup-close" aria-label="Fermer">×</button></div><div class="prime-popup-kicker">PRIME FIT · NOTIFICATION</div><h2 id="prime-popup-title"></h2><p id="prime-popup-message"></p><div class="prime-popup-actions"><button type="button" class="prime-popup-cancel">Annuler</button><button type="button" class="primary prime-popup-accept">Compris</button></div></div>';
  document.body.appendChild(dialog);
  const title=dialog.querySelector('#prime-popup-title'), message=dialog.querySelector('#prime-popup-message');
  const icon=dialog.querySelector('.prime-popup-icon'), accept=dialog.querySelector('.prime-popup-accept');
  const cancel=dialog.querySelector('.prime-popup-cancel'), close=dialog.querySelector('.prime-popup-close');
  const labels={success:['Succès','✓'],error:['Une erreur est survenue','!'],warning:['Attention','!'],info:['Information','i'],confirm:['Confirmation','?']};
  const queue=[]; let current=null;
  const advance=() => {
    if (current || !queue.length) return;
    current=queue.shift(); const type=Object.prototype.hasOwnProperty.call(labels,current.type) ? current.type : 'info';
    dialog.dataset.type=type; title.textContent=current.title || labels[type][0]; message.textContent=current.message;
    icon.textContent=labels[type][1]; cancel.hidden=type!=='confirm'; accept.textContent=type==='confirm' ? 'Confirmer' : 'Compris';
    try { dialog.showModal(); if (current.source) current.source.hidden=true; (type==='confirm' ? cancel : accept).focus(); }
    catch (_) { current=null; advance(); }
  };
  const show=(type,text,options={}) => {
    if (!text || !String(text).trim()) return;
    queue.push({type,message:String(text),title:options.title || '',source:options.source || null,onConfirm:options.onConfirm || null}); advance();
  };
  window.PrimeNotify={show};
  accept.addEventListener('click', () => { if (current) current.accepted=true; dialog.close(); });
  cancel.addEventListener('click', () => dialog.close());
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    const previous=current; current=null;
    if (previous?.accepted && previous.onConfirm) previous.onConfirm();
    advance();
  });
  // Le navigateur garde le focus à l'intérieur du dialogue et restaure le focus à sa fermeture.
  document.querySelectorAll('[data-notification]').forEach(node => {
    const text=node.children.length ? Array.from(node.children).map(child=>child.textContent.trim()).filter(Boolean).join(' — ') : node.textContent.trim();
    show(node.dataset.notification,text,{source:node});
  });
  // Les confirmations natives restent utilisables si le dialogue n'est pas pris en charge.
  document.addEventListener('click', e => {
    const button=e.target.closest('button[data-confirm]');
    if (!button || !button.form || button.disabled) return;
    e.preventDefault(); e.stopImmediatePropagation();
    const form=button.form;
    show('confirm',button.dataset.confirm,{onConfirm:() => {
      if (typeof form.requestSubmit === 'function') form.requestSubmit(button);
      else if (form.reportValidity()) form.submit();
    }});
  }, true);
})();
