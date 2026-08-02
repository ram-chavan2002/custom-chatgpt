<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: view_login.php');
    exit;
}

require_once 'db.php';

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$lead_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lead_id) {
    header('Location: view_lead1.php');
    exit;
}

$lead = null;
$res  = $conn->query("SELECT * FROM domains WHERE id = $lead_id");
if ($res && $res->num_rows > 0) {
    $lead = $res->fetch_assoc();
} else {
    header('Location: view_lead1.php');
    exit;
}

$phone       = $lead['registrant_phone'] ?? '';
$clean_phone = preg_replace('/[^0-9+]/', '', $phone);
if (strlen($clean_phone) === 10) {
    $clean_phone = '+91' . $clean_phone;
} elseif (strlen($clean_phone) === 12 && substr($clean_phone, 0, 2) === '91') {
    $clean_phone = '+' . $clean_phone;
}
$wa_phone = ltrim($clean_phone, '+');

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_call') {
        $status             = $conn->real_escape_string($_POST['call_status']        ?? 'called');
        $note               = $conn->real_escape_string(trim($_POST['call_notes']    ?? ''));
        $next_action        = $conn->real_escape_string($_POST['next_action']        ?? '');
        $scheduled_callback = $conn->real_escape_string($_POST['scheduled_callback'] ?? '');

        $conn->query("INSERT INTO call_history
            (domain_id, user_id, call_status, call_notes, next_action, scheduled_callback)
            VALUES ($lead_id, $user_id, '$status', '$note', '$next_action', '$scheduled_callback')");
        $success_msg = 'Call log saved successfully!';
    }

    if ($_POST['action'] === 'add_followup') {
        $fdate = $conn->real_escape_string($_POST['followup_date'] ?? '');
        $fnote = $conn->real_escape_string(trim($_POST['followup_note'] ?? ''));
        if (!empty($fdate)) {
            $conn->query("INSERT INTO lead_followups (domain_id, user_id, followup_date, followup_note)
                          VALUES ($lead_id, $user_id, '$fdate', '$fnote')");
            $success_msg = 'Follow-up scheduled successfully!';
        } else {
            $error_msg = 'Please select a follow-up date.';
        }
    }

    if ($_POST['action'] === 'mark_done') {
        $fid = (int)($_POST['followup_id'] ?? 0);
        if ($fid) {
            $conn->query("UPDATE lead_followups SET is_done = 1 WHERE id = $fid AND user_id = $user_id");
            $success_msg = 'Follow-up marked as done!';
        }
    }
}

