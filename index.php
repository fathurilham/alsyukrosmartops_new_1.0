<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'config/auth.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid_form()) {
        $error = 'Sesi form kedaluwarsa, silakan coba lagi.';
    } else {
        if (checkIpRateLimit()) {
            $error = 'Terlalu banyak percobaan. Silakan coba lagi nanti.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (empty($username) || empty($password)) { $error = 'Username dan password wajib diisi.'; }
            else {
                global $conn;
                $found = null;
                $stmt = $conn->prepare("SELECT u.*, r.kode AS role_kode, r.nama_role FROM users u
                                         JOIN roles r ON r.id = u.role_id
                                         WHERE u.username=? OR u.email=?");
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $candidate = $stmt->get_result()->fetch_assoc();

                $hash = $candidate ? $candidate['password'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                $isValid = password_verify($password, $hash);

                if ($candidate && $isValid) {
                    if ($candidate['status'] !== 'aktif') {
                        $error = 'Akun tidak aktif. Hubungi Administrator.';
                    } elseif (isAccountLocked($candidate)) {
                        $error = "Username atau password salah.";
                    } else {
                        $found = $candidate;
                    }
                }

                if ($found) {
                    session_regenerate_id(true);
                    $newSid = session_id();
                    if (!claimUserSession($found['id'], $newSid, getClientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '')) {
                        logAktivitas($found['id'], 'auth', 'Login ditolak: akun sedang aktif di perangkat lain');
                        $error = 'Akun ini sedang digunakan di perangkat lain. Silakan logout dari perangkat tersebut atau tunggu hingga sesi berakhir.';
                    } else {
                        resetFailedLogin($found['id']);
                        $_SESSION['user_id'] = $found['id'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['db_last_touch'] = time();
                        logAktivitas($found['id'], 'auth', 'Login ke sistem');
                        header('Location: dashboard.php'); exit();
                    }
                } elseif (!$error) {
                    if ($candidate) {
                        registerFailedLogin($candidate['id']);
                    }
                    registerIpFailedLogin();
                    $error = 'Username atau password salah.';
                }
            }
        }
    }
}

$demoRoles = [
    ['u' => 'admin', 'label' => 'Administrator', 'icon' => 'crown'],
    ['u' => 'manajerga', 'label' => 'Manajer GA', 'icon' => 'briefcase'],
    ['u' => 'spvinventaris', 'label' => 'SPV Inventaris', 'icon' => 'package'],
    ['u' => 'staffinventaris', 'label' => 'Staff Inventaris', 'icon' => 'boxes'],
    ['u' => 'spvsapras', 'label' => 'SPV Sapras', 'icon' => 'hard-hat'],
    ['u' => 'teamleader', 'label' => 'Team Leader', 'icon' => 'users'],
    ['u' => 'engineering', 'label' => 'Engineering', 'icon' => 'wrench'],
    ['u' => 'cso1', 'label' => 'CSO', 'icon' => 'shield'],
    ['u' => 'gardener1', 'label' => 'Gardener', 'icon' => 'leaf'],
    ['u' => 'manajerhrd', 'label' => 'Manajer HRD', 'icon' => 'id-card'],
    ['u' => 'staffhrd', 'label' => 'Staff HRD', 'icon' => 'id-card'],
    ['u' => 'manajermarkom', 'label' => 'Manajer MARKOM', 'icon' => 'megaphone'],
    ['u' => 'spvcrm', 'label' => 'SPV CRM', 'icon' => 'calendar-check'],
    ['u' => 'staffcrm', 'label' => 'Staff CRM', 'icon' => 'calendar'],
    ['u' => 'manajerqms', 'label' => 'Manajer QMS', 'icon' => 'folder-cog'],
    ['u' => 'staffqms', 'label' => 'Staff QMS', 'icon' => 'folder'],
    ['u' => 'tu_sd', 'label' => 'TU (SD)', 'icon' => 'file-text'],
    ['u' => 'kepsek_sd', 'label' => 'Kepala Sekolah (SD)', 'icon' => 'graduation-cap'],
    ['u' => 'manajerfa', 'label' => 'Manajer FA', 'icon' => 'briefcase'],
    ['u' => 'stafffa', 'label' => 'Staff FA', 'icon' => 'wallet'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Masuk — AL-SYUKROSMART OPS</title>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="512x512" href="logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Bootstrap 5 (framework CSS utama aplikasi) -->
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<script src="assets/vendor/lucide.min.js"></script>
<script src="assets/vendor/sweetalert2.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary: #0a4d33;
  --primary-hover: #073a26;
  --bg-color: #f0f7f4;
  --text-main: #2d3748;
  --text-muted: #718096;
  --border-color: #e2e8f0;
  --font-display: 'Fraunces', serif;
  --font-body: 'Inter', sans-serif;
}
html,body{height:100%;font-family:var(--font-body);background:var(--bg-color);color:var(--text-main)}
.page-wrapper {
  display: flex;
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  overflow: hidden;
}
/* Abstract leaf decorations for background corners */
.bg-decor {
  position: absolute;
  width: 400px;
  height: 400px;
  background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,50 C0,0 50,0 50,0 C100,0 100,50 100,50 C100,100 50,100 50,100 C0,100 0,50 0,50 Z" fill="%23dcece5" opacity="0.8"/><path d="M20,70 C20,20 70,20 70,20 C120,20 120,70 120,70 C120,120 70,120 70,120 C20,120 20,70 20,70 Z" fill="%23cbe0d5" opacity="0.8"/></svg>');
  background-size: contain;
  background-repeat: no-repeat;
  z-index: 1;
}
.bg-decor-1 { top: -100px; left: -100px; transform: rotate(-30deg); }
.bg-decor-2 { bottom: -100px; right: -100px; transform: rotate(150deg); }

.login-container {
  display: flex;
  width: 100%;
  max-width: 1000px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(10, 77, 51, 0.08), 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
  position: relative;
  z-index: 10;
}
.panel-left {
  width: 45%;
  background-color: var(--primary);
  background-image: linear-gradient(rgba(10, 77, 51, 0.85), rgba(10, 77, 51, 0.95)), url('bg-building.png');
  background-size: cover;
  background-position: 25% center;
  padding: 50px;
  display: flex;
  flex-direction: column;
  color: #ffffff;
}
.panel-left-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.logo-area {
  display: flex;
  align-items: center;
  gap: 16px;
}
.logo-area img {
  height: 50px;
  width: auto;
}
.logo-text {
  font-family: var(--font-body);
}
.logo-text h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
  line-height: 1.2;
}
.logo-text span {
  font-size: 14px;
  font-weight: 400;
  color: #e2e8f0;
}
.logo-divider {
  width: 30px;
  height: 1px;
  background-color: rgba(255, 255, 255, 0.4);
  margin: 30px 0;
}
.brand-text h3 {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  margin-bottom: 8px;
  color: #e2e8f0;
}
.brand-text h1 {
  font-family: var(--font-display);
  font-size: 34px;
  font-weight: 700;
  margin: 0;
}
.panel-left-bottom {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  border-top: 1px solid rgba(255,255,255,0.2);
  padding-top: 24px;
}
.feature-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 500;
  color: #e2e8f0;
}
.feature-item i { width: 18px; height: 18px; }

/* Right Panel */
.panel-right {
  width: 55%;
  padding: 50px 60px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.form-header { margin-bottom: 30px; }
.form-header h2 {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 8px;
}
.form-header p {
  font-size: 13.5px;
  color: var(--text-muted);
}
.input-group {
  position: relative;
  margin-bottom: 16px;
}
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--primary);
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.input-control {
  width: 100%;
  padding: 12px 16px 12px 42px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 13.5px;
  font-family: var(--font-body);
  transition: all 0.2s;
  color: var(--text-main);
  background: #ffffff;
}
.input-control:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(10, 77, 51, 0.1);
}
.pwd-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
}
.pwd-options {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 24px;
}
.pwd-options input[type="checkbox"] {
  accent-color: var(--primary);
  width: 14px;
  height: 14px;
  cursor: pointer;
}
.pwd-options label { font-size: 13px; color: var(--text-muted); cursor: pointer; user-select: none; }

