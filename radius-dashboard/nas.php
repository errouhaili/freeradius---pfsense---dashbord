<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$nasList = $pdo->query("SELECT * FROM nas ORDER BY id DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa fa-server me-2 text-primary"></i><?= t('nas_heading') ?></h4>
  <a href="nas_form.php" class="btn btn-primary"><i class="fa fa-plus me-1"></i> <?= t('btn_add_nas') ?></a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th><?= t('th_shortname') ?></th><th><?= t('th_ip') ?></th><th><?= t('th_type') ?></th><th><?= t('th_port') ?></th><th><?= t('th_description') ?></th><th><?= t('th_actions') ?></th></tr>
      </thead>
      <tbody>
        <?php if (!$nasList): ?>
          <tr><td colspan="6" class="text-center text-muted py-4"><?= t('no_nas') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($nasList as $n): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($n['shortname']) ?></td>
          <td><?= htmlspecialchars($n['nasname']) ?></td>
          <td><span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($n['type']) ?></span></td>
          <td><?= htmlspecialchars($n['ports'] ?? t('not_set')) ?></td>
          <td><?= htmlspecialchars($n['description'] ?? t('not_set')) ?></td>
          <td>
            <a href="nas_form.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-pen"></i></a>
            <form method="post" action="nas_delete.php" class="d-inline"
                  onsubmit="return confirm('<?= htmlspecialchars(t('confirm_delete_nas')) ?>');">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
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
