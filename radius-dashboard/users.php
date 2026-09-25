<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$search = trim($_GET['q'] ?? '');
$users = get_all_users($search);
$activeSet = array_flip(get_active_username_set());

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa fa-users me-2 text-primary"></i><?= t('users_heading') ?></h4>
  <a href="user_form.php" class="btn btn-primary"><i class="fa fa-plus me-1"></i> <?= t('btn_new_user') ?></a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2">
      <div class="col-md-6">
        <input type="text" name="q" class="form-control" placeholder="<?= htmlspecialchars(t('search_placeholder')) ?>" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-primary w-100"><i class="fa fa-search"></i> <?= t('btn_search') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th><?= t('th_username') ?></th><th><?= t('th_password') ?></th><th><?= t('th_group') ?></th><th><?= t('th_expiration') ?></th><th><?= t('th_status') ?></th><th><?= t('th_actions') ?></th></tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="6" class="text-center text-muted py-4"><?= t('no_users') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
          <td><code><?= htmlspecialchars($u['password'] ?? '') ?></code></td>
          <td><?= htmlspecialchars($u['groupname'] ?: t('not_set')) ?></td>
          <td><?= htmlspecialchars($u['expiration'] ?: t('not_set')) ?></td>
          <td>
            <?php if (isset($activeSet[$u['username']])): ?>
              <span class="badge badge-active"><?= t('badge_connected') ?></span>
            <?php else: ?>
              <span class="badge bg-secondary"><?= t('badge_disconnected') ?></span>
            <?php endif; ?>
          </td>
          <td>
            <a href="user_form.php?username=<?= urlencode($u['username']) ?>" class="btn btn-sm btn-outline-primary">
              <i class="fa fa-pen"></i></a>
            <form method="post" action="user_delete.php" class="d-inline"
                  onsubmit="return confirm('<?= htmlspecialchars(t('confirm_delete_user', ['username' => $u['username']])) ?>');">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
