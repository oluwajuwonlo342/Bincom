const express = require('express');
const mysql = require('mysql2/promise');
const app = express();
app.use(express.json());
app.use(express.static('public'));

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'bincom_test',
  port: process.env.DB_PORT || 3306,
  waitForConnections: true, connectionLimit: 10
});
const wrap = fn => (req, res) => fn(req, res).catch(e => { console.error(e); res.status(500).json({ error: e.message }); });
const q = async (sql, p = []) => (await pool.query(sql, p))[0];

// Delta State (25) LGAs
app.get('/api/lgas', wrap(async (_, res) =>
  res.json(await q('SELECT lga_id, lga_name FROM lga WHERE state_id = 25 ORDER BY lga_name'))));

// Wards in an LGA (chained combo)
app.get('/api/wards', wrap(async (req, res) =>
  res.json(await q('SELECT uniqueid, ward_id, ward_name FROM ward WHERE lga_id = ? ORDER BY ward_name', [req.query.lga_id]))));

// Polling units in a ward (chained combo)
app.get('/api/polling-units', wrap(async (req, res) =>
  res.json(await q(`SELECT uniqueid, polling_unit_name, polling_unit_number FROM polling_unit
    WHERE lga_id = ? AND ward_id = ? ORDER BY polling_unit_name`, [req.query.lga_id, req.query.ward_id]))));

app.get('/api/parties', wrap(async (_, res) =>
  res.json(await q('SELECT DISTINCT partyid FROM party ORDER BY partyid'))));

// Q1: result for one polling unit
app.get('/api/results/pu/:id', wrap(async (req, res) =>
  res.json(await q(`SELECT party_abbreviation, party_score FROM announced_pu_results
    WHERE polling_unit_uniqueid = ? ORDER BY party_score DESC`, [req.params.id]))));

// Q2: summed result per LGA (computed from polling units, NOT announced_lga_results)
app.get('/api/results/lga/:id', wrap(async (req, res) =>
  res.json(await q(`SELECT r.party_abbreviation, SUM(r.party_score) AS total
    FROM announced_pu_results r JOIN polling_unit p ON r.polling_unit_uniqueid = p.uniqueid
    WHERE p.lga_id = ? GROUP BY r.party_abbreviation ORDER BY total DESC`, [req.params.id]))));

// Q3: create polling unit + results for all parties (transaction)
app.post('/api/polling-units', wrap(async (req, res) => {
  const { lga_id, ward_id, ward_uniqueid, name, number, entered_by, scores } = req.body;
  if (!lga_id || !ward_id || !name || !scores || !Object.keys(scores).length)
    return res.status(400).json({ error: 'Missing required fields' });
  for (const v of Object.values(scores))
    if (!Number.isInteger(+v) || +v < 0) return res.status(400).json({ error: 'Scores must be non-negative whole numbers' });
  const c = await pool.getConnection();
  try {
    await c.beginTransaction();
    const ip = req.ip, now = new Date(), user = entered_by || 'web';
    const [pu] = await c.query(`INSERT INTO polling_unit
      (ward_id, lga_id, uniquewardid, polling_unit_number, polling_unit_name, entered_by_user, date_entered, user_ip_address)
      VALUES (?,?,?,?,?,?,?,?)`, [ward_id, lga_id, ward_uniqueid, number || '', name, user, now, ip]);
    for (const [party, score] of Object.entries(scores))
      await c.query(`INSERT INTO announced_pu_results
        (polling_unit_uniqueid, party_abbreviation, party_score, entered_by_user, date_entered, user_ip_address)
        VALUES (?,?,?,?,?,?)`, [pu.insertId, party, +score, user, now, ip]);
    await c.commit();
    res.json({ ok: true, uniqueid: pu.insertId });
  } catch (e) { await c.rollback(); throw e; } finally { c.release(); }
}));

app.listen(process.env.PORT || 3000, () => console.log('Running'));
