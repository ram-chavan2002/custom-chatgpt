<?php
error_reporting(0);
@ini_set('display_errors', 0);
session_start();

$host = 'localhost';
$username = 'srrkgfnt_lead_user';
$password = 'Admin_6666';
$database = 'srrkgfnt_lead_db';

$conn = @new mysqli($host, $username, $password, $database);
if ($conn->connect_error) die("Database connection failed.");

$success_message = '';
$error_message = '';
$upload_message = '';

// Function to convert Excel to array
function readExcelFile($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) === TRUE) {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        
        if ($xml && $worksheet) {
            $strings = simplexml_load_string($xml);
            $sheet = simplexml_load_string($worksheet);
            
            $data = array();
            foreach ($sheet->sheetData->row as $row) {
                $rowData = array();
                foreach ($row->c as $cell) {
                    $value = (string)$cell->v;
                    if (isset($cell['t']) && $cell['t'] == 's') {
                        $value = (string)$strings->si[(int)$value]->t;
                    }
                    $rowData[] = $value;
                }
                $data[] = $rowData;
            }
            return $data;
        }
    }
    return false;
}

// Handle File Upload
if(isset($_POST['upload_excel']) && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if($file['error'] == 0) {
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $successCount = 0;
        $errorCount = 0;
        
        if($fileExt == 'csv') {
            if(($handle = fopen($fileTmpName, "r")) !== FALSE) {
                fgetcsv($handle);
                while(($data = fgetcsv($handle)) !== FALSE) {
                    if(count($data) >= 13 && !empty($data[0])) {
                        $stmt = $conn->prepare("INSERT INTO domains (domain_name, create_date, expiry_date, domain_registrar_name, registrant_name, registrant_company, registrant_address, registrant_city, registrant_state, registrant_zip, registrant_country, registrant_email, registrant_phone, uploaded_at, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
                        if($stmt) {
                            $stmt->bind_param("sssssssssssss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12]);
                            if($stmt->execute()) $successCount++;
                            else $errorCount++;
                            $stmt->close();
                        }
                    }
                }
                fclose($handle);
                $upload_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Successfully imported <strong>' . $successCount . '</strong> domains!</div>';
            }
        } elseif($fileExt == 'xlsx' || $fileExt == 'xls') {
            $excelData = readExcelFile($fileTmpName);
            
            if($excelData !== false && count($excelData) > 1) {
                array_shift($excelData);
                
                foreach($excelData as $row) {
                    if(count($row) >= 13 && !empty($row[0])) {
                        $stmt = $conn->prepare("INSERT INTO domains (domain_name, create_date, expiry_date, domain_registrar_name, registrant_name, registrant_company, registrant_address, registrant_city, registrant_state, registrant_zip, registrant_country, registrant_email, registrant_phone, uploaded_at, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
                        if($stmt) {
                            $stmt->bind_param("sssssssssssss", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]);
                            if($stmt->execute()) $successCount++;
                            else $errorCount++;
                            $stmt->close();
                        }
                    }
                }
                
                $upload_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Successfully imported <strong>' . $successCount . '</strong> domains from Excel!</div>';
            } else {
                $upload_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Could not read Excel file.</div>';
            }
        }
    }
}

// Handle Create User
if(isset($_POST['create_user'])) {
    $username_input = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password_input = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    
    if(empty($username_input) || empty($email) || empty($password_input)) {
        $error_message = 'All required fields must be filled!';
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, status) VALUES (?, ?, ?, ?, 'active')");
        if($insert_stmt) {
            $insert_stmt->bind_param("ssss", $username_input, $password_input, $email, $full_name);
            if($insert_stmt->execute()) {
                $success_message = 'User created successfully!';
            } else {
                $error_message = 'Username or email already exists!';
            }
            $insert_stmt->close();
        }
    }
}

// Get statistics
$stats = array('total_domains' => 0, 'expiring_soon' => 0, 'expired' => 0, 'total_users' => 0);
$result = @$conn->query("SELECT COUNT(*) as count FROM domains");
if($result) { $row = $result->fetch_assoc(); $stats['total_domains'] = $row['count']; }
$result = @$conn->query("SELECT COUNT(*) as count FROM domains WHERE DATEDIFF(expiry_date, CURDATE()) <= 30 AND DATEDIFF(expiry_date, CURDATE()) >= 0");
if($result) { $row = $result->fetch_assoc(); $stats['expiring_soon'] = $row['count']; }
$result = @$conn->query("SELECT COUNT(*) as count FROM domains WHERE DATEDIFF(expiry_date, CURDATE()) < 0");
if($result) { $row = $result->fetch_assoc(); $stats['expired'] = $row['count']; }
$result = @$conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if($result) { $row = $result->fetch_assoc(); $stats['total_users'] = $row['count']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Domain Monitor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/[email protected]/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --primary: #7c4dff;
            --primary-dark: #651fff;
            --primary-light: #b47cff;
            --sidebar-bg: #1a1d2e;
            --sidebar-hover: #252837;
            --text-light: #e4e8f0;
            --text-muted: #9ba4b5;
            --card-bg: #ffffff;
            --shadow: 0 10px 40px rgba(0,0,0,0.08);
            --shadow-hover: 0 15px 50px rgba(0,0,0,0.12);
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-4: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ebf5 100%);
            min-height: 100vh;
            color: #2c3e50;
        }
        
        /* SIDEBAR */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: var(--sidebar-bg);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            color: white;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .brand-logo:hover {
            transform: translateX(5px);
        }
        
        .brand-logo i {
            font-size: 36px;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .brand-text {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .admin-profile {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .profile-img {
            width: 56px;
            height: 56px;
            background: var(--gradient-1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
            box-shadow: 0 8px 20px rgba(124, 77, 255, 0.3);
        }
        
        .profile-info {
            flex: 1;
            color: white;
        }
        
        .profile-info h4 {
            margin: 0 0 6px 0;
            font-size: 17px;
            font-weight: 600;
        }
        
        .profile-info span {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 24px 0;
            margin: 0;
        }
        
        .menu-item {
            margin: 6px 16px;
        }
        
        .menu-item a, .menu-item button {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 14px;
            transition: all 0.3s ease;
            font-size: 15px;
            font-weight: 500;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item a i, .menu-item button i {
            font-size: 20px;
            width: 24px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .menu-item a:hover, .menu-item button:hover {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(6px);
        }
        
        .menu-item a:hover i, .menu-item button:hover i {
            transform: scale(1.1);
        }
        
        .menu-item.active a, .menu-item.active button {
            background: var(--gradient-1);
            color: white;
            font-weight: 600;
            box-shadow: 0 6px 20px rgba(124, 77, 255, 0.4);
        }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 280px;
            padding: 36px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .page-header {
            margin-bottom: 36px;
            background: white;
            padding: 32px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #1a1d2e;
            margin: 0 0 10px 0;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 15px;
            margin: 0;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
            margin-bottom: 36px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: var(--shadow);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-1);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card.primary::before {
            background: var(--gradient-1);
        }
        
        .stat-card.warning::before {
            background: var(--gradient-2);
        }
        
        .stat-card.danger::before {
            background: var(--gradient-3);
        }
        
        .stat-card.success::before {
            background: var(--gradient-4);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-icon {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-card.primary .stat-icon {
            background: var(--gradient-1);
        }
        
        .stat-card.warning .stat-icon {
            background: var(--gradient-2);
        }
        
        .stat-card.danger .stat-icon {
            background: var(--gradient-3);
        }
        
        .stat-card.success .stat-icon {
            background: var(--gradient-4);
        }
        
        .stat-content h3 {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            margin: 0 0 12px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-number {
            font-size: 40px;
            font-weight: 800;
            color: #1a1d2e;
            letter-spacing: -1px;
        }
        
        .dashboard-tabs {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-top: 36px;
            overflow: hidden;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #f0f3f7;
            padding: 24px 24px 0;
            display: flex;
            gap: 8px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 14px 14px 0 0;
            transition: all 0.3s ease;
            font-size: 15px;
            position: relative;
        }
        
        .nav-tabs .nav-link:hover {
            background: #f8f9fc;
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 -4px 15px rgba(124, 77, 255, 0.3);
        }
        
        .tab-content {
            padding: 36px;
        }
        
        .upload-area {
            border: 3px dashed #e0e6f0;
            border-radius: 20px;
            padding: 64px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .upload-area::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(124, 77, 255, 0.05) 0%, transparent 70%);
            transition: transform 0.6s ease;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background: white;
            transform: scale(1.01);
        }
        
        .upload-area:hover::before {
            transform: scale(1.2);
        }
        
        .upload-area i {
            font-size: 72px;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 24px;
            position: relative;
        }
        
        .upload-area h3 {
            font-size: 24px;
            color: #1a1d2e;
            margin-bottom: 12px;
            font-weight: 700;
            position: relative;
        }
        
        .upload-area p {
            color: var(--text-muted);
            margin-bottom: 24px;
            font-size: 15px;
            position: relative;
        }
        
        .btn-upload {
            background: var(--gradient-1);
            color: white;
            border: none;
            padding: 14px 36px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(124, 77, 255, 0.3);
            position: relative;
        }
        
        .btn-upload:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(124, 77, 255, 0.4);
        }
        
        .btn-upload:active {
            transform: translateY(-1px);
        }
        
        .form-group {
            margin-bottom: 28px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a1d2e;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e0e6f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 77, 255, 0.1);
        }
        
        .btn-create {
            background: var(--gradient-1);
            color: white;
            border: none;
            padding: 16px 40px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(124, 77, 255, 0.3);
        }
        
        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(124, 77, 255, 0.4);
        }
        
        .alert {
            padding: 18px 24px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border: 2px solid #bee5eb;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 24px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="admin_dashboard.php" class="brand-logo">
                <i class="fas fa-globe"></i>
                <span class="brand-text">Domain Monitor</span>
            </a>
        </div>
        
        <div class="admin-profile">
            <div class="profile-img"><i class="fas fa-user-shield"></i></div>
            <div class="profile-info">
                <h4>Admin User</h4>
                <span>System Administrator</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item active">
                <a href="admin_dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="domains.php">
                    <i class="fas fa-globe"></i>
                    <span>Domains</span>
                </a>
            </li>
            <li class="menu-item">
                <button onclick="document.getElementById('upload-tab').click();">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Upload File</span>
                </button>
            </li>
            <li class="menu-item">
                <button onclick="document.getElementById('create-user-tab').click();">
                    <i class="fas fa-user-plus"></i>
                    <span>Create User</span>
                </button>
            </li>
            <li class="menu-item">
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome back! Here's what's happening with your domain portfolio today.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Total Domains</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_domains']); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-globe"></i></div>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Expiring Soon</h3>
                        <div class="stat-number"><?php echo number_format($stats['expiring_soon']); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Expired</h3>
                        <div class="stat-number"><?php echo number_format($stats['expired']); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Active Users</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-tabs">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Excel/CSV
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="create-user-tab" data-bs-toggle="tab" data-bs-target="#create-user">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="upload">
                    <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 12px; color: #1a1d2e;">
                        <i class="fas fa-file-excel" style="color: var(--primary);"></i> Upload Domain Data
                    </h3>
                    <p style="color: var(--text-muted); margin-bottom: 28px; font-size: 15px;">Upload XLSX or CSV file - automatic conversion and import included</p>
                    
                    <?php echo $upload_message; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="upload-area">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3>Drop Your File Here</h3>
                            <p>Supports XLSX, XLS, and CSV formats</p>
                            <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" required style="display:none;">
                            <button type="button" class="btn-upload" onclick="document.getElementById('excel_file').click();">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <p id="file-name" style="margin-top: 20px; font-weight: 600; color: var(--primary); font-size: 15px;"></p>
                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" name="upload_excel" class="btn-upload" style="font-size: 16px; padding: 16px 48px;">
                                <i class="fas fa-upload"></i> Upload and Import to Database
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="tab-pane fade" id="create-user">
                    <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 12px; color: #1a1d2e;">
                        <i class="fas fa-user-plus" style="color: var(--primary);"></i> Create New User
                    </h3>
                    <p style="color: var(--text-muted); margin-bottom: 28px; font-size: 15px;">Add a new user to the system with access privileges</p>
                    
                    <?php if($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Username *</label>
                                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email Address *</label>
                                    <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Password *</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Full Name</label>
                                    <input type="text" name="full_name" class="form-control" placeholder="Enter full name">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="create_user" class="btn-create">
                            <i class="fas fa-user-plus"></i> Create User Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/[email protected]/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('excel_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('file-name').textContent = fileName ? '📄 ' + fileName : '';
        });
    </script>
</body>
</html>
