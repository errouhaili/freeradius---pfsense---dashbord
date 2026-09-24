<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$active = $pdo->query("SELECT radacctid, username, nasipaddress, framedipaddress, callingstationid,
                               acctstarttime, acctsessiontime, acctinputoctets, acctoutputoctets
                        FROM radacct WHERE acctstoptime IS NULL
                        ORDER BY acctstarttime DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa fa-signal me-2 text-primary"></i><?= t('sessions_heading') ?></h4>
  <div class="d-flex align-items-center gap-2">
    <span class="badge badge-active fs-6"><?= htmlspecialchars(t('badge_active_count', ['count' => count($active)])) ?></span>
    <a href="export_sessions_csv.php?scope=active" class="btn btn-outline-success btn-sm">
      <i class="fa fa-file-csv me-1"></i><?= t('btn_export_csv') ?></a>
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-print me-1"></i><?= t('btn_print_pdf') ?></button>
  </div>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th><?= t('th_user') ?></th><th><?= t('th_nas') ?></th><th><?= t('th_device_ip') ?></th><th><?= t('th_mac') ?></th>
          <th><?= t('th_session_start') ?></th><th><?= t('th_duration') ?></th><th><?= t('th_download') ?></th><th><?= t('th_upload') ?></th>
          <th><?= t('th_actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$active): ?>
          <tr><td colspan="9" class="text-center text-muted py-4"><?= t('no_active_sessions') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($active as $s): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($s['username']) ?></td>
          <td><?= htmlspecialchars($s['nasipaddress']) ?></td>
          <td><?= htmlspecialchars($s['framedipaddress'] ?: t('not_set')) ?></td>
          <td><?= htmlspecialchars($s['callingstationid'] ?: t('not_set')) ?></td>
          <td><?= htmlspecialchars($s['acctstarttime']) ?></td>
          <td><?= format_duration(time() - strtotime($s['acctstarttime'])) ?></td>
          <td><?= format_bytes($s['acctinputoctets']) ?></td>
          <td><?= format_bytes($s['acctoutputoctets']) ?></td>
          <td>
            <form method="post" action="session_disconnect.php" class="d-inline"
                  onsubmit="return confirm('<?= htmlspecialchars(t('confirm_disconnect', ['username' => $s['username']])) ?>');">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="radacctid" value="<?= (int)$s['radacctid'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fa fa-plug-circle-xmark me-1"></i><?= t('btn_disconnect') ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted small mt-2"><i class="fa fa-info-circle me-1"></i><?= t('info_radacct_note') ?></p>
<p class="text-muted small"><i class="fa fa-circle-info me-1"></i><?= t('info_coa_note') ?></p>

<?php include __DIR__ . '/includes/footer.php'; ?>
