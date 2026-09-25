<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$admin = get_current_admin_row();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'enable_confirm') {
        $secret = $_SESSION['pending_totp_secret'] ?? '';
        $code = trim($_POST['code'] ?? '');
        if ($secret && TOTP::verify($secret, $code)) {
            $stmt = $pdo->prepare("UPDATE admins SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
            $stmt->execute([$secret, $admin['id']]);
            unset($_SESSION['pending_totp_secret']);
            $msg = t('msg_2fa_enabled');
            $admin = get_current_admin_row();
        } else {
            $error = t('err_2fa_invalid_code');
        }
    } elseif ($action === 'disable') {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $admin['password_hash'])) {
            $stmt = $pdo->prepare("UPDATE admins SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
            $stmt->execute([$admin['id']]);
            $msg = t('msg_2fa_disabled');
            $admin = get_current_admin_row();
        } else {
            $error = t('err_wrong_password');
        }
    } elseif ($action === 'start_enable') {
        $_SESSION['pending_totp_secret'] = TOTP::generateSecret();
    }
}

$provisioningUri = null;
if (empty($admin['totp_enabled']) && !empty($_SESSION['pending_totp_secret'])) {
    $provisioningUri = TOTP::provisioningUri($_SESSION['pending_totp_secret'], $admin['username']);
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa fa-lock me-2 text-primary"></i><?= t('label_2fa_title') ?></h4>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="max-width:560px;">
  <div class="card-body">

    <?php if (!empty($admin['totp_enabled'])): ?>
      <!-- 2FA مفعّل: عرض خيار التعطيل -->
      <p class="mb-3"><span class="badge bg-success me-2"><i class="fa fa-check me-1"></i><?= t('status_2fa_enabled') ?></span></p>
      <p class="text-muted small"><?= t('hint_2fa_disable') ?></p>
      <form method="post" onsubmit="return confirm('<?= htmlspecialchars(t('confirm_2fa_disable')) ?>');">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="disable">
        <div class="mb-3">
          <label class="form-label"><?= t('label_current_password') ?></label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-outline-danger"><i class="fa fa-lock-open me-1"></i><?= t('btn_disable_2fa') ?></button>
      </form>

    <?php elseif ($provisioningUri): ?>
      <!-- مرحلة التأكيد: عرض QR Code والمفتاح اليدوي -->
      <p class="mb-3"><?= t('hint_2fa_scan') ?></p>
      <div class="text-center mb-3">
        <div id="qrcode"></div>
      </div>
      <p class="text-center small text-muted mb-4">
        <?= t('label_manual_key') ?> :
        <code><?= htmlspecialchars($_SESSION['pending_totp_secret']) ?></code>
      </p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="enable_confirm">
        <div class="mb-3">
          <label class="form-label"><?= t('label_confirm_code') ?></label>
          <input type="text" name="code" class="form-control form-control-lg text-center"
                 style="letter-spacing:.4em;" maxlength="6" pattern="\d{6}" inputmode="numeric" required placeholder="000000">
        </div>
        <button class="btn btn-primary"><i class="fa fa-check me-1"></i><?= t('btn_confirm_enable_2fa') ?></button>
      </form>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
      <script>
        new QRCode(document.getElementById('qrcode'), {
          text: <?= json_encode($provisioningUri) ?>,
          width: 200,
          height: 200
        });
      </script>

    <?php else: ?>
      <!-- غير مفعّل بعد: زر البدء -->
      <p class="mb-3"><span class="badge bg-secondary me-2"><?= t('status_2fa_disabled') ?></span></p>
      <p class="text-muted small mb-3"><?= t('hint_2fa_intro') ?></p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="start_enable">
        <button class="btn btn-primary"><i class="fa fa-shield-halved me-1"></i><?= t('btn_enable_2fa') ?></button>
      </form>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
