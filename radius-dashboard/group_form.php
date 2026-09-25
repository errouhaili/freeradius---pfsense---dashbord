<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$groupname = trim($_GET['groupname'] ?? '');
$check = [];
$reply = [];
if ($groupname !== '') {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT attribute, op, value FROM radgroupcheck WHERE groupname = ?");
    $stmt->execute([$groupname]);
    $check = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT attribute, op, value FROM radgroupreply WHERE groupname = ?");
    $stmt->execute([$groupname]);
    $reply = $stmt->fetchAll();
}
if (!$check) $check = [['attribute'=>'', 'op'=>':=', 'value'=>'']];
if (!$reply) $reply = [['attribute'=>'', 'op'=>':=', 'value'=>'']];

include __DIR__ . '/includes/header.php';
?>
<h4 class="fw-bold mb-4"><i class="fa fa-layer-group me-2 text-primary"></i>
  <?= $groupname ? htmlspecialchars(t('manage_policies_title', ['groupname' => $groupname])) : t('create_group_title') ?>
</h4>

<div class="card">
  <div class="card-body">
    <form method="post" action="group_save.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="original_groupname" value="<?= htmlspecialchars($groupname) ?>">

      <div class="mb-3">
        <label class="form-label"><?= t('label_group_name') ?></label>
        <input type="text" name="groupname" class="form-control" value="<?= htmlspecialchars($groupname) ?>"
               <?= $groupname ? 'readonly' : 'required' ?>>
      </div>

      <hr>
      <h6 class="fw-bold mb-2"><i class="fa fa-key me-1"></i><?= t('check_conditions_title') ?></h6>
      <p class="text-muted small"><?= t('check_conditions_hint') ?></p>
      <div id="checkRows">
        <?php foreach ($check as $c): ?>
        <div class="row g-2 mb-2 attr-row">
          <div class="col-4"><input type="text" name="check_attr[]" class="form-control" placeholder="Attribute" value="<?= htmlspecialchars($c['attribute']) ?>"></div>
          <div class="col-2">
            <select name="check_op[]" class="form-select">
              <?php foreach ([':=', '==', '+=', '!='] as $op): ?>
                <option <?= $op===$c['op']?'selected':'' ?>><?= $op ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4"><input type="text" name="check_value[]" class="form-control" placeholder="Value" value="<?= htmlspecialchars($c['value']) ?>"></div>
          <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fa fa-times"></i></button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addRow('checkRows')"><i class="fa fa-plus me-1"></i><?= t('btn_add_condition') ?></button>

      <hr>
      <h6 class="fw-bold mb-2"><i class="fa fa-reply me-1"></i><?= t('reply_permissions_title') ?></h6>
      <p class="text-muted small"><?= t('reply_permissions_hint') ?></p>
      <div id="replyRows">
        <?php foreach ($reply as $r): ?>
        <div class="row g-2 mb-2 attr-row">
          <div class="col-4"><input type="text" name="reply_attr[]" class="form-control" placeholder="Attribute" value="<?= htmlspecialchars($r['attribute']) ?>"></div>
          <div class="col-2">
            <select name="reply_op[]" class="form-select">
              <?php foreach ([':=', '=', '+='] as $op): ?>
                <option <?= $op===$r['op']?'selected':'' ?>><?= $op ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4"><input type="text" name="reply_value[]" class="form-control" placeholder="Value" value="<?= htmlspecialchars($r['value']) ?>"></div>
          <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fa fa-times"></i></button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addRow('replyRows')"><i class="fa fa-plus me-1"></i><?= t('btn_add_permission') ?></button>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> <?= t('btn_save_group') ?></button>
        <a href="groups.php" class="btn btn-light"><?= t('btn_cancel') ?></a>
      </div>
    </form>
  </div>
</div>

<template id="rowTemplate">
  <div class="row g-2 mb-2 attr-row">
    <div class="col-4"><input type="text" class="form-control" placeholder="Attribute"></div>
    <div class="col-2"><select class="form-select"><option>:=</option><option>==</option><option>+=</option></select></div>
    <div class="col-4"><input type="text" class="form-control" placeholder="Value"></div>
    <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fa fa-times"></i></button></div>
  </div>
</template>

<script>
document.addEventListener('click', function(e){
  if (e.target.closest('.remove-row')) {
    e.target.closest('.attr-row').remove();
  }
});
function addRow(containerId) {
  const container = document.getElementById(containerId);
  const tpl = document.getElementById('rowTemplate').content.cloneNode(true);
  const prefix = containerId === 'checkRows' ? 'check' : 'reply';
  const row = tpl.querySelector('.attr-row');
  const inputs = row.querySelectorAll('input, select');
  inputs[0].name = prefix + '_attr[]';
  inputs[1].name = prefix + '_op[]';
  inputs[2].name = prefix + '_value[]';
  container.appendChild(row);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
