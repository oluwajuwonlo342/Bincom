<?php require 'config.php';
 page_start('Polling Unit Result', 'q1.php'); $lgas = get_lgas(); 
 ?>
<label for="lga">Local Government</label>
<select id="lga"><option value="">Select LGA</option>
<?php foreach ($lgas as $l): ?><option value="<?= e($l['lga_id']) ?>"><?= e($l['lga_name']) ?></option><?php endforeach; ?>
</select>
<label for="ward">Ward</label>
<select id="ward" disabled><option value="">Select Ward</option></select>
<label for="pu">Polling Unit</label>
<select id="pu" disabled><option value="">Select Polling Unit</option></select>
<div id="out"></div>
<script src="app.js">

</script>
<script>
const out = document.getElementById('out');
setupChain(document.getElementById('lga'), document.getElementById('ward'), document.getElementById('pu'), async (pu) => {
  if (!pu) { out.innerHTML = ''; return; }
  const rows = await getJSON('api.php?action=pu_results&pu=' + encodeURIComponent(pu));
  if (!rows.length) { out.innerHTML = '<p class="muted">No results recorded for this polling unit.</p>'; return; }
  let total = 0, h = '<table><thead><tr><th>Party</th><th class="num">Score</th></tr></thead><tbody>';
  rows.forEach(r => { total += +r.party_score; h += '<tr><td>' + r.party_abbreviation.replace(/</g,'&lt;') + '</td><td class="num">' + (+r.party_score).toLocaleString() + '</td></tr>'; });
  out.innerHTML = h + '</tbody><tfoot><tr><td>Total</td><td class="num">' + total.toLocaleString() + '</td></tr></tfoot></table>';
});
</script>
</main></body></html>
