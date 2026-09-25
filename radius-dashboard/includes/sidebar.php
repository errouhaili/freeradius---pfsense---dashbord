<aside class="app-sidebar" id="appSidebar">
  <ul class="nav flex-column">
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='dashboard.php'?'active':'' ?>" href="dashboard.php">
        <i class="fa fa-gauge-high me-2"></i><?= t('nav_dashboard') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='users.php'?'active':'' ?>" href="users.php">
        <i class="fa fa-users me-2"></i><?= t('nav_users') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='sessions.php'?'active':'' ?>" href="sessions.php">
        <i class="fa fa-signal me-2"></i><?= t('nav_sessions') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='reports.php'?'active':'' ?>" href="reports.php">
        <i class="fa fa-chart-column me-2"></i><?= t('nav_reports') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='groups.php'?'active':'' ?>" href="groups.php">
        <i class="fa fa-layer-group me-2"></i><?= t('nav_groups') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='nas.php'?'active':'' ?>" href="nas.php">
        <i class="fa fa-server me-2"></i><?= t('nav_nas') ?></a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $current_page=='settings_2fa.php'?'active':'' ?>" href="settings_2fa.php">
        <i class="fa fa-lock me-2"></i><?= t('nav_2fa_settings') ?></a>
    </li>
  </ul>
</aside>
