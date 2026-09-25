<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = attempt_login($username, $password);
    if ($result === 'ok') {
        header('Location: dashboard.php');
        exit;
    } elseif ($result === 'need_2fa') {
        header('Location: login_2fa.php');
        exit;
    } else {
        $error = t('login_error');
    }
}

$dir = is_rtl() ? 'rtl' : 'ltr';
$bootstrap_css = is_rtl()
    ? 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.rtl.min.css'
    : 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css';
$lang_names = ['ar' => 'العربية', 'fr' => 'Français', 'en' => 'English'];
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(t('app_name')) ?></title>
<link href="<?= $bootstrap_css ?>" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
  <div class="card login-card shadow-lg">
    <div class="card-body p-4">
      <div class="d-flex justify-content-end mb-2">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="fa fa-globe me-1"></i><?= strtoupper(current_lang()) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($lang_names as $code => $label): ?>
              <li>
                <a class="dropdown-item <?= current_lang() === $code ? 'active' : '' ?>" href="<?= lang_switch_url($code) ?>">
                  <?= htmlspecialchars($label) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div class="text-center mb-4">
        <i class="fa fa-shield-halved fa-3x text-primary mb-2"></i>
        <h4 class="fw-bold"><?= htmlspecialchars(t('app_name')) ?></h4>
        <p class="text-muted small"><?= t('login_subtitle') ?></p>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-3">
          <label class="form-label"><?= t('login_username') ?></label>
          <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= t('login_password') ?></label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="fa fa-right-to-bracket me-1"></i> <?= t('login_button') ?>
        </button>
      </form>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
