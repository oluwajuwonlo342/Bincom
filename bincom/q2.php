<?php
require 'config.php';
page_start('LGA Summed Result', 'q2.php');
$lgas = get_lgas();
$sel = $_GET['lga_id'] ?? '';
?>
<form method="get">
  <label for="lga">Local Government</label>
  <select id="lga" name="lga_id" onchange="this.form.submit()">
    <option value="">Select LGA</option>
    <?php foreach ($lgas as $l): ?>
      <option value="<?= e($l['lga_id']) ?>" <?= (string)$sel === (string)$l['lga_id'] ? 'selected' : '' ?>><?= e($l['lga_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button type="submit">View</button></noscript>
</form>
<?php
if ($sel !== '') {
 
    $st = db()->prepare('SELECT r.party_abbreviation, SUM(r.party_score) AS total
                         FROM polling_unit pu
                         JOIN announced_pu_results r ON r.polling_unit_uniqueid = pu.uniqueid
                         WHERE pu.lga_id = ?
                         GROUP BY r.party_abbreviation ORDER BY total DESC');
    $st->execute([$sel]);
    $rows = $st->fetchAll();
    $c = db()->prepare('SELECT COUNT(DISTINCT pu.uniqueid) FROM polling_unit pu
                        JOIN announced_pu_results r ON r.polling_unit_uniqueid = pu.uniqueid WHERE pu.lga_id = ?');
    $c->execute([$sel]);
    $units = (int)$c->fetchColumn();
    if (!$rows) {
        echo '<p class="muted">No polling unit results found for this LGA.</p>';
    } else {
        $grand = array_sum(array_column($rows, 'total'));
        echo '<p class="muted">Based on ' . $units . ' polling unit(s) with results.</p>';
        echo '<table><thead><tr><th>Party</th><th class="num">Total Score</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . e($r['party_abbreviation']) . '</td><td class="num">' . number_format($r['total']) . '</td></tr>';
        }
        echo '</tbody><tfoot><tr><td>Total</td><td class="num">' . number_format($grand) . '</td></tr></tfoot></table>';
    }
}
page_end();
