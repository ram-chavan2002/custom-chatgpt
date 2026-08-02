<?php
// ─── DB CONFIG ────────────────────────────────────────────────────────────────
$host     = 'localhost';
$username = 'u743928828_lead_user';
$password = 'Admin_66666';
$database = 'u743928828_lead_db';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8");

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS city  VARCHAR(100) DEFAULT NULL");

$stateCities = [
    "Andhra Pradesh"       => ["Visakhapatnam","Vijayawada","Guntur","Nellore","Kurnool","Tirupati","Kakinada","Rajahmundry"],
    "Arunachal Pradesh"    => ["Itanagar","Naharlagun","Pasighat","Tezpur"],
    "Assam"                => ["Guwahati","Silchar","Dibrugarh","Jorhat","Nagaon","Tinsukia"],
    "Bihar"                => ["Patna","Gaya","Muzaffarpur","Bhagalpur","Darbhanga","Purnia","Arrah"],
    "Chhattisgarh"         => ["Raipur","Bhilai","Bilaspur","Korba","Durg","Rajnandgaon"],
    "Goa"                  => ["Panaji","Margao","Vasco da Gama","Mapusa","Ponda"],
    "Gujarat"              => ["Ahmedabad","Surat","Vadodara","Rajkot","Bhavnagar","Jamnagar","Gandhinagar","Junagadh"],
    "Haryana"              => ["Faridabad","Gurgaon","Panipat","Ambala","Yamunanagar","Rohtak","Hisar","Karnal","Sonipat"],
    "Himachal Pradesh"     => ["Shimla","Manali","Dharamshala","Solan","Mandi","Kullu"],
    "Jharkhand"            => ["Ranchi","Jamshedpur","Dhanbad","Bokaro","Deoghar","Hazaribagh"],
    "Karnataka"            => ["Bangalore","Mysore","Hubli","Mangalore","Belgaum","Gulbarga","Davangere","Bellary"],
    "Kerala"               => ["Thiruvananthapuram","Kochi","Kozhikode","Kannur","Kollam","Thrissur","Palakkad","Malappuram"],
    "Madhya Pradesh"       => ["Bhopal","Indore","Jabalpur","Gwalior","Ujjain","Sagar","Dewas","Satna"],
    "Maharashtra"          => ["Mumbai","Pune","Nagpur","Thane","Nashik","Aurangabad","Solapur","Kolhapur","Navi Mumbai","Amravati","Nanded","Sangli"],
    "Manipur"              => ["Imphal","Thoubal","Bishnupur","Churachandpur"],
    "Meghalaya"            => ["Shillong","Tura","Jowai"],
    "Mizoram"              => ["Aizawl","Lunglei","Champhai"],
    "Nagaland"             => ["Kohima","Dimapur","Mokokchung"],
    "Odisha"               => ["Bhubaneswar","Cuttack","Rourkela","Sambalpur","Berhampur"],
    "Punjab"               => ["Ludhiana","Amritsar","Jalandhar","Patiala","Bathinda","Mohali","Hoshiarpur"],
    "Rajasthan"            => ["Jaipur","Jodhpur","Udaipur","Kota","Ajmer","Bikaner","Alwar","Bharatpur"],
    "Sikkim"               => ["Gangtok","Namchi","Mangan"],
    "Tamil Nadu"           => ["Chennai","Coimbatore","Madurai","Tiruchirappalli","Salem","Tirunelveli","Erode","Vellore","Tiruppur"],
    "Telangana"            => ["Hyderabad","Warangal","Nizamabad","Karimnagar","Khammam","Ramagundam"],
    "Tripura"              => ["Agartala","Udaipur","Dharmanagar"],
    "Uttar Pradesh"        => ["Lucknow","Kanpur","Agra","Varanasi","Meerut","Allahabad","Ghaziabad","Noida","Bareilly","Moradabad","Aligarh","Gorakhpur"],
    "Uttarakhand"          => ["Dehradun","Haridwar","Roorkee","Haldwani","Rudrapur","Nainital","Rishikesh"],
    "West Bengal"          => ["Kolkata","Howrah","Durgapur","Asansol","Siliguri","Bardhaman","Malda"],
    "Delhi"                => ["New Delhi","Dwarka","Rohini","Janakpuri","Laxmi Nagar","Saket","Pitampura"],
    "Jammu & Kashmir"      => ["Srinagar","Jammu","Anantnag","Baramulla","Sopore"],
    "Ladakh"               => ["Leh","Kargil"],
    "Chandigarh"           => ["Chandigarh"],
    "Puducherry"           => ["Puducherry","Karaikal","Mahe"],
    "Andaman & Nicobar"    => ["Port Blair"],
    "Dadra & Nagar Haveli" => ["Silvassa"],
    "Daman & Diu"          => ["Daman","Diu"],
    "Lakshadweep"          => ["Kavaratti"],
];

