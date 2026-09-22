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
  <span class="badge badge-active fs-6"><?= htmlspecialchars(t('badge_active_count', ['count' => count($active)])) ?></span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th><?= t('th_user') ?></th><th><?= t('th_nas') ?></th><th><?= t('th_device_ip') ?></th><th><?= t('th_mac') ?></th>
          <th><?= t('th_session_start') ?></th><th><?= t('th_duration') ?></th><th><?= t('th_download') ?></th><th><?= t('th_upload') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$active): ?>
          <tr><td colspan="8" class="text-center text-muted py-4"><?= t('no_active_sessions') ?></td></tr>
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
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted small mt-2"><i class="fa fa-info-circle me-1"></i><?= t('info_radacct_note') ?></p>

<?php include __DIR__ . '/includes/footer.php'; ?>
