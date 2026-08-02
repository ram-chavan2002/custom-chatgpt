<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: view_login.php');
    exit;
}

require_once 'db.php';

$user_state = $_SESSION['user_state'] ?? '';
$user_city  = $_SESSION['user_city']  ?? '';
$user_name  = $_SESSION['full_name']  ?? $_SESSION['username'] ?? 'User';

// ── DEBUG: Uncomment to check session values ──
// echo "<pre>SESSION: "; print_r($_SESSION); echo "</pre>"; exit;

$search      = isset($_GET['search'])      ? trim($_GET['search'])      : '';
$date_filter = isset($_GET['date'])        ? $_GET['date']              : 'newest';
$create_from = isset($_GET['create_from']) ? trim($_GET['create_from']) : '';
$create_to   = isset($_GET['create_to'])   ? trim($_GET['create_to'])   : '';
$expiry_from = isset($_GET['expiry_from']) ? trim($_GET['expiry_from']) : '';
$expiry_to   = isset($_GET['expiry_to'])   ? trim($_GET['expiry_to'])   : '';

// ── FIX 1: TRIM session values (sometimes spaces cause mismatch) ──
$user_city  = trim($user_city);
$user_state = trim($user_state);

// ── FIX 2: Check which columns exist ──
$existing_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM domains");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) {
        $existing_cols[] = $c['Field'];
    }
}
$has_uploaded_at = in_array('uploaded_at', $existing_cols);
$has_create_date = in_array('create_date', $existing_cols);

// Pick the right timestamp column
if ($has_uploaded_at) {
    $ts_col = 'uploaded_at';
} elseif ($has_create_date) {
    $ts_col = 'create_date';
} else {
    $ts_col = 'id'; // fallback
}

// ── FIX 3: Select proper columns ──
$select_cols = "id, domain_name, registrant_name, registrant_phone,
    registrant_email, registrant_state, registrant_city,
    domain_registrar_name, expiry_date";

if ($has_uploaded_at) $select_cols .= ", uploaded_at";
if ($has_create_date) $select_cols .= ", create_date";

$query = "SELECT $select_cols FROM domains WHERE 1=1";

// ── FIX 4: COLLATE UTF8 + TRIM for case-insensitive matching ──
if (!empty($user_city)) {
    $uc = $conn->real_escape_string($user_city);
    // TRIM + LOWER on both sides to handle spaces & case
    $query .= " AND LOWER(TRIM(registrant_city)) = LOWER(TRIM('$uc'))";
} elseif (!empty($user_state)) {
    $us = $conn->real_escape_string($user_state);
    $query .= " AND LOWER(TRIM(registrant_state)) = LOWER(TRIM('$us'))";
}

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $query .= " AND (domain_name LIKE '%$s%' OR registrant_name LIKE '%$s%' 
                OR registrant_email LIKE '%$s%' OR registrant_phone LIKE '%$s%')";
}

// ── FIX 5: uploaded_at OR create_date for date filters ──
if (!empty($create_from)) {
    $cf = $conn->real_escape_string($create_from);
    if ($has_uploaded_at) {
        $query .= " AND DATE(uploaded_at) >= '$cf'";
    } elseif ($has_create_date) {
        $query .= " AND DATE(create_date) >= '$cf'";
    }
}
if (!empty($create_to)) {
    $ct = $conn->real_escape_string($create_to);
    if ($has_uploaded_at) {
        $query .= " AND DATE(uploaded_at) <= '$ct'";
    } elseif ($has_create_date) {
        $query .= " AND DATE(create_date) <= '$ct'";
    }
}
if (!empty($expiry_from)) {
    $ef = $conn->real_escape_string($expiry_from);
    $query .= " AND expiry_date >= '$ef'";
}
if (!empty($expiry_to)) {
    $et = $conn->real_escape_string($expiry_to);
    $query .= " AND expiry_date <= '$et'";
}

switch ($date_filter) {
    case 'oldest':
        $query .= $has_uploaded_at ? " ORDER BY uploaded_at ASC" : " ORDER BY id ASC";
        break;
    case 'expiring':
        $query .= " ORDER BY expiry_date ASC";
        break;
    default:
        $query .= $has_uploaded_at ? " ORDER BY uploaded_at DESC" : " ORDER BY id DESC";
        break;
}