.btn-login {
  width: 100%;
  padding: 12px;
  background-color: var(--primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-login:hover { background-color: var(--primary-hover); }

.divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 28px 0;
  color: #a0aec0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
}
.divider::before, .divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid var(--border-color);
}
.divider::before { margin-right: 12px; }
.divider::after { margin-left: 12px; }

.demo-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  max-height: 180px;
  overflow-y: auto;
  padding-right: 6px;
}
.demo-grid::-webkit-scrollbar { width: 5px; }
.demo-grid::-webkit-scrollbar-track { background: transparent; }
.demo-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.demo-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: #f8faf9;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s;
}
.demo-card:hover {
  border-color: var(--primary);
  background: #f0f7f4;
}
.demo-card i { color: var(--primary); width: 18px; height: 18px; }
.demo-card-info { display: flex; flex-direction: column; }
.demo-card-info strong { font-size: 12.5px; font-weight: 600; color: var(--text-main); }
.demo-card-info span { font-size: 11px; color: var(--text-muted); }

.help-text {
  text-align: center;
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 30px;
}
.help-text b { color: var(--text-main); font-weight: 600; }

@media(max-width: 900px) {
  .login-container { flex-direction: column; max-width: 500px; margin: 20px; }
  .panel-left { width: 100%; padding: 40px; min-height: 250px; }
  .panel-right { width: 100%; padding: 40px; }
  .panel-left-bottom { display: none; }
}
@media(max-width: 480px) {
  .login-container { margin: 16px; }
  .panel-left { padding: 35px 24px; min-height: auto; }
  .panel-right { padding: 35px 24px; }
  .logo-area img { height: 38px; }
  .logo-text h3 { font-size: 14px; }
  .logo-text span { font-size: 12px; }
  .logo-divider { margin: 20px 0; }
  .brand-text h3 { font-size: 14px; margin-bottom: 4px; }
  .brand-text h1 { font-size: 25px; }
  .form-header { margin-bottom: 24px; }
  .form-header h2 { font-size: 22px; }
  .demo-grid { grid-template-columns: 1fr; }
}
@media(min-width: 901px) {
  html, body, .page-wrapper { overflow: hidden; height: 100vh; }
}
</style>
</head>
<body>
<div class="page-wrapper">
  <div class="bg-decor bg-decor-1"></div>
  <div class="bg-decor bg-decor-2"></div>
  
  <div class="login-container">
    <div class="panel-left">
      <div class="panel-left-center">
        <div class="logo-area">
          <img src="logo-login.png" alt="Logo">
          <div class="logo-text">
            <h3>Perguruan Islam</h3>
            <span>Al Syukro Universal</span>
          </div>
        </div>
        <div class="logo-divider"></div>
        <div class="brand-text">
          <h3>Sistem Operasional Terpadu</h3>
          <h1>Al-SyukroSmart Ops</h1>
        </div>
      </div>
      <div class="panel-left-bottom">
        <div class="feature-item"><i data-lucide="shield-check"></i> Profesional</div>
        <div class="feature-item"><i data-lucide="link"></i> Terintegrasi</div>
        <div class="feature-item"><i data-lucide="home"></i> Berkelanjutan</div>
      </div>
    </div>
    
    <div class="panel-right">
      <div class="form-header">
        <h2>Masuk ke akun Anda</h2>
      </div>
      
      <form method="POST">
        <?= csrf_field() ?>
        <div class="input-group">
          <div class="input-icon"><i data-lucide="mail"></i></div>
          <input class="input-control" type="text" name="username" id="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Username atau Email" required autofocus>
        </div>
        
        <div class="input-group">
          <div class="input-icon"><i data-lucide="lock"></i></div>
          <input class="input-control" type="password" name="password" id="pw" placeholder="Password" required>
          <button type="button" class="pwd-toggle" id="eyeBtn"><i data-lucide="eye"></i></button>
        </div>
        
        
        <button type="submit" class="btn-login">Masuk</button>
      </form>
      
      <div class="divider">MODE DEMO</div>
      <div class="demo-grid">
        <?php foreach ($demoRoles as $d): ?>
        <button type="button" class="demo-card dpill" data-u="<?= $d['u'] ?>" data-p="password123">
          <i data-lucide="<?= $d['icon'] ?>"></i>
          <div class="demo-card-info">
            <strong><?= htmlspecialchars($d['label']) ?></strong>
            <span><?= $d['u'] ?></span>
          </div>
        </button>
        <?php endforeach; ?>
      </div>
      

    </div>
  </div>
</div>
<script>
lucide.createIcons();
document.querySelectorAll('.dpill').forEach(b=>{
  b.addEventListener('click',()=>{document.getElementById('username').value=b.dataset.u;document.getElementById('pw').value=b.dataset.p});
});
const pw = document.getElementById('pw');
const eye = document.getElementById('eyeBtn');

function togglePassword(show) {
  pw.type = show ? 'text' : 'password';
  eye.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
  lucide.createIcons({ root: eye });
}

eye.addEventListener('click', () => togglePassword(pw.type === 'password'));

<?php if ($error): ?>
Swal.fire({ title: 'Terjadi Kesalahan!', text: <?= json_encode($error) ?>, icon: 'error', confirmButtonText: 'OK', confirmButtonColor: '#145c3f' });
<?php endif; ?>
<?php if (isset($_GET['registered'])): ?>
Swal.fire({ title: 'Berhasil!', text: 'Registrasi berhasil! Silakan login.', icon: 'success', confirmButtonText: 'OK', confirmButtonColor: '#145c3f' });
<?php endif; ?>
</script>
</body>
</html>
