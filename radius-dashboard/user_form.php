<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$editing = false;
$username = '';
$groupname = '';
$expiration = '';
$sessionTimeoutMin = '';
$bandwidth = ['down' => '', 'up' => ''];

if (!empty($_GET['username'])) {
    $editing = true;
    $username = $_GET['username'];
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT groupname FROM radusergroup WHERE username = ? ORDER BY priority LIMIT 1");
    $stmt->execute([$username]);
    $groupname = $stmt->fetchColumn() ?: '';

    $stmt = $pdo->prepare("SELECT value FROM radcheck WHERE username = ? AND attribute = 'Expiration' LIMIT 1");
    $stmt->execute([$username]);
    $exp = $stmt->fetchColumn();
    $expiration = $exp ? date('Y-m-d', strtotime($exp)) : '';

    $timeoutSec = get_user_session_timeout($username);
    $sessionTimeoutMin = $timeoutSec ? (string)round($timeoutSec / 60) : '';

    $bw = get_user_bandwidth($username);
    $bandwidth['down'] = $bw['down'] !== null ? (string)$bw['down'] : '';
    $bandwidth['up'] = $bw['up'] !== null ? (string)$bw['up'] : '';
}

$groups = get_all_groups();
include __DIR__ . '/includes/header.php';
?>
<h4 class="fw-bold mb-4">
  <i class="fa fa-user-plus me-2 text-primary"></i>
  <?= $editing ? htmlspecialchars(t('edit_user_title', ['username' => $username])) : t('add_user_title') ?>
</h4>

<div class="card">
  <div class="card-body">
    <form method="post" action="user_save.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="original_username" value="<?= htmlspecialchars($username) ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label"><?= t('label_username') ?></label>
          <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>"
                 <?= $editing ? 'readonly' : 'required' ?>>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= $editing ? t('label_password_edit_hint') : t('label_password') ?></label>
          <input type="text" name="password" class="form-control" <?= $editing ? '' : 'required' ?>>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_group') ?></label>
          <select name="groupname" class="form-select">
            <option value=""><?= t('no_group_option') ?></option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= htmlspecialchars($g) ?>" <?= $g === $groupname ? 'selected' : '' ?>>
                <?= htmlspecialchars($g) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_expiration') ?></label>
          <input type="date" name="expiration" class="form-control" value="<?= htmlspecialchars($expiration) ?>">
        </div>
      </div>

      <hr class="my-4">
      <h6 class="fw-bold mb-3"><i class="fa fa-gauge me-2 text-primary"></i><?= t('quota_section_title') ?></h6>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label"><?= t('label_session_timeout') ?></label>
          <div class="input-group">
            <input type="number" min="0" name="session_timeout" class="form-control" value="<?= htmlspecialchars($sessionTimeoutMin) ?>">
            <span class="input-group-text"><?= t('unit_minutes') ?></span>
          </div>
          <div class="form-text"><?= t('hint_session_timeout') ?></div>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= t('label_bandwidth_down') ?></label>
          <div class="input-group">
            <input type="number" min="0" name="bandwidth_down" class="form-control" value="<?= htmlspecialchars($bandwidth['down']) ?>">
            <span class="input-group-text">kbps</span>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= t('label_bandwidth_up') ?></label>
          <div class="input-group">
            <input type="number" min="0" name="bandwidth_up" class="form-control" value="<?= htmlspecialchars($bandwidth['up']) ?>">
            <span class="input-group-text">kbps</span>
          </div>
        </div>
        <div class="col-12">
          <div class="form-text"><?= t('hint_bandwidth') ?></div>
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> <?= t('btn_save') ?></button>
        <a href="users.php" class="btn btn-light"><?= t('btn_cancel') ?></a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
