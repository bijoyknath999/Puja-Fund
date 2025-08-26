<?php
// Puja Fund Installation Script
// This file should be deleted after successful installation for security

session_start();

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Check if already installed
if (file_exists('db.php') && $step == 1) {
    // Check if database has tables and admin user
    try {
        include 'db.php';
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
        if ($result && $result->fetch_assoc()['count'] > 0) {
            $step = 'complete';
        }
    } catch (Exception $e) {
        // Database not properly set up, continue with installation
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Database configuration step
        $host = trim($_POST['db_host']);
        $username = trim($_POST['db_username']);
        $password = $_POST['db_password'];
        $database = trim($_POST['db_database']);
        
        // Test database connection
        try {
            $conn = new mysqli($host, $username, $password, $database);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Store database config in session
            $_SESSION['db_config'] = [
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'database' => $database
            ];
            
            $conn->close();
            header('Location: installation.php?step=2');
            exit;
            
        } catch (Exception $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
        
    } elseif ($step == 2) {
        // Database setup step
        if (!isset($_SESSION['db_config'])) {
            header('Location: installation.php?step=1');
            exit;
        }
        
        $config = $_SESSION['db_config'];
        
        try {
            $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
            
            // Set charset for Bengali text support
            $conn->set_charset('utf8mb4');
            
            // Create tables
            $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('member', 'manager') DEFAULT 'member',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('collection', 'expense') NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT NOT NULL,
                category VARCHAR(50),
                date DATE NOT NULL,
                added_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if ($conn->multi_query($sql)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->next_result());
            }
            
            if ($conn->error) {
                throw new Exception("Error creating tables: " . $conn->error);
            }
            
            $conn->close();
            header('Location: installation.php?step=3');
            exit;
            
        } catch (Exception $e) {
            $error = "Database setup failed: " . $e->getMessage();
        }
        
    } elseif ($step == 3) {
        // Admin user creation step
        if (!isset($_SESSION['db_config'])) {
            header('Location: installation.php?step=1');
            exit;
        }
        
        $name = trim($_POST['admin_name']);
        $email = trim($_POST['admin_email']);
        $password = $_POST['admin_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $config = $_SESSION['db_config'];
            
            try {
                $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'manager')");
                $stmt->bind_param('sss', $name, $email, $hashedPassword);
                
                if ($stmt->execute()) {
                    // Update db.php file with new database configuration
                    $dbContent = "<?php
// Database configuration - Update these values for your hosting environment
\$DB_HOST = '{$config['host']}';
\$DB_USER = '{$config['username']}';
\$DB_PASS = '{$config['password']}'; // Update with your database password
\$DB_NAME = '{$config['database']}';

// Create connection
\$conn = new mysqli(\$DB_HOST, \$DB_USER, \$DB_PASS, \$DB_NAME);

// Check connection
if (\$conn->connect_error) {
    die(\"Database connection failed: \" . \$conn->connect_error);
}

// Set charset to handle Bengali text properly
\$conn->set_charset('utf8mb4');
?>";
                    
                    file_put_contents('db.php', $dbContent);
                    
                    // Clear session
                    unset($_SESSION['db_config']);
                    
                    header('Location: installation.php?step=complete');
                    exit;
                    
                } else {
                    $error = "Error creating admin user: " . $stmt->error;
                }
                
                $conn->close();
                
            } catch (Exception $e) {
                $error = "Admin creation failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t) ? $t['page_title_installation'] : 'Installation - Puja Fund'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .installation-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .step-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .step-indicator.completed {
            background: #28a745;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="installation-card p-5">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="bi bi-gem text-primary fs-1 me-2"></i>
                            <h2 class="mb-0 fw-bold">Puja Fund</h2>
                        </div>
                        <p class="text-muted">Installation & Setup</p>
                    </div>

                    <!-- Progress Steps -->
                    <div class="d-flex justify-content-center mb-4">
                        <div class="d-flex align-items-center">
                            <div class="step-indicator <?php echo $step >= 1 ? 'completed' : ''; ?>">1</div>
                            <div class="mx-2" style="width: 30px; height: 2px; background: <?php echo $step >= 2 ? '#28a745' : '#dee2e6'; ?>;"></div>
                            <div class="step-indicator <?php echo $step >= 2 ? 'completed' : ''; ?>">2</div>
                            <div class="mx-2" style="width: 30px; height: 2px; background: <?php echo $step >= 3 ? '#28a745' : '#dee2e6'; ?>;"></div>
                            <div class="step-indicator <?php echo $step >= 3 ? 'completed' : ''; ?>">3</div>
                            <div class="mx-2" style="width: 30px; height: 2px; background: <?php echo $step == 'complete' ? '#28a745' : '#dee2e6'; ?>;"></div>
                            <div class="step-indicator <?php echo $step == 'complete' ? 'completed' : ''; ?>">✓</div>
                        </div>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                    <!-- Step 1: Database Configuration -->
                    <div class="text-center mb-4">
                        <h4><i class="bi bi-database me-2"></i>Database Configuration</h4>
                        <p class="text-muted">Enter your database connection details</p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Host</label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Username</label>
                            <input type="text" class="form-control" name="db_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Password</label>
                            <input type="password" class="form-control" name="db_password">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Database Name</label>
                            <input type="text" class="form-control" name="db_database" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right me-2"></i>Test Connection & Continue
                        </button>
                    </form>

                    <?php elseif ($step == 2): ?>
                    <!-- Step 2: Database Setup -->
                    <div class="text-center mb-4">
                        <h4><i class="bi bi-gear me-2"></i>Database Setup</h4>
                        <p class="text-muted">Create database tables</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will create the necessary tables in your database.
                    </div>

                    <form method="POST">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-database-add me-2"></i>Create Database Tables
                        </button>
                    </form>

                    <?php elseif ($step == 3): ?>
                    <!-- Step 3: Admin User -->
                    <div class="text-center mb-4">
                        <h4><i class="bi bi-person-plus me-2"></i>Create Admin User</h4>
                        <p class="text-muted">Set up your administrator account</p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" name="admin_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="admin_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" class="form-control" name="admin_password" required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-2"></i>Complete Installation
                        </button>
                    </form>

                    <?php elseif ($step == 'complete'): ?>
                    <!-- Installation Complete -->
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-success mb-3">Installation Complete!</h4>
                        <p class="text-muted mb-4">Puja Fund has been successfully installed and configured.</p>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Please delete the <code>installation.php</code> file for security reasons.
                        </div>
                        
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
