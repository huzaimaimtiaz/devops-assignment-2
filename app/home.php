<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['controller_id'])) {
    header('Location: index.php');
    exit;
}

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $callsign    = strtoupper(trim($_POST['callsign'] ?? ''));
    $aircraft    = strtoupper(trim($_POST['aircraft'] ?? ''));
    $origin      = strtoupper(trim($_POST['origin'] ?? ''));
    $destination = strtoupper(trim($_POST['destination'] ?? ''));
    $level       = trim($_POST['level'] ?? '');
    $statusCode  = strtoupper(trim($_POST['status'] ?? ''));
    $remarks     = trim($_POST['remarks'] ?? '');

    if ($callsign === '' || $origin === '' || $destination === '') {
        $error = 'Callsign, origin and destination are required.';
    } elseif (!in_array($statusCode, STRIP_STATUSES, true)) {
        $error = 'Select a valid strip status.';
    } elseif ($level !== '' && !ctype_digit($level)) {
        $error = 'Flight level must be a number, for example 350.';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO flight_strips
                   (controller_id, callsign, aircraft_type, origin,
                    destination, flight_level, status, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $_SESSION['controller_id'],
                $callsign,
                $aircraft !== '' ? $aircraft : null,
                $origin,
                $destination,
                $level !== '' ? (int) $level : null,
                $statusCode,
                $remarks !== '' ? $remarks : null,
            ]);
            header('Location: home.php?filed=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Could not file the strip: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['filed'])) {
    $notice = 'Flight strip filed to the database.';
}

$strips = [];
try {
    $stmt = db()->prepare(
        'SELECT s.id, s.callsign, s.aircraft_type, s.origin, s.destination,
                s.flight_level, s.status, s.remarks, s.filed_at, c.username
           FROM flight_strips s
           JOIN controllers c ON c.id = s.controller_id
          ORDER BY s.filed_at DESC, s.id DESC
          LIMIT 50'
    );
    $stmt->execute();
    $strips = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: 'Could not load the strip board: ' . $e->getMessage();
}

$status = db_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATC Flight Strip Console - Position</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if ($status['connected']): ?><!--DB_OK--><?php endif; ?>
<div class="shell">
  <div class="panel">
    <div class="panel-head">
      <div class="topbar">
        <div>
          <div class="course">Islamabad Area Control Centre</div>
          <h1>ATC Flight Strip Console</h1>
          <p class="sub">
            Position <strong><?= e($_SESSION['position']) ?></strong>, manned by
            <strong><?= e($_SESSION['full_name']) ?></strong>
            (licence <?= e($_SESSION['licence_no']) ?>)
          </p>
        </div>
        <div class="clock">
          <strong><?= e(utc_clock()) ?></strong>
          <a href="logout.php">Hand over / sign out</a>
        </div>
      </div>
    </div>

    <div class="panel-body">
      <div class="reg-plate">
        <div class="label">Controller Registration Number</div>
        <div class="value"><?= e($_SESSION['reg_no']) ?></div>
      </div>

      <?php if ($notice !== ''): ?>
        <div class="alert good"><?= e($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert bad"><?= e($error) ?></div>
      <?php endif; ?>

      <h2>File a flight strip</h2>
      <form method="post" action="home.php" autocomplete="off">
        <div class="grid">
          <div>
            <label for="callsign">Callsign</label>
            <input type="text" id="callsign" name="callsign" maxlength="12"
                   placeholder="PK309" required>
          </div>
          <div>
            <label for="aircraft">Aircraft type</label>
            <input type="text" id="aircraft" name="aircraft" maxlength="12"
                   placeholder="A320">
          </div>
          <div>
            <label for="origin">Origin (ICAO)</label>
            <input type="text" id="origin" name="origin" maxlength="4"
                   placeholder="OPIS" required>
          </div>
          <div>
            <label for="destination">Destination (ICAO)</label>
            <input type="text" id="destination" name="destination" maxlength="4"
                   placeholder="OPKC" required>
          </div>
          <div>
            <label for="level">Flight level</label>
            <input type="number" id="level" name="level" min="0" max="600"
                   placeholder="350">
          </div>
          <div>
            <label for="status">Status</label>
            <select id="status" name="status" required>
              <?php foreach (STRIP_STATUSES as $option): ?>
                <option value="<?= e($option) ?>"><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label for="remarks">Remarks</label>
        <textarea id="remarks" name="remarks" maxlength="255"
                  placeholder="Cleared direct, expect runway 28L"></textarea>

        <button type="submit">File strip</button>
      </form>

      <h2>Active strip board (<?= count($strips) ?>)</h2>
      <?php if (!$strips): ?>
        <p class="sub">No strips on the board. File the first one above.</p>
      <?php else: ?>
        <table class="board">
          <thead>
            <tr>
              <th>#</th><th>Callsign</th><th>Type</th><th>Route</th>
              <th>FL</th><th>Status</th><th>Remarks</th><th>By</th><th>Filed</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($strips as $strip): ?>
            <tr>
              <td class="strip-id"><?= (int) $strip['id'] ?></td>
              <td class="callsign"><?= e($strip['callsign']) ?></td>
              <td><?= e($strip['aircraft_type'] ?? '-') ?></td>
              <td class="route"><?= e($strip['origin']) ?> &rarr; <?= e($strip['destination']) ?></td>
              <td><?= $strip['flight_level'] !== null ? 'FL' . (int) $strip['flight_level'] : '-' ?></td>
              <td><span class="tag <?= e($strip['status']) ?>"><?= e($strip['status']) ?></span></td>
              <td class="remarks"><?= e($strip['remarks'] ?? '') ?></td>
              <td><?= e($strip['username']) ?></td>
              <td class="when"><?= e($strip['filed_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="meta">
        <dl>
          <dt>Deployment</dt><dd><?= e(app_part()) ?></dd>
          <dt>Database link</dt>
          <dd>
            <?php if ($status['connected']): ?>
              <span class="pill up">CONNECTED</span>
            <?php else: ?>
              <span class="pill down">NO LINK</span>
            <?php endif; ?>
          </dd>
          <dt>Database host</dt><dd class="mono"><?= e($status['host']) ?></dd>
          <dt>Database name</dt><dd class="mono"><?= e($status['database']) ?></dd>
          <dt>Application DB user</dt><dd class="mono"><?= e($status['user']) ?></dd>
          <?php if ($status['connected']): ?>
            <dt>MySQL server</dt><dd class="mono"><?= e($status['server']) ?></dd>
          <?php endif; ?>
          <dt>Web container</dt><dd class="mono"><?= e(gethostname()) ?></dd>
          <dt>Page served</dt><dd class="mono"><?= e(date('Y-m-d H:i:s T')) ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>
</body>
</html>