$message = '';
$msgType = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = "User deleted successfully.";
    $msgType  = "success";
}

// ── CREATE USER — plain password, no hash ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'create') {
    $uname  = trim($_POST['username']);
    $pass   = trim($_POST['password']); // plain text - no hash
    $email  = trim($_POST['email']);
    $fname  = trim($_POST['full_name']);
    $state  = trim($_POST['state'] ?? '');
    $city   = trim($_POST['city']  ?? '');
    $status = $_POST['status'];

    $uname_esc = $conn->real_escape_string($uname);
    $email_esc = $conn->real_escape_string($email);

    $chk = $conn->query("SELECT id FROM users WHERE username='$uname_esc' OR email='$email_esc' LIMIT 1");
    if ($chk->num_rows > 0) {
        $message = "Username or Email already exists!";
        $msgType  = "warning";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, state, city, created_at, status) VALUES (?,?,?,?,?,?,NOW(),?)");
        $stmt->bind_param("sssssss", $uname, $pass, $email, $fname, $state, $city, $status);
        $stmt->execute();
        $stmt->close();
        $message = "User <b>" . htmlspecialchars($uname) . "</b> created!";
        $msgType  = "success";
    }
}

// ── UPDATE USER — plain password, no hash ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update') {
    $id     = (int)$_POST['id'];
    $uname  = trim($_POST['username']);
    $email  = trim($_POST['email']);
    $fname  = trim($_POST['full_name']);
    $state  = trim($_POST['state'] ?? '');
    $city   = trim($_POST['city']  ?? '');
    $status = $_POST['status'];

    $uname_esc = $conn->real_escape_string($uname);
    $email_esc = $conn->real_escape_string($email);

    $chk = $conn->query("SELECT id FROM users WHERE (username='$uname_esc' OR email='$email_esc') AND id!=$id LIMIT 1");
    if ($chk->num_rows > 0) {
        $message = "Username or Email already taken!";
        $msgType  = "warning";
    } else {
        if (!empty($_POST['password'])) {
            // plain password - no hash
            $pass = trim($_POST['password']);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, email=?, full_name=?, state=?, city=?, status=? WHERE id=?");
            $stmt->bind_param("sssssssi", $uname, $pass, $email, $fname, $state, $city, $status, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, full_name=?, state=?, city=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssi", $uname, $email, $fname, $state, $city, $status, $id);
        }
        $stmt->execute();
        $stmt->close();
        $message = "User <b>" . htmlspecialchars($uname) . "</b> updated!";
        $msgType  = "success";
    }
}

$editUser = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM users WHERE id=$id LIMIT 1");
    if ($res && $res->num_rows > 0) $editUser = $res->fetch_assoc();
}

$search = isset($_GET['search']) ? trim($conn->real_escape_string($_GET['search'])) : '';
$sql    = "SELECT * FROM users";
if ($search) {
    $sql .= " WHERE username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%' OR state LIKE '%$search%' OR city LIKE '%$search%'";
}
$sql   .= " ORDER BY id DESC";
$users  = $conn->query($sql);
$total  = $users->num_rows;

