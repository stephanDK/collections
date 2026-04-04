<?php
// layout.php  –  included by every page for consistent chrome
function page_header(string $title, string $active = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> – Collections</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
</head>
<body>
<header class="site-header">
  <div class="site-header__inner">
    <a href="<?= BASE_URL ?>/collections.php" class="site-logo">&#9632; Collections</a>
    <nav class="site-nav">
      <a href="<?= BASE_URL ?>/collections.php" class="<?= $active==='collections'?'active':'' ?>">Collections</a>
      <?php if (is_admin()): ?>
      <a href="<?= BASE_URL ?>/admin.php"       class="<?= $active==='admin'?'active':'' ?>">Admin</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/logout.php" class="nav-logout">Log out (<?= h($_SESSION['username'] ?? '') ?>)</a>
    </nav>
  </div>
</header>
<main class="site-main">
<?php
    $flash = get_flash();
    if ($flash): ?>
  <div class="flash flash--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif;
}

function page_footer(): void { ?>
</main>
<footer class="site-footer">
  <div class="site-footer__inner">Collections Manager</div>
</footer>
</body>
</html>
<?php }
