function fillSelect(sel, items, valKey, labelFn, placeholder) {
  sel.innerHTML = '';
  const o = document.createElement('option');
  o.value = ''; o.textContent = placeholder; sel.appendChild(o);
  items.forEach(i => {
    const opt = document.createElement('option');
    opt.value = i[valKey]; opt.textContent = labelFn(i); sel.appendChild(opt);
  });
  sel.disabled = items.length === 0;
}
async function getJSON(url) {
  const r = await fetch(url);
  if (!r.ok) throw new Error('Request failed');
  return r.json();
}
function setupChain(lgaSel, wardSel, puSel, onPu) {
  lgaSel.addEventListener('change', async () => {
    fillSelect(wardSel, [], 'ward_id', x => x, 'Select Ward'); wardSel.disabled = true;
    if (puSel) { fillSelect(puSel, [], 'uniqueid', x => x, 'Select Polling Unit'); puSel.disabled = true; }
    if (onPu) onPu('');
    if (!lgaSel.value) return;
    const w = await getJSON('api.php?action=wards&lga_id=' + encodeURIComponent(lgaSel.value));
    fillSelect(wardSel, w, 'ward_id', x => x.ward_name, 'Select Ward');
  });
  wardSel.addEventListener('change', async () => {
    if (!puSel) return;
    fillSelect(puSel, [], 'uniqueid', x => x, 'Select Polling Unit'); puSel.disabled = true;
    if (onPu) onPu('');
    if (!wardSel.value) return;
    const p = await getJSON('api.php?action=pus&lga_id=' + encodeURIComponent(lgaSel.value) + '&ward_id=' + encodeURIComponent(wardSel.value));
    fillSelect(puSel, p, 'uniqueid', x => (x.polling_unit_name || 'Unnamed') + (x.polling_unit_number ? ' (' + x.polling_unit_number + ')' : ''), 'Select Polling Unit');
  });
  if (puSel && onPu) puSel.addEventListener('change', () => onPu(puSel.value));
}
