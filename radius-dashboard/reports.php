<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$to = $_GET['to'] ?? date('Y-m-d');

$pdo = get_db();

$stmt = $pdo->prepare("SELECT DATE(acctstarttime) d,
                               SUM(acctinputoctets+acctoutputoctets) total
                        FROM radacct
                        WHERE DATE(acctstarttime) BETWEEN ? AND ?
                        GROUP BY DATE(acctstarttime) ORDER BY d");
$stmt->execute([$from, $to]);
$daily = $stmt->fetchAll();

$labels = array_map(fn($r) => $r['d'], $daily);
$values = array_map(fn($r) => round(($r['total'] ?? 0) / (1024*1024), 2), $daily);

$sessionsPerDay = get_sessions_count_per_day($from, $to);
$sessionLabels = array_map(fn($r) => $r['d'], $sessionsPerDay);
$sessionValues = array_map(fn($r) => (int)$r['c'], $sessionsPerDay);

$stmt = $pdo->prepare("SELECT username, SUM(acctinputoctets+acctoutputoctets) total, COUNT(*) sessions
                        FROM radacct
                        WHERE DATE(acctstarttime) BETWEEN ? AND ?
                        GROUP BY username ORDER BY total DESC LIMIT 10");
$stmt->execute([$from, $to]);
$topUsers = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<h4 class="fw-bold mb-4"><i class="fa fa-chart-column me-2 text-primary"></i><?= t('reports_heading') ?></h4>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small"><?= t('label_from_date') ?></label>
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small"><?= t('label_to_date') ?></label>
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="fa fa-filter me-1"></i><?= t('btn_filter') ?></button>
      </div>
      <div class="col-md-4 text-md-end">
        <a href="export_report_csv.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-outline-success">
          <i class="fa fa-file-csv me-1"></i><?= t('btn_export_csv') ?></a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
          <i class="fa fa-print me-1"></i><?= t('btn_print_pdf') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><?= t('chart_title') ?></div>
      <div class="card-body">
        <canvas id="usageChart" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><?= t('chart_sessions_title') ?></div>
      <div class="card-body">
        <canvas id="sessionsChart" height="90"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
    <span><?= t('top_users_title') ?></span>
    <a href="export_report_csv.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&type=top_users" class="btn btn-sm btn-outline-success">
      <i class="fa fa-file-csv me-1"></i><?= t('btn_export_csv') ?></a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th><?= t('th_rank') ?></th><th><?= t('th_user') ?></th><th><?= t('th_sessions_count') ?></th><th><?= t('th_total_usage') ?></th></tr>
      </thead>
      <tbody>
        <?php if (!$topUsers): ?>
          <tr><td colspan="4" class="text-center text-muted py-4"><?= t('no_data_period') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($topUsers as $i => $u): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
          <td><?= (int)$u['sessions'] ?></td>
          <td><?= format_bytes($u['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('usageChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
      label: 'MB',
      data: <?= json_encode($values) ?>,
      backgroundColor: '#1b6ec2'
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('sessionsChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($sessionLabels) ?>,
    datasets: [{
      label: '<?= t('chart_sessions_title') ?>',
      data: <?= json_encode($sessionValues) ?>,
      borderColor: '#20c997',
      backgroundColor: 'rgba(32,201,151,0.15)',
      fill: true,
      tension: 0.3
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
