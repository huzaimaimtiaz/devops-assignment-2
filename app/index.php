<?php
session_start();
require __DIR__ . '/db.php';

if (!empty($_SESSION['controller_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Controller ID and password are both required.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT id, username, password_hash, full_name, reg_no,
                        position, licence_no
                   FROM controllers
                  WHERE username = ? LIMIT 1'
            );
            $stmt->execute([$username]);
            $controller = $stmt->fetch();

            if ($controller && password_matches($password, $controller['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['controller_id'] = (int) $controller['id'];
                $_SESSION['username']      = $controller['username'];
                $_SESSION['full_name']     = $controller['full_name'];
                $_SESSION['reg_no']        = $controller['reg_no'];
                $_SESSION['position']      = $controller['position'];
                $_SESSION['licence_no']    = $controller['licence_no'];
                header('Location: home.php');
                exit;
            }
            $error = 'Access denied. Check the controller ID and password.';
        } catch (Throwable $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$status = db_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATC Flight Strip Console - Controller Sign In</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if ($status['connected']): ?><!--DB_OK--><?php endif; ?>
<div class="shell narrow">
  <div class="panel">
    <div class="panel-head" style="text-align:center">
      <div class="radar">
        <div class="sweep"></div><i></i><i></i><i></i>
      </div>
      <div class="course">Islamabad Area Control Centre</div>
      <h1>ATC Flight Strip Console</h1>
      <p class="sub">Authorised controllers only</p>
    </div>

    <div class="panel-body">
      <form method="post" action="index.php" autocomplete="off">
        <label for="username">Controller ID</label>
        <input type="text" id="username" name="username" required
               placeholder="e.g. huzaima"
               value="<?= e($_POST['username'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required
               placeholder="••••••••">

        <button type="submit">Sign in to position</button>
      </form>

      <?php if ($error !== ''): ?>
        <div class="alert bad"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="hint">
        Demo positions: <code>huzaima</code> / <code>Fa23Bds017</code>
        and <code>qasim</code> / <code>Devops2026</code>
      </div>

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
            <dt>Records</dt>
            <dd class="mono"><?= (int) $status['controllers'] ?> controllers,
                <?= (int) $status['strips'] ?> flight strips</dd>
          <?php else: ?>
            <dt>Error</dt><dd><?= e($status['error'] ?? '') ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>
</div>
</body>
</html>
