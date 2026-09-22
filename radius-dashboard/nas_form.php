<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$nas = ['id'=>'', 'nasname'=>'', 'shortname'=>'', 'type'=>'other', 'ports'=>'', 'secret'=>'', 'description'=>''];
if (!empty($_GET['id'])) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM nas WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $row = $stmt->fetch();
    if ($row) $nas = $row;
}

include __DIR__ . '/includes/header.php';
?>
<h4 class="fw-bold mb-4"><i class="fa fa-server me-2 text-primary"></i><?= $nas['id'] ? t('edit_nas_title') : t('add_nas_title') ?></h4>

<div class="card">
  <div class="card-body">
    <form method="post" action="nas_save.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" value="<?= htmlspecialchars($nas['id']) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label"><?= t('label_pfsense_ip') ?></label>
          <input type="text" name="nasname" class="form-control" placeholder="192.168.1.1" value="<?= htmlspecialchars($nas['nasname']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_shortname') ?></label>
          <input type="text" name="shortname" class="form-control" placeholder="pfsense-main" value="<?= htmlspecialchars($nas['shortname']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_type') ?></label>
          <select name="type" class="form-select">
            <?php foreach (['other','cisco','livingston','pfsense'] as $t): ?>
              <option value="<?= $t ?>" <?= $nas['type']===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_port') ?></label>
          <input type="text" name="ports" class="form-control" value="<?= htmlspecialchars($nas['ports']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_secret') ?></label>
          <input type="text" name="secret" class="form-control" value="<?= htmlspecialchars($nas['secret']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('label_description') ?></label>
          <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($nas['description']) ?>">
        </div>
      </div>
      <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> <?= t('btn_save') ?></button>
        <a href="nas.php" class="btn btn-light"><?= t('btn_cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<p class="text-muted small mt-2"><i class="fa fa-info-circle me-1"></i><?= t('nas_secret_hint') ?></p>

<?php include __DIR__ . '/includes/footer.php'; ?>
