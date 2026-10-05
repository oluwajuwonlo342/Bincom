<?php
require 'config.php';
$pdo = db();
$lgas = get_lgas();


$parties = $pdo->query('SELECT partyid FROM party ORDER BY partyid')->fetchAll(PDO::FETCH_COLUMN);
if (!$parties) $parties = $pdo->query('SELECT DISTINCT party_abbreviation FROM announced_pu_results ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lga = $_POST['lga_id'] ?? ''; $ward = $_POST['ward_id'] ?? '';
    $name = trim($_POST['polling_unit_name'] ?? ''); $num = trim($_POST['polling_unit_number'] ?? '');
    $desc = trim($_POST['polling_unit_description'] ?? ''); $by = trim($_POST['entered_by'] ?? '') ?: 'web_user';
    $scores = $_POST['scores'] ?? [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $w = $pdo->prepare('SELECT uniqueid FROM ward WHERE ward_id = ? AND lga_id = ?');
    $w->execute([$ward, $lga]);
    $wardUnique = $w->fetchColumn();

    if ($lga === '' || $ward === '' || $name === '' || $wardUnique === false) {
        $err = 'Please select an LGA and ward, and enter the polling unit name.';
    } else {
        foreach ($parties as $p) {
            $v = $scores[$p] ?? '';
            if ($v === '' || !ctype_digit((string)$v)) { $err = 'Enter a whole-number score (0 or more) for every party.'; break; }
        }
    }
    if (!$err) {
        try {
            $pdo->beginTransaction();
            $n = $pdo->prepare('SELECT COALESCE(MAX(polling_unit_id),0)+1 FROM polling_unit WHERE ward_id = ? AND lga_id = ?');
            $n->execute([$ward, $lga]);
            $puId = (int)$n->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO polling_unit
                (polling_unit_id, ward_id, lga_id, uniquewardid, polling_unit_number, polling_unit_name, polling_unit_description, entered_by_user, date_entered, user_ip_address)
                VALUES (?,?,?,?,?,?,?,?,NOW(),?)');
            $ins->execute([$puId, $ward, $lga, $wardUnique, $num, $name, $desc, $by, $ip]);
            $uid = $pdo->lastInsertId();
            $r = $pdo->prepare('INSERT INTO announced_pu_results
                (polling_unit_uniqueid, party_abbreviation, party_score, entered_by_user, date_entered, user_ip_address)
                VALUES (?,?,?,?,NOW(),?)');
            foreach ($parties as $p) $r->execute([$uid, $p, (int)$scores[$p], $by, $ip]);
            $pdo->commit();
            $msg = 'Saved! Polling unit "' . $name . '" and its results were stored.';
            $_POST = [];
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'Could not save the data. Please try again.';
        }
    }
}
page_start('Add New Polling Unit Results', 'q3.php');
if ($msg) echo '<div class="msg ok">' . e($msg) . '</div>';
if ($err) echo '<div class="msg err">' . e($err) . '</div>';
?>
<form method="post">
  <div class="grid">
    <div>
      <label for="lga">Local Government</label>
      <select id="lga" name="lga_id" required>
        <option value="">Select LGA</option>
        <?php foreach ($lgas as $l): ?><option value="<?= e($l['lga_id']) ?>" <?= ($_POST['lga_id'] ?? '') == $l['lga_id'] ? 'selected' : '' ?>><?= e($l['lga_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="ward">Ward</label>
      <select id="ward" name="ward_id" required disabled><option value="">Select Ward</option></select>
    </div>
    <div>
      <label for="puname">Polling Unit Name</label>
      <input type="text" id="puname" name="polling_unit_name" required value="<?= e($_POST['polling_unit_name'] ?? '') ?>">
    </div>
    <div>
      <label for="punum">Polling Unit Number</label>
      <input type="text" id="punum" name="polling_unit_number" value="<?= e($_POST['polling_unit_number'] ?? '') ?>">
    </div>
  </div>
  <label for="desc">Description (optional)</label>
  <textarea id="desc" name="polling_unit_description" rows="2"><?= e($_POST['polling_unit_description'] ?? '') ?></textarea>
  <label for="by">Entered by</label>
  <input type="text" id="by" name="entered_by" placeholder="Your name" value="<?= e($_POST['entered_by'] ?? '') ?>">

  <h3>Party Scores</h3>
  <div class="grid">
    <?php foreach ($parties as $p): ?>
      <div>
        <label for="s_<?= e($p) ?>"><?= e($p) ?></label>
        <input type="number" min="0" step="1" required id="s_<?= e($p) ?>" name="scores[<?= e($p) ?>]" value="<?= e($_POST['scores'][$p] ?? '') ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <button type="submit">Save Results</button>
</form>
<script src="app.js"></script>
<script>
const lga = document.getElementById('lga'), ward = document.getElementById('ward');
setupChain(lga, ward, null, null);
<?php if (!empty($_POST['lga_id']) && $err): ?>
// restore chained selection after a validation error
getJSON('api.php?action=wards&lga_id=<?= urlencode($_POST['lga_id']) ?>').then(w => {
  fillSelect(ward, w, 'ward_id', x => x.ward_name, 'Select Ward');
  ward.value = <?= json_encode($_POST['ward_id'] ?? '') ?>;
});
<?php endif; ?>
</script>
</main></body></html>
