<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$groups = $pdo->query("SELECT groupname, COUNT(*) members FROM radusergroup GROUP BY groupname
                        UNION
                        SELECT DISTINCT g.groupname, 0 FROM (
                          SELECT groupname FROM radgroupcheck
                          UNION SELECT groupname FROM radgroupreply
                        ) g WHERE g.groupname NOT IN (SELECT DISTINCT groupname FROM radusergroup)
                        ORDER BY groupname")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa fa-layer-group me-2 text-primary"></i><?= t('groups_heading') ?></h4>
  <a href="group_form.php" class="btn btn-primary"><i class="fa fa-plus me-1"></i> <?= t('btn_new_group') ?></a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<div class="row g-3">
  <?php if (!$groups): ?>
    <p class="text-muted"><?= t('no_groups') ?></p>
  <?php endif; ?>
  <?php foreach ($groups as $g): ?>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="fw-bold"><i class="fa fa-layer-group me-2 text-primary"></i><?= htmlspecialchars($g['groupname']) ?></h5>
        <p class="text-muted small mb-3"><?= htmlspecialchars(t('members_count', ['count' => (int)$g['members']])) ?></p>
        <a href="group_form.php?groupname=<?= urlencode($g['groupname']) ?>" class="btn btn-sm btn-outline-primary">
          <i class="fa fa-pen me-1"></i><?= t('btn_manage_policies') ?>
        </a>
        <form method="post" action="group_delete.php" class="d-inline"
              onsubmit="return confirm('<?= htmlspecialchars(t('confirm_delete_group')) ?>');">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="groupname" value="<?= htmlspecialchars($g['groupname']) ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash me-1"></i><?= t('btn_delete') ?></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
