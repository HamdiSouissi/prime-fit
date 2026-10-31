/* Messages et confirmations : amélioration progressive, les messages serveur restent visibles sans JavaScript. */
(() => {
  const dialog = document.createElement('dialog');
  if (typeof dialog.showModal !== 'function') return;
  dialog.className = 'prime-popup';
  dialog.setAttribute('aria-labelledby', 'prime-popup-title');
  dialog.setAttribute('aria-describedby', 'prime-popup-message');
  dialog.innerHTML = '<div class="prime-popup-accent"></div><div class="prime-popup-inner"><div class="prime-popup-head"><div class="prime-popup-icon" aria-hidden="true"></div><button type="button" class="prime-popup-close" aria-label="Fermer">×</button></div><div class="prime-popup-kicker">HORIZON SPORTIF DE BIR EL HAFEY · NOTIFICATION</div><h2 id="prime-popup-title"></h2><p id="prime-popup-message"></p><div class="prime-popup-member" hidden></div><div class="prime-popup-actions"><button type="button" class="prime-popup-cancel">Annuler</button><button type="button" class="primary prime-popup-accept">Compris</button></div></div>';
  document.body.appendChild(dialog);
  const title=dialog.querySelector('#prime-popup-title'), message=dialog.querySelector('#prime-popup-message');
  const icon=dialog.querySelector('.prime-popup-icon'), accept=dialog.querySelector('.prime-popup-accept');
  const cancel=dialog.querySelector('.prime-popup-cancel'), close=dialog.querySelector('.prime-popup-close');
  const member=dialog.querySelector('.prime-popup-member'), kicker=dialog.querySelector('.prime-popup-kicker');
  const labels={success:['Succès','✓'],error:['Une erreur est survenue','!'],warning:['Attention','!'],info:['Information','i'],confirm:['Confirmation','?']};
  const queue=[]; let current=null;
  const advance=() => {
    if (current || !queue.length) return;
    current=queue.shift(); const type=Object.prototype.hasOwnProperty.call(labels,current.type) ? current.type : 'info';
    dialog.dataset.type=type; dialog.dataset.kind=current.kind || 'default';
    title.textContent=current.title || labels[type][0]; message.textContent=current.message;
    kicker.textContent=current.kind==='access' ? 'HORIZON SPORTIF DE BIR EL HAFEY · CONTRÔLE D’ACCÈS' : 'HORIZON SPORTIF DE BIR EL HAFEY · NOTIFICATION';
    icon.textContent=labels[type][1]; cancel.hidden=type!=='confirm'; accept.textContent=type==='confirm' ? 'Confirmer' : (current.kind==='access' ? 'Fermer' : 'Compris');
    member.replaceChildren(); member.hidden=!current.member;
    if (current.member) {
      const head=document.createElement('div'); head.className='prime-member-head';
      const avatar=document.createElement('span'); avatar.className='prime-member-avatar'; avatar.setAttribute('aria-hidden','true');
      avatar.textContent=current.member.name.trim().split(/\s+/).slice(0,2).map(part=>Array.from(part)[0] || '').join('').toLocaleUpperCase();
      const person=document.createElement('div'); const eyebrow=document.createElement('span'); eyebrow.className='prime-member-eyebrow'; eyebrow.textContent='FICHE ADHÉRENT';
      const name=document.createElement('strong'); name.className='prime-member-name'; name.textContent=current.member.name;
      person.append(eyebrow,name); head.append(avatar,person); member.append(head);
      const list=document.createElement('dl'); list.className='prime-member-grid';
      current.member.details.forEach(item => {
        const row=document.createElement('div'); const label=document.createElement('dt'); const value=document.createElement('dd');
        label.textContent=item.label; value.textContent=item.value; row.append(label,value); list.append(row);
      });
      member.append(list);
    }
    try { dialog.showModal(); if (current.source) current.source.hidden=true; (type==='confirm' ? cancel : accept).focus(); }
    catch (_) { current=null; advance(); }
  };
  const show=(type,text,options={}) => {
    if (!text || !String(text).trim()) return;
    queue.push({type,message:String(text),title:options.title || '',source:options.source || null,onConfirm:options.onConfirm || null,kind:options.kind || '',member:options.member || null}); advance();
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
    const kind=node.dataset.notificationKind || '';
    const name=node.querySelector('[data-access-name]');
    const reason=node.querySelector('[data-access-reason]');
    const memberInfo=name ? {name:name.textContent.trim(),details:Array.from(node.querySelectorAll('[data-access-detail]')).map(row=>({label:row.querySelector('dt')?.textContent.trim() || '',value:row.querySelector('dd')?.textContent.trim() || ''}))} : null;
    const text=reason ? reason.textContent.trim() : (node.children.length ? Array.from(node.children).map(child=>child.textContent.trim()).filter(Boolean).join(' — ') : node.textContent.trim());
    show(node.dataset.notification,text,{source:node,kind,title:node.dataset.notificationTitle || '',member:memberInfo});
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