$totalAll    = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$totalActive = $conn->query("SELECT COUNT(*) c FROM users WHERE status='active'")->fetch_assoc()['c'];
$stateCitiesJson = json_encode($stateCities, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
:root{
  --bg:#0d0f1a;--sb:#111320;--border:#1e2235;--card:#161929;
  --accent:#3b82f6;--accent2:#f59e0b;--green:#22c55e;--red:#ef4444;
  --text:#e2e8f0;--muted:#6b7280;--hover:#1a1f38;
}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;margin:0;min-height:100vh}
.topnav{height:54px;background:var(--sb);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 18px;position:fixed;top:0;left:0;right:0;z-index:300}
.brand{display:flex;align-items:center;gap:9px;text-decoration:none}
.brand-ico{width:32px;height:32px;background:var(--accent);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff}
.brand-name{font-weight:700;font-size:1rem;color:#fff;letter-spacing:.2px}
.brand-name span{color:var(--accent)}
.search-wrap{flex:1;max-width:440px;margin:0 20px;background:#1a1f38;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;padding:0 12px;height:34px}
.search-wrap input{background:none;border:none;outline:none;color:var(--text);font-size:.85rem;width:100%}
.search-wrap input::placeholder{color:var(--muted)}
.search-wrap i{color:var(--muted);font-size:.88rem}
.nav-right{display:flex;align-items:center;gap:7px}
.btn-vr{background:transparent;border:1.5px solid var(--accent);color:var(--accent);padding:5px 13px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:.18s}
.btn-vr:hover{background:var(--accent);color:#fff}
.ico-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.18s;font-size:.95rem}
.ico-btn:hover,.ico-btn.on{background:var(--accent);color:#fff;border-color:var(--accent)}
.layout{display:flex;margin-top:54px;min-height:calc(100vh - 54px)}
.sidebar{width:256px;background:var(--sb);border-right:1px solid var(--border);position:fixed;top:54px;left:0;bottom:0;overflow-y:auto;display:flex;flex-direction:column;padding:14px 0 20px;z-index:200}
.btn-logout{margin:0 12px 14px;background:#2a1520;border:1px solid #3d1a28;color:#f87171;padding:8px 12px;border-radius:8px;font-size:.86rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.18s;width:calc(100% - 24px)}
.btn-logout:hover{background:#3d1a28}
.sec-lbl{font-size:.66rem;text-transform:uppercase;letter-spacing:1.2px;color:var(--muted);font-weight:600;padding:10px 16px 6px}
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;padding:0 12px 6px}
.s-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:11px 10px}
.s-val{font-size:1.35rem;font-weight:700;line-height:1}
.s-val.blue{color:var(--accent)}.s-val.yellow{color:var(--accent2)}
.s-lbl{font-size:.68rem;color:var(--muted);margin-top:3px}
.slink{display:flex;align-items:center;gap:9px;color:var(--muted);padding:9px 16px;font-size:.87rem;font-weight:500;border-radius:8px;margin:2px 10px;transition:.15s;cursor:pointer;text-decoration:none;border:none;background:none;width:calc(100% - 20px);text-align:left}
.slink:hover{color:var(--text);background:var(--hover)}
.slink.active{color:#fff;background:rgba(59,130,246,.15);border-left:3px solid var(--accent);padding-left:13px}
.slink i{font-size:.95rem;flex-shrink:0}
.no-meet{margin:0 12px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px 10px;text-align:center;color:var(--muted);font-size:.8rem}
.no-meet i{font-size:1.4rem;display:block;margin-bottom:6px;color:var(--border)}
.main{margin-left:256px;flex:1;display:flex;flex-direction:column}
.toolbar{background:var(--sb);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px}
.f-btn{background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 14px;border-radius:7px;font-size:.82rem;font-weight:500;cursor:pointer;transition:.15s}
.f-btn:hover,.f-btn.on{border-color:var(--accent);color:var(--accent)}
.tb-right{margin-left:auto;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.t-btn{display:flex;align-items:center;gap:5px;padding:6px 13px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;transition:.18s}
.t-btn.blue{background:transparent;border:1.5px solid var(--accent);color:var(--accent)}
.t-btn.blue:hover{background:var(--accent);color:#fff}
.count-bar{padding:10px 20px;font-size:.83rem;color:var(--muted);background:var(--sb);border-bottom:1px solid var(--border)}
.count-bar b{color:var(--text)}
.dk-alert{margin:14px 20px 0;padding:10px 16px;border-radius:9px;font-size:.87rem;display:flex;align-items:center;justify-content:space-between;gap:10px}
.dk-alert.success{background:#052e16;border:1px solid #166534;color:#86efac}
.dk-alert.warning{background:#451a03;border:1px solid #92400e;color:#fcd34d}
.dk-alert .btn-close-dk{background:none;border:none;cursor:pointer;color:inherit;font-size:1rem;line-height:1}
.tbl-wrap{padding:20px}
.tbl-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.tbl-head{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px}
.tbl-head-title{font-size:.9rem;font-weight:600;color:var(--text)}
.search-inp{background:#0d0f1a;border:1px solid var(--border);border-radius:7px;color:var(--text);padding:5px 12px;font-size:.83rem;outline:none;width:220px}
.search-inp::placeholder{color:var(--muted)}
.search-inp:focus{border-color:var(--accent)}
.btn-search{background:transparent;border:1px solid var(--border);color:var(--muted);padding:5px 10px;border-radius:7px;cursor:pointer;transition:.15s}
.btn-search:hover{border-color:var(--accent);color:var(--accent)}
.btn-clear{background:transparent;border:1px solid var(--border);color:var(--muted);padding:5px 10px;border-radius:7px;cursor:pointer;text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center}
table{width:100%;border-collapse:collapse}
thead tr{background:#0d0f1a}
thead th{padding:11px 14px;font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:.13s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#131627}
td{padding:12px 14px;font-size:.87rem;vertical-align:middle}
.avatar{width:34px;height:34px;border-radius:50%;background:rgba(59,130,246,.15);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0}
.b-active{background:#052e16;color:#4ade80;padding:3px 11px;border-radius:999px;font-size:.76rem;font-weight:600}
.b-inactive{background:#450a0a;color:#f87171;padding:3px 11px;border-radius:999px;font-size:.76rem;font-weight:600}
.loc-badge{background:rgba(59,130,246,.1);color:#93c5fd;padding:3px 9px;border-radius:999px;font-size:.76rem;font-weight:500;display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
.act-btn{background:transparent;border:1px solid var(--border);color:var(--muted);width:30px;height:30px;border-radius:6px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;text-decoration:none;transition:.15s}
.act-btn:hover.edit{border-color:var(--accent);color:var(--accent)}
.act-btn:hover.del{border-color:var(--red);color:var(--red)}
.empty-state{padding:60px 20px;text-align:center;color:var(--muted)}

/* ── NOTE BADGE for plain password ── */
.pass-note{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#86efac;padding:8px 14px;border-radius:8px;font-size:.8rem;margin:10px 20px 0;display:flex;align-items:center;gap:7px}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:14px;width:100%;max-width:660px;max-height:90vh;overflow-y:auto}
.modal-box::-webkit-scrollbar{width:3px}
.modal-box::-webkit-scrollbar-thumb{background:var(--border)}
.modal-hdr{padding:18px 20px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.modal-hdr h5{font-size:.95rem;font-weight:700;margin:0;color:var(--text)}
.modal-hdr h5 i{color:var(--accent);margin-right:7px}
.btn-close-modal{background:none;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer;line-height:1;padding:0}
.btn-close-modal:hover{color:var(--text)}
.modal-body{padding:18px 20px}
.modal-ftr{padding:12px 20px 18px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border)}
.form-sec{font-size:.68rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);font-weight:600;padding:12px 0 8px;border-bottom:1px solid var(--border);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.form-lbl{font-weight:500;font-size:.84rem;color:#94a3b8;margin-bottom:5px;display:block}
.form-ctrl{width:100%;background:#0d0f1a;border:1.5px solid var(--border);border-radius:8px;color:var(--text);padding:8px 12px;font-size:.87rem;outline:none;transition:.15s}
.form-ctrl:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.form-ctrl option{background:var(--card)}
.input-grp{display:flex}
.input-grp .form-ctrl{border-radius:8px 0 0 8px}
.btn-eye{background:#0d0f1a;border:1.5px solid var(--border);border-left:none;color:var(--muted);padding:0 12px;border-radius:0 8px 8px 0;cursor:pointer;transition:.15s}
.btn-eye:hover{color:var(--text)}
.btn-cancel{background:transparent;border:1.5px solid var(--border);color:var(--muted);padding:7px 18px;border-radius:8px;font-size:.86rem;cursor:pointer;transition:.15s;text-decoration:none;display:inline-flex;align-items:center}
.btn-cancel:hover{border-color:var(--muted);color:var(--text)}
.btn-submit{background:var(--accent);border:none;color:#fff;padding:8px 22px;border-radius:8px;font-size:.86rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:.15s}
.btn-submit:hover{opacity:.88}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:540px){.row2{grid-template-columns:1fr}}
@media(max-width:768px){.sidebar{display:none}.main{margin-left:0}.search-wrap{display:none}.toolbar{flex-wrap:wrap}}
</style>
</head>
<body>

<nav class="topnav">
  <a class="brand" href="lead.php">
    <div class="brand-ico"><i class="bi bi-clipboard-data"></i></div>
    <div class="brand-name">Lead<span>Pro</span></div>
  </a>
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" placeholder="Search name, email, state, city…" id="liveSearch" value="<?= htmlspecialchars($search) ?>">
  </div>
  <div class="nav-right">
    <button class="btn-vr" onclick="location.href='lead.php'">
      <i class="bi bi-arrow-left-circle"></i> Back to Leads
    </button>
    <div class="ico-btn on"><i class="bi bi-people"></i></div>
  </div>
</nav>

<div class="layout">
<aside class="sidebar">
  <button class="btn-logout" onclick="location.href='logout.php'">
    <i class="bi bi-box-arrow-right"></i> Logout
  </button>
  <div class="sec-lbl">Overview</div>
  <div class="stats-grid">
    <div class="s-card"><div class="s-val blue"><?= $totalAll ?></div><div class="s-lbl">Total Users</div></div>
    <div class="s-card"><div class="s-val blue"><?= $totalActive ?></div><div class="s-lbl">Active</div></div>
    <div class="s-card"><div class="s-val blue"><?= $totalAll - $totalActive ?></div><div class="s-lbl">Inactive</div></div>
    <div class="s-card"><div class="s-val yellow"><?= $totalAll ?></div><div class="s-lbl">Total</div></div>
  </div>
  <div class="sec-lbl" style="margin-top:8px">Navigation</div>
  <a href="lead.php" class="slink"><i class="bi bi-clipboard-data"></i> Lead Manager</a>
  <a href="view_register.php" class="slink active"><i class="bi bi-people"></i> Users</a>
</aside>

<main class="main">
  <div class="toolbar">
    <button class="f-btn on">All Users</button>
    <div class="tb-right">
      <button class="t-btn blue" onclick="openModal('createModal')">
        <i class="bi bi-plus-lg"></i> Add New User
      </button>
    </div>
  </div>

  <div class="count-bar">Showing <b><?= $total ?></b> of <b><?= $totalAll ?></b> users</div>

  <!-- Plain password notice -->
  <div class="pass-note">
    <i class="bi bi-shield-check"></i>
    Passwords are stored as plain text. Users login with exact password they are given.
  </div>

  <?php if ($message): ?>
  <div class="dk-alert <?= $msgType ?>" id="dkAlert">
    <span><?= $message ?></span>
    <button class="btn-close-dk" onclick="document.getElementById('dkAlert').remove()">✕</button>
  </div>
  <?php endif; ?>

  <div class="tbl-wrap">
    <div class="tbl-card">
      <div class="tbl-head">
        <div class="tbl-head-title"><i class="bi bi-people me-1"></i> All Users (<?= $total ?>)</div>
        <form method="GET" style="display:flex;gap:6px;align-items:center">
          <input type="text" name="search" class="search-inp" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
          <?php if ($search): ?>
          <a href="view_register.php" class="btn-clear"><i class="bi bi-x"></i></a>
          <?php endif; ?>
        </form>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>#</th><th>User</th><th>Email</th><th>Password</th>
              <th>📍 State › City</th><th>Status</th><th>Created</th>
              <th style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($total === 0): ?>
            <tr><td colspan="8"><div class="empty-state"><p>No users found.</p></div></td></tr>
          <?php else: ?>
            <?php while ($row = $users->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--muted);font-size:.8rem"><?= $row['id'] ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:9px">
                  <div class="avatar"><?= strtoupper(substr($row['username'],0,1)) ?></div>
                  <div>
                    <div style="font-weight:600"><?= htmlspecialchars($row['username']) ?></div>
                    <div style="color:var(--muted);font-size:.76rem"><?= htmlspecialchars($row['full_name']) ?></div>
                  </div>
                </div>
              </td>
              <td style="color:#94a3b8;font-size:.83rem"><?= htmlspecialchars($row['email']) ?></td>
              <td>
                <!-- Show password plainly for admin reference -->
                <span style="font-family:monospace;font-size:.8rem;color:#fbbf24;background:rgba(251,191,36,.08);padding:3px 8px;border-radius:5px;border:1px solid rgba(251,191,36,.2)">
                  <?= htmlspecialchars($row['password']) ?>
                </span>
              </td>
              <td>
                <?php if (!empty($row['state'])): ?>
                <span class="loc-badge">
                  <i class="bi bi-geo-alt-fill"></i>
                  <?= htmlspecialchars($row['state']) ?>
                  <?php if (!empty($row['city'])): ?> › <?= htmlspecialchars($row['city']) ?><?php endif; ?>
                </span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem">— Not assigned —</span>
                <?php endif; ?>
              </td>
              <td><span class="<?= $row['status']==='active' ? 'b-active' : 'b-inactive' ?>"><?= ucfirst($row['status']) ?></span></td>
              <td style="color:var(--muted);font-size:.8rem"><?= $row['created_at'] ?></td>
              <td style="text-align:center">
                <a href="view_register.php?action=edit&id=<?= $row['id'] ?>" class="act-btn edit" title="Edit"><i class="bi bi-pencil-square"></i></a>
                <a href="view_register.php?action=delete&id=<?= $row['id'] ?>" class="act-btn del" title="Delete" onclick="return confirm('Delete this user?')" style="margin-left:4px"><i class="bi bi-trash3"></i></a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</div>

<!-- CREATE MODAL -->
<div class="modal-overlay" id="createModal">
  <div class="modal-box">
    <div class="modal-hdr">
      <h5><i class="bi bi-person-plus"></i>Add New User</h5>
      <button class="btn-close-modal" onclick="closeModal('createModal')">✕</button>
    </div>
    <form method="POST" action="view_register.php">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-sec"><i class="bi bi-person"></i> Account Info</div>
        <div class="row2">
          <div>
            <label class="form-lbl">Username *</label>
            <input type="text" name="username" class="form-ctrl" placeholder="e.g. john_doe" required>
          </div>
          <div>
            <label class="form-lbl">Full Name *</label>
            <input type="text" name="full_name" class="form-ctrl" placeholder="e.g. John Doe" required>
          </div>
          <div>
            <label class="form-lbl">Email *</label>
            <input type="email" name="email" class="form-ctrl" placeholder="john@example.com" required>
          </div>
          <div>
            <label class="form-lbl">Password * <span style="color:#6b7280;font-size:.75rem">(plain text)</span></label>
            <div class="input-grp">
              <input type="text" name="password" id="cp" class="form-ctrl" placeholder="e.g. mypass123" required>
              <button type="button" class="btn-eye" onclick="toggleType('cp',this)"><i class="bi bi-eye-slash"></i></button>
            </div>
          </div>
        </div>
        <div class="form-sec" style="margin-top:16px"><i class="bi bi-geo-alt"></i> Location</div>
        <div class="row2">
          <div>
            <label class="form-lbl">State</label>
            <select name="state" id="c_state" class="form-ctrl" onchange="loadCities(this,'c_city')">
              <option value="">— Select State —</option>
              <?php foreach ($stateCities as $st => $cities): ?>
              <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-lbl">City</label>
            <select name="city" id="c_city" class="form-ctrl" disabled>
              <option value="">— Select State First —</option>
            </select>
          </div>
        </div>
        <div class="form-sec" style="margin-top:16px"><i class="bi bi-toggle-on"></i> Status</div>
        <div style="max-width:50%">
          <label class="form-lbl">Account Status</label>
          <select name="status" class="form-ctrl">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-ftr">
        <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn-submit"><i class="bi bi-person-plus"></i> Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<?php if ($editUser): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal-box">
    <div class="modal-hdr">
      <h5><i class="bi bi-pencil-square"></i>Edit — <?= htmlspecialchars($editUser['username']) ?></h5>
      <a href="view_register.php" class="btn-close-modal">✕</a>
    </div>
    <form method="POST" action="view_register.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
      <div class="modal-body">
        <div class="form-sec"><i class="bi bi-person"></i> Account Info</div>
        <div class="row2">
          <div>
            <label class="form-lbl">Username *</label>
            <input type="text" name="username" class="form-ctrl" value="<?= htmlspecialchars($editUser['username']) ?>" required>
          </div>
          <div>
            <label class="form-lbl">Full Name *</label>
            <input type="text" name="full_name" class="form-ctrl" value="<?= htmlspecialchars($editUser['full_name']) ?>" required>
          </div>
          <div>
            <label class="form-lbl">Email *</label>
            <input type="email" name="email" class="form-ctrl" value="<?= htmlspecialchars($editUser['email']) ?>" required>
          </div>
          <div>
            <label class="form-lbl">New Password <span style="color:#6b7280;font-size:.75rem">(blank = keep)</span></label>
            <div class="input-grp">
              <input type="text" name="password" id="ep" class="form-ctrl" placeholder="Leave blank to keep">
              <button type="button" class="btn-eye" onclick="toggleType('ep',this)"><i class="bi bi-eye-slash"></i></button>
            </div>
          </div>
        </div>
        <div class="form-sec" style="margin-top:16px"><i class="bi bi-geo-alt"></i> Location</div>
        <div class="row2">
          <div>
            <label class="form-lbl">State</label>
            <select name="state" id="e_state" class="form-ctrl" onchange="loadCities(this,'e_city')">
              <option value="">— Select State —</option>
              <?php foreach ($stateCities as $st => $cities): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= ($editUser['state']??'')===$st?'selected':'' ?>><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-lbl">City</label>
            <select name="city" id="e_city" class="form-ctrl">
              <option value="">— Loading… —</option>
            </select>
          </div>
        </div>
        <div class="form-sec" style="margin-top:16px"><i class="bi bi-toggle-on"></i> Status</div>
        <div style="max-width:50%">
          <label class="form-lbl">Account Status</label>
          <select name="status" class="form-ctrl">
            <option value="active"   <?= ($editUser['status']??'')==='active'  ?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($editUser['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-ftr">
        <a href="view_register.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-submit"><i class="bi bi-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const stateCities = <?= $stateCitiesJson ?>;
const currentCity = "<?= htmlspecialchars($editUser['city'] ?? '') ?>";

function openModal(id)  { document.getElementById(id).classList.add('open') }
function closeModal(id) { document.getElementById(id).classList.remove('open') }

document.querySelectorAll('.modal-overlay').forEach(function(el){
  el.addEventListener('click', function(e){ if(e.target===el) el.classList.remove('open') });
});

function loadCities(sel, cityId){
  var state  = sel.value;
  var cityEl = document.getElementById(cityId);
  cityEl.innerHTML = '<option value="">— Select City —</option>';
  cityEl.disabled  = !state;
  if(state && stateCities[state]){
    stateCities[state].forEach(function(c){
      var o = document.createElement('option');
      o.value = c; o.textContent = c;
      if(c === currentCity) o.selected = true;
      cityEl.appendChild(o);
    });
  }
}

// Toggle show/hide password (text type toggle)
function toggleType(id, btn){
  var i = document.getElementById(id);
  var isText = i.type === 'text';
  i.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<i class="bi bi-eye-slash"></i>'
    : '<i class="bi bi-eye"></i>';
}

// Pre-load cities for edit modal
<?php if ($editUser && !empty($editUser['state'])): ?>
(function(){
  var s = document.getElementById('e_state');
  if(s && s.value) loadCities(s,'e_city');
})();
<?php endif; ?>

document.getElementById('liveSearch').addEventListener('keydown', function(e){
  if(e.key==='Enter'){
    var v = this.value.trim();
    location.href = v ? 'view_register.php?search='+encodeURIComponent(v) : 'view_register.php';
  }
});
</script>
</body>
</html>