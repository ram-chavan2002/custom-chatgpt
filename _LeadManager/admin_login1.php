<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin  = $result->fetch_assoc();
        $stmt->close();

        if ($admin) {
            $passwordMatch = password_verify($password, $admin['password']) || $password === $admin['password'];

            if ($passwordMatch) {
                $upd = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $admin['id']);
                $upd->execute();
                $upd->close();

                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name']     = $admin['full_name'] ?? $admin['username'];

                header('Location: lead.php');
                exit;
            } else {
                $error = 'Incorrect username or password.';
            }
        } else {
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Lead Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f2f8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Soft geometric background */
        body::before {
            content: '';
            position: fixed;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, #dde4ff 0%, transparent 70%);
            top: -200px; right: -150px;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, #ffe4f0 0%, transparent 70%);
            bottom: -100px; left: -100px;
            pointer-events: none;
        }

        .page {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 580px;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(60, 60, 120, 0.14), 0 2px 8px rgba(60,60,120,0.06);
            position: relative;
            z-index: 1;
        }

        /* LEFT PANEL */
        .left {
            background: linear-gradient(145deg, #3730a3 0%, #6366f1 55%, #818cf8 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .left::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            border: 60px solid rgba(255,255,255,0.06);
            bottom: -80px; right: -80px;
        }
        .left::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            border: 40px solid rgba(255,255,255,0.06);
            top: 40px; right: 60px;
        }

        .left-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .left-logo .logo-icon {
            width: 42px; height: 42px;
            background: rgba(255,255,255,0.18);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .left-logo .logo-icon svg { width: 22px; height: 22px; stroke: white; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .left-logo span { font-size: 18px; font-weight: 700; color: white; letter-spacing: -0.3px; }

        .left-body { z-index: 1; }
        .left-body h2 {
            font-size: 34px;
            font-weight: 700;
            color: white;
            line-height: 1.25;
            letter-spacing: -0.5px;
            margin-bottom: 16px;
        }
        .left-body p {
            font-size: 15px;
            color: rgba(255,255,255,0.65);
            line-height: 1.7;
            max-width: 280px;
        }

        .left-stats {
            display: flex;
            gap: 24px;
            z-index: 1;
        }
        .stat-pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 14px;
            padding: 14px 20px;
            backdrop-filter: blur(4px);
        }
        .stat-pill .num { font-size: 22px; font-weight: 700; color: white; }
        .stat-pill .lbl { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* RIGHT PANEL */
        .right {
            background: white;
            padding: 60px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .right-head { margin-bottom: 36px; }
        .right-head h1 { font-size: 26px; font-weight: 700; color: #1e1b4b; letter-spacing: -0.4px; margin-bottom: 6px; }
        .right-head p  { font-size: 14px; color: #9094a8; }

        /* Error */
        .alert-error {
            background: #fff1f2;
            border: 1px solid #fca5a5;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13.5px;
            color: #b91c1c;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            animation: slideIn 0.25s ease;
        }
        @keyframes slideIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
        .alert-error svg { flex-shrink: 0; }

        /* Fields */
        .field { margin-bottom: 20px; }
        .field label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #6366f1;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-wrap svg.fi {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            width: 17px; height: 17px; stroke: #c7c9da; fill: none;
            stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
            transition: stroke 0.2s; pointer-events: none;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            border: 1.5px solid #e5e7f0;
            border-radius: 12px;
            padding: 13px 16px 13px 44px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14.5px;
            color: #1e1b4b;
            background: #f8f9ff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        input::placeholder { color: #b8bcd0; }
        input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.10);
        }
        input:focus ~ svg.fi { stroke: #6366f1; }

        .toggle-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 4px;
            display: flex; align-items: center; opacity: 0.35; transition: opacity 0.2s;
        }
        .toggle-pw:hover { opacity: 0.8; }
        .toggle-pw svg { width: 17px; height: 17px; stroke: #1e1b4b; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

        /* Row */
        .row-meta {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px;
        }
        .remember { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
        .remember input[type="checkbox"] { width: 15px; height: 15px; accent-color: #6366f1; }
        .remember span { font-size: 13.5px; color: #6b7280; }
        .forgot { font-size: 13.5px; color: #6366f1; text-decoration: none; font-weight: 500; }
        .forgot:hover { text-decoration: underline; }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border: none;
            border-radius: 13px;
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.2px;
            box-shadow: 0 6px 24px rgba(99,102,241,0.35);
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 10px 32px rgba(99,102,241,0.42); }
        .btn-login:active { transform: translateY(0); }
        .btn-login.loading { opacity: 0.75; pointer-events: none; }

        .spinner {
            width: 17px; height: 17px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.65s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0 0;
        }
        .divider hr { flex: 1; border: none; border-top: 1px solid #e5e7f0; }
        .divider span { font-size: 12px; color: #b0b3c6; }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #b0b3c6;
        }

        /* Responsive */
        @media (max-width: 680px) {
            .page { grid-template-columns: 1fr; min-height: unset; border-radius: 20px; }
            .left { padding: 36px 32px; }
            .left-body h2 { font-size: 24px; }
            .left-stats { display: none; }
            .right { padding: 36px 32px; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- LEFT -->
    <div class="left">
        <div class="left-logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <span>LeadManager</span>
        </div>

        <div class="left-body">
            <h2>Manage your leads smarter.</h2>
            <p>All your leads, calls, and follow-ups — organized in one powerful dashboard.</p>
        </div>

        <div class="left-stats">
            <div class="stat-pill">
                <div class="num">2.4k</div>
                <div class="lbl">Active Leads</div>
            </div>
            <div class="stat-pill">
                <div class="num">98%</div>
                <div class="lbl">Uptime</div>
            </div>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="right">
        <div class="right-head">
            <h1>Welcome back 👋</h1>
            <p>Sign in to your admin account to continue</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="" autocomplete="off">

            <div class="field">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username"
                        placeholder="Enter your username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required autofocus>
                    <svg class="fi" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                        placeholder="Enter your password" required>
                    <svg class="fi" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <button type="button" class="toggle-pw" onclick="togglePw()">
                        <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="row-meta">
                <label class="remember">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Remember me</span>
                </label>
                <a href="#" class="forgot">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span id="btnText">Sign In</span>
            </button>

        </form>

        <div class="divider"><hr><span>secured by SSL</span><hr></div>

        <div class="footer-note">
            &copy; <?= date('Y') ?> LeadManager &mdash; Admin Panel
        </div>
    </div>

</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        pw.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    const txt = document.getElementById('btnText');
    btn.classList.add('loading');
    btn.innerHTML = '<div class="spinner"></div><span>Signing in...</span>';
});
</script>
</body>
</html>