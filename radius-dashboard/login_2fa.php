<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
if (empty($_SESSION['pending_2fa_admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = trim($_POST['code'] ?? '');
    if (attempt_2fa_verify($code)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = t('err_2fa_invalid_code');
    }
}

$dir = is_rtl() ? 'rtl' : 'ltr';
$bootstrap_css = is_rtl()
    ? 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.rtl.min.css'
    : 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css';
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
      <div class="text-center mb-4">
        <i class="fa fa-mobile-screen-button fa-3x text-primary mb-2"></i>
        <h4 class="fw-bold"><?= t('label_2fa_title') ?></h4>
        <p class="text-muted small"><?= t('hint_2fa_enter_code') ?></p>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-3">
          <input type="text" name="code" class="form-control form-control-lg text-center"
                 style="letter-spacing:.4em;" maxlength="6" pattern="\d{6}" inputmode="numeric"
                 autocomplete="one-time-code" required autofocus placeholder="000000">
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="fa fa-check me-1"></i> <?= t('btn_verify_code') ?>
        </button>
      </form>
      <div class="text-center mt-3">
        <a href="login.php" class="small text-muted"><i class="fa fa-arrow-left me-1"></i><?= t('link_back_to_login') ?></a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