// ── FIX 6: Show leads if city OR state is set ──
if (empty($user_city) && empty($user_state)) {
    $result = null;
    $total_leads = 0;
} else {
    $result      = $conn->query($query);
    $total_leads = $result ? $result->num_rows : 0;

    // ── FIX 7: If 0 results, try fallback with LIKE (partial match) ──
    if ($total_leads === 0 && !empty($user_city)) {
        $fallback_query = str_replace(
            "LOWER(TRIM(registrant_city)) = LOWER(TRIM('$user_city'))",
            "registrant_city LIKE '%" . $conn->real_escape_string($user_city) . "%'",
            $query
        );
        $result_fb = $conn->query($fallback_query);
        if ($result_fb && $result_fb->num_rows > 0) {
            $result      = $result_fb;
            $total_leads = $result_fb->num_rows;
        }
    }
}

// ── HELPER: uploaded_at badge ──
function getDateBadge($row) {
    $ua = $row['uploaded_at'] ?? $row['create_date'] ?? '';
    if (empty($ua) || $ua == '0000-00-00 00:00:00' || $ua == '0000-00-00') return '';
    $diff = (time() - strtotime($ua)) / 86400;
    if ($diff <= 7)  return '<span class="badge-new">NEW</span>';
    if ($diff <= 30) return '<span class="badge-recent">Recent</span>';
    return '<span class="badge-old">Old</span>';
}

