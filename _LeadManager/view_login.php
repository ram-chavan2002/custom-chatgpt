<?php
session_start();
require_once 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "Username not found.";
        } elseif ($user['status'] !== 'active') {
            $error = "Your account is inactive.";
        } else {
            // ── PASSWORD CHECK: plain text only (no hash) ──
            $pass_ok = ($password === $user['password']);
            
            if (!$pass_ok) {
                $error = "Incorrect password.";
            } else {
                // ── SET ALL SESSION VARIABLES ──
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['user_state']= trim($user['state'] ?? '');
                $_SESSION['user_city'] = trim($user['city']  ?? '');
                
                header("Location: view_lead1.php");
                exit;
            }
        }
    } else {
        $error = "Please enter username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login — Lead Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet"/>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0d0d14; --card: #14141f; --border: rgba(255,255,255,0.07);
    --accent: #7c6af7; --accent2: #a78bfa; --text: #e8e8f0;
    --muted: #6b6b80; --error: #f87171; --input-bg: #1a1a28;
  }
  body {
    font-family: 'Sora', sans-serif; background: var(--bg);
    min-height: 100vh; display: flex; align-items: center;
    justify-content: center; overflow: hidden; position: relative;
  }
  body::before, body::after {
    content: ''; position: fixed; border-radius: 50%;
    filter: blur(80px); opacity: 0.15; pointer-events: none;
    animation: float 8s ease-in-out infinite;
  }
  body::before {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #7c6af7, transparent);
    top: -100px; left: -100px;
  }
  body::after {
    width: 400px; height: 400px;
    background: radial-gradient(circle, #a78bfa, transparent);
    bottom: -80px; right: -80px; animation-delay: -4s;
  }
  @keyframes float {
    0%, 100% { transform: translateY(0) scale(1); }
    50%       { transform: translateY(30px) scale(1.05); }
  }
  .card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 20px; padding: 48px 44px; width: 100%; max-width: 420px;
    position: relative; z-index: 1; box-shadow: 0 24px 64px rgba(0,0,0,0.5);
    animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .card::before {
    content: ''; position: absolute; top: 0; left: 24px; right: 24px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
    border-radius: 2px;
  }
  .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
  .logo-icon {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 10px; display: flex; align-items: center;
    justify-content: center; font-size: 18px;
  }
  .logo-text { font-size: 17px; font-weight: 700; color: var(--text); }
  .logo-text span { color: var(--accent2); }
  h1 { font-size: 24px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
  .subtitle { font-size: 13px; color: var(--muted); margin-bottom: 28px; }
  .field { margin-bottom: 16px; }
  .field label {
    display: block; font-size: 11px; font-weight: 600;
    letter-spacing: 0.6px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 7px;
  }
  .input-wrap { position: relative; display: flex; align-items: center; }
  .field input {
    width: 100%; background: var(--input-bg); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 42px 12px 14px;
    color: var(--text); font-family: inherit; font-size: 14px;
    transition: border-color .2s, box-shadow .2s; outline: none;
  }
  .field input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(124,106,247,.15);
  }
  .field input::placeholder { color: var(--muted); }
  .eye-btn {
    position: absolute; right: 12px; background: none; border: none;
    color: var(--muted); cursor: pointer; font-size: 15px;
    padding: 0; display: flex; align-items: center; transition: color .2s;
  }
  .eye-btn:hover { color: var(--text); }
  .error-msg {
    background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3);
    color: var(--error); border-radius: 10px; padding: 10px 14px;
    font-size: 13px; margin-bottom: 18px;
  }
  .btn {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border: none; border-radius: 12px; color: #fff;
    font-family: inherit; font-size: 15px; font-weight: 600;
    cursor: pointer; margin-top: 6px;
    transition: opacity .2s, transform .15s;
    box-shadow: 0 4px 20px rgba(124,106,247,.35);
  }
  .btn:hover { opacity: .9; transform: translateY(-1px); }
  .footer-note { text-align: center; margin-top: 22px; font-size: 12px; color: var(--muted); }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">📋</div>
    <div class="logo-text">Lead<span>Manager</span></div>
  </div>
  <h1>Welcome back</h1>
  <p class="subtitle">Sign in to view your assigned leads</p>

  <?php if ($error): ?>
  <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="field">
      <label>Username</label>
      <div class="input-wrap">
        <input type="text" name="username" placeholder="Enter your username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               required autofocus autocomplete="username"/>
      </div>
    </div>
    <div class="field">
      <label>Password</label>
      <div class="input-wrap">
        <input type="password" name="password" id="pwdField"
               placeholder="Enter your password"
               required autocomplete="current-password"/>
        <button type="button" class="eye-btn" onclick="togglePwd()" id="eyeBtn">👁</button>
      </div>
    </div>
    <button type="submit" class="btn">Sign In →</button>
  </form>

  <p class="footer-note">SmartDigi Solution · Lead Management System</p>
</div>

<script>
function togglePwd() {
  var f = document.getElementById('pwdField');
  var b = document.getElementById('eyeBtn');
  f.type = f.type === 'password' ? 'text' : 'password';
  b.textContent = f.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>