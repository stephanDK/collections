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

<!-- Lightbox -->
<style>
#lb-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  width: 100vw; height: 100vh;
  background: rgba(0,0,0,.92);
  z-index: 99999;
  align-items: center;
  justify-content: center;
}
#lb-overlay.open { display: flex; }
#lb-overlay img {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 6px;
  box-shadow: 0 8px 48px rgba(0,0,0,.8);
  display: block;
}
#lb-close {
  position: fixed;
  top: 12px; right: 18px;
  font-size: 2.5rem;
  color: #fff;
  cursor: pointer;
  line-height: 1;
  z-index: 100000;
  background: none;
  border: none;
  opacity: .8;
}
#lb-close:hover { opacity: 1; }
</style>
<div id="lb-overlay">
  <button id="lb-close" onclick="lbClose()">✕</button>
  <img id="lb-img" src="" alt="">
</div>
<script>
function lbOpen(src) {
  var img = document.getElementById('lb-img');
  img.setAttribute('src', src);
  document.getElementById('lb-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function lbClose() {
  document.getElementById('lb-overlay').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('lb-overlay').addEventListener('click', function(e) {
  if (e.target === this) lbClose();
});
document.addEventListener('click', function(e) {
  var img = e.target.closest('img.lightbox-trigger');
  if (img) { e.preventDefault(); lbOpen(img.getAttribute('src')); }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') lbClose();
});
</script>

</body>
</html>
<?php }
