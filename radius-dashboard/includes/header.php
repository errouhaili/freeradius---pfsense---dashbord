<?php
$current_page = basename($_SERVER['PHP_SELF']);
$dir = is_rtl() ? 'rtl' : 'ltr';
$bootstrap_css = is_rtl()
    ? 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.rtl.min.css'
    : 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css';

$lang_names = ['ar' => 'العربية', 'fr' => 'Français', 'en' => 'English'];
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(t('app_name')) ?></title>
<link href="<?= $bootstrap_css ?>" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if (!empty($_SESSION['admin_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark topbar">
  <div class="container-fluid">
    <button class="btn btn-link text-white d-lg-none" id="sidebarToggle"><i class="fa fa-bars"></i></button>
    <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fa fa-shield-halved me-2"></i><?= htmlspecialchars(t('app_name')) ?></a>
    <div class="ms-auto d-flex align-items-center text-white">
      <div class="dropdown me-3">
        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="fa fa-globe me-1"></i><?= strtoupper(current_lang()) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php foreach ($lang_names as $code => $label): ?>
            <li>
              <a class="dropdown-item <?= current_lang() === $code ? 'active' : '' ?>" href="<?= lang_switch_url($code) ?>">
                <?= htmlspecialchars($label) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <span class="me-3"><i class="fa fa-user-circle me-1"></i><?= htmlspecialchars(current_admin()) ?></span>
      <a href="logout.php" class="btn btn-sm btn-outline-light"><i class="fa fa-right-from-bracket me-1"></i><?= t('logout') ?></a>
    </div>
  </div>
</nav>
<div class="app-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="app-content p-4">
<?php endif; ?>