function getExpiryBadge($expiry_date) {
    if (empty($expiry_date) || $expiry_date == '0000-00-00') return '';
    $diff = (strtotime($expiry_date) - time()) / 86400;
    if ($diff < 0)   return '<span class="badge-expired">Expired</span>';
    if ($diff <= 30) return '<span class="badge-expiring">Expiring Soon</span>';
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leads - DomainCRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7c4dff;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-call: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --text-muted: #9ba4b5;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ebf5 100%);
            min-height: 100vh;
        }

        /* TOPBAR */
        .topbar {
            background: white; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 800; color: #1a1d2e; }
        .topbar-left i { color: var(--primary); }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .user-info { display: flex; align-items: center; gap: 8px; background: #f5f7fa; padding: 8px 14px; border-radius: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--gradient-1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: 700; }
        .user-details { line-height: 1.3; }
        .user-details .uname { font-size: 13px; font-weight: 700; color: #1a1d2e; }
        .user-details .uloc { font-size: 11px; color: var(--text-muted); }
        .btn-logout { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: rgba(245,87,108,0.1); color: #f5576c; border: 1px solid rgba(245,87,108,0.3); border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; }
        .btn-logout:hover { background: rgba(245,87,108,0.2); transform: translateX(2px); }

        /* MAIN */
        .main-content { padding: 20px; max-width: 1600px; margin: 0 auto; }
        .page-header { margin-bottom: 24px; background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-left: 4px solid var(--primary); }
        .page-header h1 { font-size: 22px; color: #1a1d2e; margin: 0 0 6px 0; font-weight: 700; }
        .page-header p { color: var(--text-muted); font-size: 13px; margin: 0; }
        .loc-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(124,77,255,0.1); color: var(--primary); font-weight: 700; font-size: 13px; padding: 3px 12px; border-radius: 20px; margin-left: 6px; }

        /* DEBUG BOX */
        .debug-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 12px; font-family: monospace; color: #856404; }

        /* FILTERS */
        .filters-section { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .filter-section-title { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #f0f3f7; display: flex; align-items: center; gap: 6px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .date-filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; padding: 16px; background: #f8f9fc; border-radius: 12px; border: 2px solid #e8edf5; }
        .date-filter-group-title { grid-column: 1 / -1; font-size: 12px; font-weight: 700; color: #1a1d2e; display: flex; align-items: center; gap: 6px; margin-bottom: -4px; }
        .date-filter-group-title i { color: var(--primary); }
        .filter-group label { font-weight: 600; font-size: 12px; color: #1a1d2e; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
        .filter-input { padding: 10px 14px; border: 2px solid #e0e6f0; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; width: 100%; transition: all 0.3s ease; background: white; }
        .filter-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(124,77,255,0.1); }
        input[type="date"].filter-input { color: #1a1d2e; }
        .btn-row { display: flex; gap: 10px; margin-top: 4px; }
        .btn-filter { background: var(--gradient-1); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; flex: 1; font-family: 'Inter', sans-serif; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-filter:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-reset { background: #f5f7fa; color: #9ba4b5; border: 2px solid #e0e6f0; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Inter', sans-serif; font-size: 14px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-reset:hover { background: #fee; color: #f5576c; border-color: #f5576c; }

        /* ACTIVE FILTERS */
        .active-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .filter-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(124,77,255,0.1); color: var(--primary); font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px; }

        /* STATS */
        .stats-bar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .stats-info { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: #1a1d2e; }

        /* BADGES */
        .badge-new     { background: linear-gradient(135deg,#11998e,#38ef7d); color:white; font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; letter-spacing:.5px; margin-left:5px; vertical-align:middle; }
        .badge-recent  { background: linear-gradient(135deg,#f093fb,#f5576c); color:white; font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; letter-spacing:.5px; margin-left:5px; vertical-align:middle; }
        .badge-old     { background:#e0e6f0; color:#9ba4b5; font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; letter-spacing:.5px; margin-left:5px; vertical-align:middle; }
        .badge-expired { background: linear-gradient(135deg,#ff416c,#ff4b2b); color:white; font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; letter-spacing:.5px; margin-left:5px; vertical-align:middle; }
        .badge-expiring{ background: linear-gradient(135deg,#f7971e,#ffd200); color:#7a4500; font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; letter-spacing:.5px; margin-left:5px; vertical-align:middle; }

        /* CARDS */
        .leads-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap: 20px; }
        .lead-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s ease; border: 2px solid transparent; }
        .lead-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.12); border-color: var(--primary); }
        .lead-header { display: flex; align-items: flex-start; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f3f7; }
        .lead-avatar { width: 48px; height: 48px; background: var(--gradient-1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; flex-shrink: 0; }
        .lead-name { flex: 1; margin-left: 12px; }
        .lead-name h3 { font-size: 15px; font-weight: 700; color: #1a1d2e; margin: 0 0 5px 0; }
        .lead-domain { font-size: 12px; color: var(--primary); font-weight: 600; background: rgba(124,77,255,0.1); padding: 3px 10px; border-radius: 16px; display: inline-block; }
        .lead-info { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
        .info-row { display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .info-icon { width: 32px; height: 32px; background: #f8f9fc; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 14px; flex-shrink: 0; }
        .info-content { flex: 1; min-width: 0; }
        .info-label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-value { font-weight: 600; color: #1a1d2e; word-break: break-word; font-size: 13px; }
        .info-row.date-row .info-icon { background: rgba(124,77,255,0.08); }
        .lead-footer { margin-top: 12px; padding-top: 12px; border-top: 2px solid #f0f3f7; }
        .action-buttons { display: flex; gap: 8px; }

        /* BUTTONS */
        .btn-call {
            flex: 1; padding: 9px 12px; border-radius: 8px; font-weight: 600; font-size: 12px;
            cursor: pointer; transition: all 0.3s ease; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: var(--gradient-call); color: white; border: none;
        }
        .btn-call:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(17,153,142,0.4); color: white; }
        .btn-whatsapp {
            flex: 1; padding: 9px 12px; border-radius: 8px; font-weight: 600; font-size: 12px;
            cursor: pointer; transition: all 0.3s ease; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none;
        }
        .btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(37,211,102,0.4); color: white; }
        .btn-followup {
            flex: 1; padding: 9px 12px; border-radius: 8px; font-weight: 600; font-size: 12px;
            cursor: pointer; transition: all 0.3s ease; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;
        }
        .btn-followup:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(245,87,108,0.4); color: white; }

        /* NO LEADS */
        .no-leads { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .leads-grid { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
            .date-filter-row { grid-template-columns: 1fr; }
            .user-details { display: none; }
            .topbar-left span { display: none; }
        }
        @media (max-width: 480px) {
            .action-buttons { flex-direction: column; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <i class="fas fa-globe"></i>
        <span>DomainCRM</span>
    </div>
    <div class="topbar-right">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
            <div class="user-details">
                <div class="uname"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="uloc">
                    <i class="fas fa-map-marker-alt" style="font-size:9px;"></i>
                    <?php
                    if (!empty($user_city) && !empty($user_state))
                        echo htmlspecialchars($user_city.', '.$user_state);
                    elseif (!empty($user_city))
                        echo htmlspecialchars($user_city);
                    elseif (!empty($user_state))
                        echo htmlspecialchars($user_state);
                    else echo 'Not assigned';
                    ?>
                </div>
            </div>
        </div>
        <a href="view_login.php" class="btn-logout" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">

    <div class="page-header">
        <h1><i class="fas fa-address-book"></i> View Leads</h1>
        <p>Your assigned location:
            <span class="loc-badge">
                <i class="fas fa-map-marker-alt"></i>
                <?php
                if (!empty($user_city) && !empty($user_state))
                    echo htmlspecialchars($user_city.', '.$user_state);
                elseif (!empty($user_city))
                    echo htmlspecialchars($user_city);
                elseif (!empty($user_state))
                    echo htmlspecialchars($user_state);
                else echo '<span style="color:#f5576c;">No location assigned</span>';
                ?>
            </span>
        </p>
    </div>

    <!-- ── FILTERS ── -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filter-section-title"><i class="fas fa-sliders-h"></i> Basic Filters</div>
            <div class="filter-row">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" class="filter-input"
                           placeholder="Name, email, phone, domain..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-sort"></i> Sort By</label>
                    <select name="date" class="filter-input">
                        <option value="newest"   <?php echo ($date_filter=='newest')   ?'selected':''; ?>>Newest First</option>
                        <option value="oldest"   <?php echo ($date_filter=='oldest')   ?'selected':''; ?>>Oldest First</option>
                        <option value="expiring" <?php echo ($date_filter=='expiring') ?'selected':''; ?>>Expiring Soon</option>
                    </select>
                </div>
            </div>

            <div class="date-filter-row">
                <div class="date-filter-group-title"><i class="fas fa-upload"></i> Uploaded / Created Date Range</div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-plus"></i> From Date</label>
                    <input type="date" name="create_from" class="filter-input" value="<?php echo htmlspecialchars($create_from); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-check"></i> To Date</label>
                    <input type="date" name="create_to" class="filter-input" value="<?php echo htmlspecialchars($create_to); ?>">
                </div>
            </div>

            <div class="date-filter-row" style="border-color:#ffe0e0;background:#fff8f8;">
                <div class="date-filter-group-title">
                    <i class="fas fa-calendar-times" style="color:#f5576c;"></i>
                    <span style="color:#f5576c;">Expiry Date Range</span>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-minus"></i> Expiry From</label>
                    <input type="date" name="expiry_from" class="filter-input" value="<?php echo htmlspecialchars($expiry_from); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-times"></i> Expiry To</label>
                    <input type="date" name="expiry_to" class="filter-input" value="<?php echo htmlspecialchars($expiry_to); ?>">
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                <a href="?" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </div>

            <?php $hasFilters = $search || $create_from || $create_to || $expiry_from || $expiry_to; ?>
            <?php if ($hasFilters): ?>
            <div class="active-filters">
                <?php if ($search): ?>
                    <span class="filter-chip"><i class="fas fa-search"></i> Search: <?php echo htmlspecialchars($search); ?></span>
                <?php endif; ?>
                <?php if ($create_from || $create_to): ?>
                    <span class="filter-chip"><i class="fas fa-upload"></i> Uploaded: <?php echo ($create_from?:'...').' → '.($create_to?:'...'); ?></span>
                <?php endif; ?>
                <?php if ($expiry_from || $expiry_to): ?>
                    <span class="filter-chip" style="background:rgba(245,87,108,0.1);color:#f5576c;">
                        <i class="fas fa-calendar-times"></i> Expiry: <?php echo ($expiry_from?:'...').' → '.($expiry_to?:'...'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stats-info">
            <i class="fas fa-database" style="color:var(--primary);"></i>
            <span>Total: <strong><?php echo number_format($total_leads); ?></strong> Leads</span>
        </div>
        <?php if (!empty($search)): ?>
        <div style="font-size:13px;color:var(--text-muted);">
            Search: "<strong><?php echo htmlspecialchars($search); ?></strong>"
        </div>
        <?php endif; ?>
    </div>

    <!-- ── LEADS ── -->
    <?php if (empty($user_city) && empty($user_state)): ?>
        <div class="no-leads">
            <i class="fas fa-map-marker-alt" style="font-size:60px;color:#f5576c;margin-bottom:20px;display:block;"></i>
            <h3 style="font-size:20px;color:#1a1d2e;margin-bottom:10px;">No Location Assigned</h3>
            <p style="color:var(--text-muted);">Admin tamne city/state assign kare pachhi leads dekhase.</p>
        </div>

    <?php elseif ($result && $result->num_rows > 0): ?>
        <div class="leads-grid">
        <?php while ($lead = $result->fetch_assoc()):
            $name        = $lead['registrant_name'] ?: 'N/A';
            $initial     = strtoupper(substr($name, 0, 1));
            $phone       = $lead['registrant_phone'] ?: '';
            $clean_phone = preg_replace('/[^0-9+]/', '', $phone);

            // India +91 format
            if (strlen($clean_phone) === 10) {
                $clean_phone = '+91' . $clean_phone;
            } elseif (strlen($clean_phone) === 12 && substr($clean_phone, 0, 2) === '91') {
                $clean_phone = '+' . $clean_phone;
            }
            $wa_phone = ltrim($clean_phone, '+');

            // Get the correct date column
            $uploaded_at = $lead['uploaded_at'] ?? $lead['create_date'] ?? '';
        ?>
            <div class="lead-card">
                <div class="lead-header">
                    <div class="lead-avatar"><?php echo $initial; ?></div>
                    <div class="lead-name">
                        <h3>
                            <?php echo htmlspecialchars($name); ?>
                            <?php echo getDateBadge($lead); ?>
                            <?php echo getExpiryBadge($lead['expiry_date'] ?? ''); ?>
                        </h3>
                        <span class="lead-domain"><?php echo htmlspecialchars($lead['domain_name']); ?></span>
                    </div>
                </div>

                <div class="lead-info">
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-content">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['registrant_email'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="info-content">
                            <div class="info-label">Location</div>
                            <div class="info-value">
                                <?php
                                $loc  = $lead['registrant_city'] ? $lead['registrant_city'].', ' : '';
                                $loc .= $lead['registrant_state'] ?: 'N/A';
                                echo htmlspecialchars($loc);
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-globe"></i></div>
                        <div class="info-content">
                            <div class="info-label">Registrar</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['domain_registrar_name'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-row date-row">
                        <div class="info-icon"><i class="fas fa-upload"></i></div>
                        <div class="info-content">
                            <div class="info-label">Uploaded Date</div>
                            <div class="info-value">
                                <?php
                                if (!empty($uploaded_at) && $uploaded_at != '0000-00-00 00:00:00' && $uploaded_at != '0000-00-00') {
                                    echo htmlspecialchars(date('d M Y', strtotime($uploaded_at)));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="info-row date-row">
                        <div class="info-icon" style="color:#f5576c;"><i class="fas fa-calendar-times"></i></div>
                        <div class="info-content">
                            <div class="info-label">Expiry Date</div>
                            <div class="info-value">
                                <?php
                                $ed = $lead['expiry_date'] ?? '';
                                if (!empty($ed) && $ed != '0000-00-00') {
                                    $days_left = (strtotime($ed) - time()) / 86400;
                                    echo htmlspecialchars(date('d M Y', strtotime($ed)));
                                    if ($days_left >= 0 && $days_left <= 90) {
                                        echo ' <span style="font-size:11px;color:'.($days_left<=30?'#f5576c':'#f7971e').';font-weight:700;">'.(int)$days_left.' days left</span>';
                                    } elseif ($days_left < 0) {
                                        echo ' <span style="font-size:11px;color:#ff416c;font-weight:700;">(Expired)</span>';
                                    }
                                } else { echo 'N/A'; }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="lead-footer">
                    <div class="action-buttons">
                        <?php if (!empty($clean_phone)): ?>
                            <a href="tel:<?php echo htmlspecialchars($clean_phone); ?>" class="btn-call">
                                <i class="fas fa-phone-alt"></i> Call
                            </a>
                            <a href="https://wa.me/<?php echo htmlspecialchars($wa_phone); ?>" target="_blank" class="btn-whatsapp">
                                <i class="fab fa-whatsapp"></i> WA
                            </a>
                        <?php endif; ?>
                        <a href="lead_details.php?id=<?php echo (int)$lead['id']; ?>" class="btn-followup">
                            <i class="fas fa-tasks"></i> Follow-up
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

    <?php else: ?>
        <div class="no-leads">
            <i class="fas fa-search" style="font-size:60px;color:#e0e6f0;margin-bottom:20px;display:block;"></i>
            <h3 style="font-size:20px;color:#1a1d2e;margin-bottom:10px;">No Leads Found</h3>
            <p style="color:var(--text-muted);">
                <strong><?php echo htmlspecialchars($user_city ?: $user_state); ?></strong> mate leads nathi.
            </p>
            <!-- ── DEBUG INFO - Remove in production ── -->
            <div class="debug-box" style="text-align:left;margin-top:20px;">
                <strong>Debug Info:</strong><br>
                Session city: "<?php echo htmlspecialchars($user_city); ?>"<br>
                Session state: "<?php echo htmlspecialchars($user_state); ?>"<br>
                Query used: <code><?php echo htmlspecialchars($query); ?></code><br><br>
                <strong>Check DB sample (city values):</strong><br>
                <?php
                $sample = $conn->query("SELECT DISTINCT registrant_city FROM domains LIMIT 10");
                if ($sample) {
                    while ($r = $sample->fetch_assoc()) {
                        echo '"' . htmlspecialchars($r['registrant_city']) . '"<br>';
                    }
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>