<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) redirect(BASE_URL . '/collections.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, is_admin, is_guest FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool) $user['is_admin'];
            $_SESSION['is_guest'] = (bool) $user['is_guest'];
            redirect(BASE_URL . '/collections.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login – Collections</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
<style>
body { margin: 0; background: #f5f0e8; }
.login-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 420px;
}
.login-illustration {
  position: relative;
  overflow: hidden;
  background: #f5f0e8;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}
.login-illustration svg {
  width: 100%;
  max-width: 680px;
  height: auto;
}
.login-panel {
  background: #fdfaf4;
  border-left: 1px solid #d8cfc0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 40px;
}
.login-form-inner {
  width: 100%;
  max-width: 320px;
}
.login-form-inner h1 {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: 2rem;
  font-weight: 700;
  color: #1e1a14;
  margin: 0 0 8px;
}
.login-form-inner p {
  color: #7a7060;
  font-size: .92rem;
  margin: 0 0 28px;
  font-family: 'Source Sans 3', sans-serif;
}
@media (max-width: 700px) {
  .login-page { grid-template-columns: 1fr; }
  .login-illustration { display: none; }
  .login-panel { border-left: none; padding: 40px 24px; }
}
</style>
</head>
<body>
<div class="login-page">

  <!-- Illustration -->
  <div class="login-illustration">
    <svg width="100%" viewBox="0 0 680 380" xmlns="http://www.w3.org/2000/svg">
      <rect width="680" height="380" fill="#f5f0e8"/>
      <rect x="20" y="20" width="640" height="340" rx="4" fill="none" stroke="#d8cfc0" stroke-width="1.5"/>
      <rect x="26" y="26" width="628" height="328" rx="3" fill="none" stroke="#d8cfc0" stroke-width="0.5"/>
      <path d="M20 50 L20 20 L50 20" fill="none" stroke="#b5451b" stroke-width="2"/>
      <path d="M630 20 L660 20 L660 50" fill="none" stroke="#b5451b" stroke-width="2"/>
      <path d="M20 330 L20 360 L50 360" fill="none" stroke="#b5451b" stroke-width="2"/>
      <path d="M630 360 L660 360 L660 330" fill="none" stroke="#b5451b" stroke-width="2"/>
      <text x="340" y="72" text-anchor="middle" font-family="Georgia, serif" font-size="42" fill="#1e1a14" font-weight="bold" letter-spacing="8">COLLECTIONS</text>
      <rect x="120" y="82" width="440" height="1.5" fill="#b5451b"/>
      <text x="340" y="100" text-anchor="middle" font-family="Georgia, serif" font-size="10" fill="#7a7060" letter-spacing="5">YOUR PERSONAL ARCHIVE</text>
      <!-- Pocket watch -->
      <circle cx="130" cy="220" r="58" fill="#c8b99a" stroke="#a09080" stroke-width="2"/>
      <circle cx="130" cy="220" r="52" fill="#e8ddd0"/>
      <circle cx="130" cy="220" r="44" fill="#f5f0e8" stroke="#c8b99a" stroke-width="1"/>
      <circle cx="130" cy="220" r="40" fill="none" stroke="#d8cfc0" stroke-width="0.5"/>
      <line x1="130" y1="182" x2="130" y2="188" stroke="#1e1a14" stroke-width="2"/>
      <line x1="130" y1="252" x2="130" y2="258" stroke="#1e1a14" stroke-width="2"/>
      <line x1="92" y1="220" x2="98" y2="220" stroke="#1e1a14" stroke-width="2"/>
      <line x1="162" y1="220" x2="168" y2="220" stroke="#1e1a14" stroke-width="2"/>
      <line x1="157" y1="193" x2="153" y2="198" stroke="#1e1a14" stroke-width="1.5"/>
      <line x1="103" y1="247" x2="107" y2="242" stroke="#1e1a14" stroke-width="1.5"/>
      <line x1="103" y1="193" x2="107" y2="198" stroke="#1e1a14" stroke-width="1.5"/>
      <line x1="157" y1="247" x2="153" y2="242" stroke="#1e1a14" stroke-width="1.5"/>
      <line x1="130" y1="220" x2="130" y2="195" stroke="#1e1a14" stroke-width="2.5" stroke-linecap="round"/>
      <line x1="130" y1="220" x2="148" y2="228" stroke="#1e1a14" stroke-width="2" stroke-linecap="round"/>
      <line x1="130" y1="220" x2="118" y2="236" stroke="#b5451b" stroke-width="1.5" stroke-linecap="round"/>
      <circle cx="130" cy="220" r="3" fill="#1e1a14"/>
      <rect x="125" y="160" width="10" height="16" rx="3" fill="#c8b99a" stroke="#a09080" stroke-width="1"/>
      <circle cx="130" cy="158" r="6" fill="#c8b99a" stroke="#a09080" stroke-width="1"/>
      <path d="M130 158 Q115 140 100 130 Q85 120 80 110" fill="none" stroke="#c8a830" stroke-width="2.5" stroke-linecap="round"/>
      <circle cx="115" cy="143" r="3" fill="none" stroke="#c8a830" stroke-width="1.5"/>
      <circle cx="100" cy="130" r="3" fill="none" stroke="#c8a830" stroke-width="1.5"/>
      <!-- Polaroid photos -->
      <g transform="rotate(-8, 340, 220)">
        <rect x="295" y="155" width="90" height="105" rx="2" fill="white" stroke="#e0d8c8" stroke-width="1"/>
        <rect x="303" y="163" width="74" height="70" fill="#c8d8e8"/>
        <rect x="310" y="170" width="60" height="56" fill="#4a7fb5"/>
        <ellipse cx="325" cy="195" rx="12" ry="20" fill="#f0e840" opacity="0.6"/>
        <text x="340" y="248" text-anchor="middle" font-family="Georgia, serif" font-size="8" fill="#7a7060">summer 1984</text>
      </g>
      <rect x="320" y="148" width="90" height="105" rx="2" fill="white" stroke="#e0d8c8" stroke-width="1.5"/>
      <rect x="328" y="156" width="74" height="70" fill="#d8c8b8"/>
      <ellipse cx="360" cy="185" rx="18" ry="22" fill="#c8a830" opacity="0.5"/>
      <ellipse cx="380" cy="195" rx="12" ry="15" fill="#b5451b" opacity="0.4"/>
      <rect x="328" y="178" width="74" height="15" fill="#4a7fb5" opacity="0.3"/>
      <text x="365" y="240" text-anchor="middle" font-family="Georgia, serif" font-size="8" fill="#7a7060">vacation 1971</text>
      <!-- Trading card -->
      <rect x="450" y="140" width="80" height="115" rx="3" fill="#c8a830"/>
      <rect x="454" y="144" width="72" height="107" rx="2" fill="#f5f0e8"/>
      <rect x="458" y="148" width="64" height="72" fill="#2d6a4f"/>
      <circle cx="490" cy="168" r="10" fill="#1e1a14" opacity="0.7"/>
      <path d="M475 220 Q490 195 505 220" fill="#1e1a14" opacity="0.7"/>
      <rect x="481" y="178" width="18" height="25" rx="2" fill="#1e1a14" opacity="0.7"/>
      <rect x="458" y="222" width="64" height="3" fill="#c8a830"/>
      <text x="490" y="236" text-anchor="middle" font-family="Georgia, serif" font-size="8" fill="#1e1a14" font-weight="bold">J. ANDERSON</text>
      <text x="490" y="246" text-anchor="middle" font-family="Georgia, serif" font-size="7" fill="#7a7060">1st BASE • 1962</text>
      <text x="490" y="148" text-anchor="middle" font-family="Georgia, serif" font-size="7" fill="#f5f0e8">TOPPS</text>
      <text x="490" y="160" text-anchor="middle" font-size="8" fill="#c8a830">&#9733;&#9733;&#9733;&#9733;</text>
      <!-- Coins -->
      <circle cx="220" cy="310" r="12" fill="#c8a830" stroke="#a08020" stroke-width="1"/>
      <circle cx="220" cy="310" r="8" fill="none" stroke="#a08020" stroke-width="0.5"/>
      <circle cx="248" cy="318" r="10" fill="#c8a830" stroke="#a08020" stroke-width="1"/>
      <circle cx="195" cy="318" r="8" fill="#e0c040" stroke="#a08020" stroke-width="1"/>
      <!-- Stamp -->
      <rect x="555" y="285" width="65" height="55" rx="1" fill="#f5f0e8" stroke="#c8b99a" stroke-width="1"/>
      <rect x="561" y="291" width="53" height="35" fill="#b5451b"/>
      <path d="M565 320 L575 305 L590 315 L600 300 L610 320 Z" fill="#f5f0e8" opacity="0.8"/>
      <text x="588" y="333" text-anchor="middle" font-family="Georgia, serif" font-size="7" fill="#1e1a14">RARE</text>
      <text x="588" y="342" text-anchor="middle" font-family="Georgia, serif" font-size="8" fill="#b5451b" font-weight="bold">12.00</text>
      <circle cx="555" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="565" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="575" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="585" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="595" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="605" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="615" cy="285" r="2.5" fill="#f5f0e8"/><circle cx="620" cy="285" r="2.5" fill="#f5f0e8"/>
      <circle cx="555" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="565" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="575" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="585" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="595" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="605" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="615" cy="340" r="2.5" fill="#f5f0e8"/><circle cx="620" cy="340" r="2.5" fill="#f5f0e8"/>
    </svg>
  </div>

  <!-- Login form -->
  <div class="login-panel">
    <div class="login-form-inner">
      <h1>Collections</h1>
      <p>Sign in to manage your collections.</p>

      <?php if ($error): ?>
        <div class="flash flash--error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" class="form-control"
                 value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
                 autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
          Sign in
        </button>
      </form>
    </div>
  </div>

</div>
</body>
</html>