$call_history = $conn->query("SELECT ch.*, a.username AS called_by
    FROM call_history ch
    LEFT JOIN admins a ON ch.user_id = a.id
    WHERE ch.domain_id = $lead_id
    ORDER BY ch.created_at DESC");

$followup_table_exists = $conn->query("SHOW TABLES LIKE 'lead_followups'");
$followups = null;
if ($followup_table_exists && $followup_table_exists->num_rows > 0) {
    $followups = $conn->query("SELECT lf.*, a.username AS set_by
        FROM lead_followups lf
        LEFT JOIN admins a ON lf.user_id = a.id
        WHERE lf.domain_id = $lead_id
        ORDER BY lf.is_done ASC, lf.followup_date ASC");
}

$expiry_date = $lead['expiry_date'] ?? '';
$days_left   = (!empty($expiry_date) && $expiry_date != '0000-00-00')
               ? (int)((strtotime($expiry_date) - time()) / 86400)
               : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Details - DomainCRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7c4dff;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-call: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-wa: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            --text-muted: #9ba4b5;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e8ebf5 100%); min-height: 100vh; }

        .topbar { background: white; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 12px rgba(0,0,0,0.07); position: sticky; top: 0; z-index: 100; }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-brand { font-size: 18px; font-weight: 800; color: #1a1d2e; display: flex; align-items: center; gap: 8px; }
        .topbar-brand i { color: var(--primary); }
        .btn-back { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: rgba(124,77,255,0.1); color: var(--primary); border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: rgba(124,77,255,0.2); }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .user-info { display: flex; align-items: center; gap: 8px; background: #f5f7fa; padding: 8px 14px; border-radius: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--gradient-1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: 700; }
        .uname { font-size: 13px; font-weight: 700; color: #1a1d2e; }

        .main-content { padding: 20px; max-width: 1100px; margin: 0 auto; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(17,153,142,0.1); color: #0b7a72; border: 1px solid rgba(17,153,142,0.3); }
        .alert-error   { background: rgba(245,87,108,0.1); color: #c0392b; border: 1px solid rgba(245,87,108,0.3); }

        .lead-profile { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 24px rgba(0,0,0,0.09); margin-bottom: 24px; border-top: 5px solid var(--primary); }
        .profile-top { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 24px; }
        .profile-avatar { width: 72px; height: 72px; background: var(--gradient-1); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; font-weight: 800; flex-shrink: 0; }
        .profile-info h2 { font-size: 22px; font-weight: 800; color: #1a1d2e; margin-bottom: 6px; }
        .profile-domain { font-size: 14px; color: var(--primary); font-weight: 600; background: rgba(124,77,255,0.1); padding: 4px 14px; border-radius: 20px; display: inline-block; margin-bottom: 8px; }
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px,1fr)); gap: 14px; }
        .pinfo-item { display: flex; align-items: center; gap: 12px; background: #f8f9fc; padding: 12px 16px; border-radius: 12px; }
        .pinfo-icon { width: 36px; height: 36px; background: rgba(124,77,255,0.1); color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .pinfo-label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .pinfo-value { font-size: 13px; font-weight: 600; color: #1a1d2e; word-break: break-all; }

        .expiry-alert { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border-radius: 12px; margin-top: 16px; font-size: 13px; font-weight: 600; }
        .expiry-danger  { background: rgba(255,65,108,0.1); color: #ff416c; border: 1px solid rgba(255,65,108,0.3); }
        .expiry-warning { background: rgba(247,151,30,0.1); color: #e67e22; border: 1px solid rgba(247,151,30,0.3); }

        .action-bar { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .btn-action { flex: 1; min-width: 130px; padding: 13px 16px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease; }
        .btn-call-big { background: var(--gradient-call); color: white; }
        .btn-wa-big   { background: var(--gradient-wa);   color: white; }
        .btn-call-big:hover, .btn-wa-big:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); color: white; }

        .section-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 15px; font-weight: 800; color: #1a1d2e; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; padding-bottom: 12px; border-bottom: 2px solid #f0f3f7; }
        .section-title i { color: var(--primary); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 16px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #1a1d2e; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 2px solid #e0e6f0; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; transition: all 0.3s; background: white; color: #1a1d2e; }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(124,77,255,0.1); }
        textarea.form-input { resize: vertical; min-height: 80px; }
        .form-full { grid-column: 1 / -1; }
        .btn-submit { border: none; padding: 11px 28px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; font-family: 'Inter', sans-serif; color: white; margin-top: 4px; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-green  { background: var(--gradient-call); }
        .btn-purple { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .s-called          { background: rgba(17,153,142,0.15);  color: #0b7a72; }
        .s-no_answer       { background: rgba(247,151,30,0.15);  color: #b7770d; }
        .s-busy            { background: rgba(255,65,108,0.15);  color: #c0392b; }
        .s-callback        { background: rgba(124,77,255,0.15);  color: var(--primary); }
        .s-interested      { background: rgba(56,239,125,0.2);   color: #0b7a72; }
        .s-not_interested  { background: rgba(200,200,200,0.3);  color: #666; }

        .na-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 20px; background: rgba(124,77,255,0.1); color: var(--primary); margin-left: 6px; }

        .timeline { display: flex; flex-direction: column; gap: 14px; }
        .timeline-item { display: flex; gap: 14px; }
        .timeline-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .ti-call     { background: rgba(17,153,142,0.15);  color: #0b7a72; }
        .ti-followup { background: rgba(124,77,255,0.15);  color: var(--primary); }
        .ti-done     { background: rgba(200,200,200,0.3);  color: #999; }
        .timeline-body { flex: 1; background: #f8f9fc; border-radius: 12px; padding: 12px 16px; }
        .tl-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .tl-title { font-size: 13px; font-weight: 700; color: #1a1d2e; display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
        .tl-date  { font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }
        .tl-note  { font-size: 13px; color: #4a5568; margin-top: 4px; line-height: 1.5; }
        .tl-by    { font-size: 11px; color: var(--text-muted); margin-top: 8px; }
        .tl-callback { font-size: 12px; color: #e67e22; font-weight: 600; margin-top: 5px; }

        .btn-done { background: rgba(17,153,142,0.1); color: #0b7a72; border: 1px solid rgba(17,153,142,0.3); padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        .btn-done:hover { background: rgba(17,153,142,0.2); }
        .done-badge { display: inline-flex; align-items: center; gap: 4px; background: rgba(200,200,200,0.3); color: #888; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; }

        .empty-state { text-align: center; padding: 30px; color: var(--text-muted); font-size: 14px; }
        .empty-state i { font-size: 36px; display: block; margin-bottom: 10px; opacity: 0.4; }

        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .profile-top  { flex-direction: column; }
            .action-bar   { flex-direction: column; }
            .form-grid    { grid-template-columns: 1fr; }
            .tl-top       { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <a href="view_lead1.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Leads</a>
        <div class="topbar-brand"><i class="fas fa-globe"></i> DomainCRM</div>
    </div>
    <div class="topbar-right">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <span class="uname"><?php echo htmlspecialchars($user_name); ?></span>
        </div>
    </div>
</div>

<div class="main-content">

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- LEAD PROFILE -->
    <div class="lead-profile">
        <div class="profile-top">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($lead['registrant_name'] ?: 'N', 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($lead['registrant_name'] ?: 'N/A'); ?></h2>
                <div class="profile-domain"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($lead['domain_name']); ?></div>
            </div>
        </div>

        <div class="profile-grid">
            <div class="pinfo-item">
                <div class="pinfo-icon"><i class="fas fa-phone-alt"></i></div>
                <div>
                    <div class="pinfo-label">Phone</div>
                    <div class="pinfo-value"><?php echo htmlspecialchars($clean_phone ?: 'N/A'); ?></div>
                </div>
            </div>
            <div class="pinfo-item">
                <div class="pinfo-icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="pinfo-label">Email</div>
                    <div class="pinfo-value"><?php echo htmlspecialchars($lead['registrant_email'] ?: 'N/A'); ?></div>
                </div>
            </div>
            <div class="pinfo-item">
                <div class="pinfo-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <div class="pinfo-label">Location</div>
                    <div class="pinfo-value">
                        <?php
                        $loc  = $lead['registrant_city'] ? $lead['registrant_city'].', ' : '';
                        $loc .= $lead['registrant_state'] ?: 'N/A';
                        echo htmlspecialchars($loc);
                        ?>
                    </div>
                </div>
            </div>
            <div class="pinfo-item">
                <div class="pinfo-icon"><i class="fas fa-building"></i></div>
                <div>
                    <div class="pinfo-label">Registrar</div>
                    <div class="pinfo-value"><?php echo htmlspecialchars($lead['domain_registrar_name'] ?: 'N/A'); ?></div>
                </div>
            </div>
            <div class="pinfo-item">
                <div class="pinfo-icon"><i class="fas fa-upload"></i></div>
                <div>
                    <div class="pinfo-label">Uploaded Date</div>
                    <div class="pinfo-value">
                        <?php
                        $ua = $lead['uploaded_at'] ?? '';
                        echo (!empty($ua) && $ua != '0000-00-00 00:00:00')
                            ? date('d M Y', strtotime($ua)) : 'N/A';
                        ?>
                    </div>
                </div>
            </div>
            <div class="pinfo-item">
                <div class="pinfo-icon" style="color:#f5576c;background:rgba(245,87,108,0.1);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div>
                    <div class="pinfo-label">Expiry Date</div>
                    <div class="pinfo-value" style="color:<?php echo ($days_left !== null && $days_left <= 30) ? '#f5576c' : '#1a1d2e'; ?>">
                        <?php
                        if (!empty($expiry_date) && $expiry_date != '0000-00-00')
                            echo date('d M Y', strtotime($expiry_date));
                        else echo 'N/A';
                        ?>
                        <?php if ($days_left !== null && $days_left >= 0 && $days_left <= 90): ?>
                            <span style="font-size:11px;font-weight:700;"> (<?php echo $days_left; ?> days left)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($days_left !== null): ?>
            <?php if ($days_left < 0): ?>
                <div class="expiry-alert expiry-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Domain expired <?php echo abs($days_left); ?> days ago! Contact immediately.
                </div>
            <?php elseif ($days_left <= 60): ?>
                <div class="expiry-alert expiry-warning">
                    <i class="fas fa-clock"></i>
                    Domain expires in <?php echo $days_left; ?> days — Offer renewal now!
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- QUICK ACTION BUTTONS -->
    <?php if (!empty($clean_phone)): ?>
    <div class="action-bar">
        <a href="tel:<?php echo htmlspecialchars($clean_phone); ?>" class="btn-action btn-call-big">
            <i class="fas fa-phone-alt"></i> Call Now: <?php echo htmlspecialchars($clean_phone); ?>
        </a>
        <a href="https://wa.me/<?php echo htmlspecialchars($wa_phone); ?>" target="_blank" class="btn-action btn-wa-big">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
    </div>
    <?php endif; ?>

    <!-- LOG A CALL -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-phone-volume"></i> Log a Call</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_call">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Call Status</label>
                    <select name="call_status" class="form-input" required>
                        <option value="called">Called – Connected</option>
                        <option value="interested">Interested in Renewal</option>
                        <option value="callback">Call Back Later</option>
                        <option value="no_answer">No Answer</option>
                        <option value="busy">Busy</option>
                        <option value="not_interested">Not Interested</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-forward"></i> Next Action</label>
                    <select name="next_action" class="form-input">
                        <option value="">-- Select Next Action --</option>
                        <option value="call_again">Call Again</option>
                        <option value="send_quote">Send Renewal Quote</option>
                        <option value="whatsapp">Send WhatsApp</option>
                        <option value="close_won">Close – Won</option>
                        <option value="close_lost">Close – Lost</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Scheduled Callback</label>
                    <input type="datetime-local" name="scheduled_callback" class="form-input"
                           min="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                <div class="form-group form-full">
                    <label><i class="fas fa-sticky-note"></i> Call Notes</label>
                    <textarea name="call_notes" class="form-input"
                              placeholder="Write what was discussed in this call..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn-submit btn-green">
                <i class="fas fa-save"></i> Save Call Log
            </button>
        </form>
    </div>

    <!-- SCHEDULE FOLLOW-UP -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-calendar-plus"></i> Schedule Follow-up</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_followup">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Follow-up Date</label>
                    <input type="date" name="followup_date" class="form-input"
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-notes-medical"></i> Follow-up Note</label>
                    <input type="text" name="followup_note" class="form-input"
                           placeholder="Enter reminder note or reason...">
                </div>
            </div>
            <button type="submit" class="btn-submit btn-purple">
                <i class="fas fa-calendar-check"></i> Schedule Follow-up
            </button>
        </form>
    </div>

    <!-- CALL HISTORY -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-history"></i> Call History
            <?php if ($call_history): ?>
                <span style="background:rgba(124,77,255,0.1);color:var(--primary);font-size:12px;padding:2px 10px;border-radius:20px;margin-left:auto;">
                    <?php echo $call_history->num_rows; ?> Total Calls
                </span>
            <?php endif; ?>
        </div>
        <?php if ($call_history && $call_history->num_rows > 0): ?>
            <div class="timeline">
            <?php while ($ch = $call_history->fetch_assoc()):
                $status_labels = [
                    'called'         => 'Called – Connected',
                    'no_answer'      => 'No Answer',
                    'busy'           => 'Busy',
                    'callback'       => 'Call Back Later',
                    'interested'     => 'Interested',
                    'not_interested' => 'Not Interested',
                ];
                $na_labels = [
                    'call_again'  => 'Call Again',
                    'send_quote'  => 'Send Quote',
                    'whatsapp'    => 'Send WhatsApp',
                    'close_won'   => 'Won',
                    'close_lost'  => 'Lost',
                ];
                $label    = $status_labels[$ch['call_status']] ?? $ch['call_status'];
                $na_label = !empty($ch['next_action']) ? ($na_labels[$ch['next_action']] ?? $ch['next_action']) : '';
            ?>
                <div class="timeline-item">
                    <div class="timeline-icon ti-call"><i class="fas fa-phone-alt"></i></div>
                    <div class="timeline-body">
                        <div class="tl-top">
                            <span class="tl-title">
                                <span class="status-badge s-<?php echo htmlspecialchars($ch['call_status']); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </span>
                                <?php if ($na_label): ?>
                                    <span class="na-badge">
                                        <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($na_label); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="tl-date">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d M Y, h:i A', strtotime($ch['created_at'])); ?>
                            </span>
                        </div>
                        <?php if (!empty($ch['call_notes'])): ?>
                            <div class="tl-note"><?php echo nl2br(htmlspecialchars($ch['call_notes'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ch['scheduled_callback']) && $ch['scheduled_callback'] != '0000-00-00 00:00:00'): ?>
                            <div class="tl-callback">
                                <i class="fas fa-calendar-alt"></i>
                                Callback scheduled: <?php echo date('d M Y, h:i A', strtotime($ch['scheduled_callback'])); ?>
                            </div>
                        <?php endif; ?>
                        <div class="tl-by">
                            <i class="fas fa-user"></i> Logged by: <?php echo htmlspecialchars($ch['called_by'] ?? 'You'); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-phone-slash"></i>
                No call logs found. Add your first call log above.
            </div>
        <?php endif; ?>
    </div>

    <!-- FOLLOW-UP SCHEDULE -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-tasks"></i> Follow-up Schedule</div>
        <?php if ($followups && $followups->num_rows > 0): ?>
            <div class="timeline">
            <?php while ($fu = $followups->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $fu['is_done'] ? 'ti-done' : 'ti-followup'; ?>">
                        <i class="fas <?php echo $fu['is_done'] ? 'fa-check-double' : 'fa-bell'; ?>"></i>
                    </div>
                    <div class="timeline-body" style="<?php echo $fu['is_done'] ? 'opacity:0.55;' : ''; ?>">
                        <div class="tl-top">
                            <span class="tl-title">
                                Follow-up on: <?php echo date('d M Y', strtotime($fu['followup_date'])); ?>
                                <?php
                                if (!$fu['is_done']) {
                                    $df = (int)((strtotime($fu['followup_date']) - time()) / 86400);
                                    if ($df < 0)
                                        echo '<span style="color:#f5576c;font-size:11px;font-weight:700;"> — Overdue!</span>';
                                    elseif ($df == 0)
                                        echo '<span style="color:#f7971e;font-size:11px;font-weight:700;"> — Today!</span>';
                                    else
                                        echo '<span style="color:#0b7a72;font-size:11px;"> — In '.$df.' day(s)</span>';
                                }
                                ?>
                            </span>
                            <?php if ($fu['is_done']): ?>
                                <span class="done-badge"><i class="fas fa-check"></i> Completed</span>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_done">
                                    <input type="hidden" name="followup_id" value="<?php echo (int)$fu['id']; ?>">
                                    <button type="submit" class="btn-done">
                                        <i class="fas fa-check"></i> Mark as Done
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($fu['followup_note'])): ?>
                            <div class="tl-note"><?php echo nl2br(htmlspecialchars($fu['followup_note'])); ?></div>
                        <?php endif; ?>
                        <div class="tl-by">
                            <i class="fas fa-user"></i> Set by: <?php echo htmlspecialchars($fu['set_by'] ?? 'You'); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                No follow-ups scheduled. Add one above.
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
