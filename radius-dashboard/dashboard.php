<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();

$totalUsers = $pdo->query("SELECT COUNT(DISTINCT username) c FROM radcheck")->fetch()['c'] ?? 0;
$activeSessions = $pdo->query("SELECT COUNT(*) c FROM radacct WHERE acctstoptime IS NULL")->fetch()['c'] ?? 0;
$totalNas = $pdo->query("SELECT COUNT(*) c FROM nas")->fetch()['c'] ?? 0;

$todayUsage = $pdo->query("SELECT COALESCE(SUM(acctinputoctets),0)+COALESCE(SUM(acctoutputoctets),0) t
                            FROM radacct WHERE DATE(acctstarttime) = CURDATE()")->fetch()['t'] ?? 0;

$recent = $pdo->query("SELECT username, nasipaddress, framedipaddress, acctstarttime, acctstoptime
                        FROM radacct ORDER BY radacctid DESC LIMIT 8")->fetchAll();

$sessionsByNas = get_active_sessions_by_nas();

include __DIR__ . '/includes/header.php';
?>
<h4 class="fw-bold mb-4"><i class="fa fa-gauge-high me-2 text-primary"></i><?= t('dashboard_heading') ?></h4>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card bg-users">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="small opacity-75"><?= t('stat_total_users') ?></div><div class="fs-3 fw-bold"><?= (int)$totalUsers ?></div></div>
        <i class="fa fa-users icon"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card bg-active">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="small opacity-75"><?= t('stat_active_sessions') ?></div><div class="fs-3 fw-bold"><?= (int)$activeSessions ?></div></div>
        <i class="fa fa-signal icon"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card bg-nas">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="small opacity-75"><?= t('stat_total_nas') ?></div><div class="fs-3 fw-bold"><?= (int)$totalNas ?></div></div>
        <i class="fa fa-server icon"></i>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card bg-data">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="small opacity-75"><?= t('stat_today_usage') ?></div><div class="fs-4 fw-bold"><?= format_bytes($todayUsage) ?></div></div>
        <i class="fa fa-chart-line icon"></i>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fa fa-clock-rotate-left me-2"></i><?= t('recent_activity') ?></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr><th><?= t('th_user') ?></th><th><?= t('th_nas') ?></th><th><?= t('th_device_ip') ?></th><th><?= t('th_session_start') ?></th><th><?= t('th_status') ?></th></tr>
          </thead>
          <tbody>
            <?php if (!$recent): ?>
              <tr><td colspan="5" class="text-center text-muted py-4"><?= t('no_data_yet') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['username']) ?></td>
              <td><?= htmlspecialchars($r['nasipaddress']) ?></td>
              <td><?= htmlspecialchars($r['framedipaddress'] ?: t('not_set')) ?></td>
              <td><?= htmlspecialchars($r['acctstarttime']) ?></td>
              <td>
                <?php if ($r['acctstoptime'] === null): ?>
                  <span class="badge badge-active"><?= t('status_connected_now') ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?= t('status_ended') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fa fa-chart-pie me-2"></i><?= t('sessions_by_nas_title') ?></div>
      <div class="card-body">
        <?php if (!$sessionsByNas): ?>
          <p class="text-muted text-center py-4 mb-0"><?= t('no_active_sessions') ?></p>
        <?php else: ?>
          <canvas id="nasChart" height="200"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($sessionsByNas): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('nasChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($sessionsByNas, 'nasipaddress')) ?>,
    datasets: [{
      data: <?= json_encode(array_map('intval', array_column($sessionsByNas, 'c'))) ?>,
      backgroundColor: ['#1b6ec2', '#20c997', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14']
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
