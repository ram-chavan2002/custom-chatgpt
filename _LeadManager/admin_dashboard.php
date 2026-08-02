<?php
session_start();
include('db.php');

$success_message = '';
$error_message = '';

// Handle Create User Form
if(isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    
    if(empty($username) || empty($email) || empty($password)) {
        $error_message = 'All fields are required!';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format!';
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error_message = 'Username or email already exists!';
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, status) VALUES (?, ?, ?, ?, 'active')");
            $insert_stmt->bind_param("ssss", $username, $password, $email, $full_name);
            
            if($insert_stmt->execute()) {
                $success_message = 'User created successfully!';
                $_POST = array();
            } else {
                $error_message = 'Error creating user: ' . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM domains");
$stats['total_domains'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM domains WHERE DATEDIFF(expiry_date, CURDATE()) <= 30 AND DATEDIFF(expiry_date, CURDATE()) >= 0");
$stats['expiring_soon'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM domains WHERE DATEDIFF(expiry_date, CURDATE()) < 0");
$stats['expired'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$stats['total_users'] = $result->fetch_assoc()['count'];
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card.primary::before {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.warning::before {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.danger::before {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .stat-card.success::before {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.danger .stat-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .dashboard-tabs {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #ecf0f1;
            padding: 20px 20px 0 20px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #7f8c8d;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .tab-content {
            padding: 30px;
        }
        
        .upload-area {
            border: 3px dashed #ecf0f1;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: white;
        }
        
        .upload-area i {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .upload-area h3 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include('nav.php'); ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Manage domains, users, and uploads from one place</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Total Domains</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_domains']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Expiring Soon</h3>
                        <div class="stat-number"><?php echo number_format($stats['expiring_soon']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Expired</h3>
                        <div class="stat-number"><?php echo number_format($stats['expired']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3>Active Users</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-tabs">
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button">
                        <i class="fas fa-upload"></i> Upload Excel
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="domains-tab" data-bs-toggle="tab" data-bs-target="#domains" type="button">
                        <i class="fas fa-list"></i> View Domains
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="create-user-tab" data-bs-toggle="tab" data-bs-target="#create-user" type="button">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="dashboardTabsContent">
                <div class="tab-pane fade show active" id="upload" role="tabpanel">
                    <h3><i class="fas fa-file-excel"></i> Upload Excel File</h3>
                    <p class="text-muted mb-4">Upload your domain data in Excel format (.xlsx, .xls)</p>
                    
                    <form action="process_upload.php" method="POST" enctype="multipart/form-data">
                        <div class="upload-area">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3>Drag & Drop Excel File</h3>
                            <p>or click to browse</p>
                            <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" required style="display: none;">
                            <button type="button" class="btn-upload" onclick="document.getElementById('excel_file').click();">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <p id="file-name" style="margin-top: 15px; font-weight: 600; color: #667eea;"></p>
                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn-upload">
                                <i class="fas fa-upload"></i> Upload and Process
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="tab-pane fade" id="domains" role="tabpanel">
                    <h3><i class="fas fa-globe"></i> All Domains</h3>
                    <p class="text-muted mb-4">View and manage all registered domains</p>
                    <div class="text-center">
                        <a href="domains.php" class="btn-upload">
                            <i class="fas fa-arrow-right"></i> Go to Domains Page
                        </a>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="create-user" role="tabpanel">
                    <h3><i class="fas fa-user-plus"></i> Create New User</h3>
                    <p class="text-muted mb-4">Add a new user to the system</p>
                    
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
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Username *</label>
                                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email *</label>
                                    <input type="email" name="email" class="form-control" placeholder="Enter email" required>
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
                            <i class="fas fa-user-plus"></i> Create User
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
