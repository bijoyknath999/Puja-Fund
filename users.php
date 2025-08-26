<?php
include 'auth.php';
include 'db.php';
include 'lang.php';

// Check if user is manager
if($_SESSION['user']['role'] != 'manager') {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

// Handle user actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add_user':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                if($name && $email && $password) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    if($stmt->execute([$name, $email, $hashedPassword, $role])) {
                        $message = "User added successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Error adding user. Email might already exist.";
                        $messageType = "error";
                    }
                } else {
                    $message = "Please fill all required fields.";
                    $messageType = "error";
                }
                break;
                
            case 'update_role':
                $userId = $_POST['user_id'];
                $newRole = $_POST['new_role'];
                
                if($userId != $_SESSION['user']['id']) { // Can't change own role
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    if($stmt->execute([$newRole, $userId])) {
                        $message = "User role updated successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Error updating user role.";
                        $messageType = "error";
                    }
                } else {
                    $message = "You cannot change your own role.";
                    $messageType = "error";
                }
                break;
                
            case 'delete_user':
                $userId = $_POST['user_id'];
                
                if($userId != $_SESSION['user']['id']) { // Can't delete own account
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    if($stmt->execute([$userId])) {
                        $message = "User deleted successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Error deleting user.";
                        $messageType = "error";
                    }
                } else {
                    $message = "You cannot delete your own account.";
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get filter parameters
$selectedYear = $_GET['year'] ?? date('Y');
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

// Get available years for dropdown
$yearsStmt = $conn->prepare("SELECT DISTINCT YEAR(date) as year FROM transactions ORDER BY year DESC");
$yearsStmt->execute();
$years = $yearsStmt->get_result();

// Build date filter conditions
$dateCondition = "";
$dateParams = [];

if ($from_date && $to_date) {
    $dateCondition = "t.date >= ? AND t.date <= ?";
    $dateParams = [$from_date, $to_date, $from_date, $to_date, $from_date, $to_date, $from_date, $to_date, $from_date, $to_date];
} elseif ($from_date) {
    $dateCondition = "t.date >= ?";
    $dateParams = [$from_date, $from_date, $from_date, $from_date, $from_date];
} elseif ($to_date) {
    $dateCondition = "t.date <= ?";
    $dateParams = [$to_date, $to_date, $to_date, $to_date, $to_date];
} else {
    $dateCondition = "YEAR(t.date) = ?";
    $dateParams = [$selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear];
}

// Get all users with their transaction counts and balance calculations for selected period
$usersStmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           COALESCE(stats.transaction_count, 0) as transaction_count,
           COALESCE(stats.total_collections, 0) as total_collections,
           COALESCE(stats.total_expenses, 0) as total_expenses,
           COALESCE(stats.transfer_in, 0) as transfer_in,
           COALESCE(stats.transfer_out, 0) as transfer_out
    FROM users u 
    LEFT JOIN (
        SELECT 
            t.added_by,
            COUNT(CASE WHEN t.type != 'transfer' AND $dateCondition THEN t.id END) as transaction_count,
            SUM(CASE WHEN t.type = 'collection' AND $dateCondition THEN t.amount ELSE 0 END) as total_collections,
            SUM(CASE WHEN t.type = 'expense' AND $dateCondition THEN t.amount ELSE 0 END) as total_expenses,
            SUM(CASE WHEN t.type = 'transfer' AND t.description LIKE '%Transfer from%' AND $dateCondition THEN t.amount ELSE 0 END) as transfer_in,
            SUM(CASE WHEN t.type = 'transfer' AND t.description LIKE '%Transfer to%' AND $dateCondition THEN t.amount ELSE 0 END) as transfer_out
        FROM transactions t
        GROUP BY t.added_by
    ) stats ON u.id = stats.added_by
    ORDER BY u.created_at DESC
");
$usersStmt->execute($dateParams);
$users = $usersStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['page_title_users']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .language-switcher {
            display: flex;
            align-items: center;
            margin-right: 1rem;
        }

        .lang-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 0.2rem;
            backdrop-filter: blur(10px);
        }

        .lang-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
        }

        .lang-btn.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .bangla-text {
            font-family: 'SolaimanLipi', 'Kalpurush', 'Nikosh', Arial, sans-serif;
        }

        @media (max-width: 768px) {
            .language-switcher {
                margin-right: 0.5rem;
            }
            
            .lang-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
                margin: 0 0.1rem;
            }
        }
        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
        }
        .dropdown-toggle::after {
            border-top-color: rgba(255, 255, 255, 0.8);
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <i class="bi bi-gem me-2 fs-4"></i>
      <span class="<?php echo getLangClass($lang); ?>"><?php echo $t['app_name']; ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="index.php"><i class="bi bi-house me-1"></i><?php echo $t['dashboard']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="transactions.php"><i class="bi bi-list-ul me-1"></i><?php echo $t['transactions']; ?></a>
        </li>
        <?php if($_SESSION['user']['role'] == 'manager'): ?>
        <li class="nav-item">
          <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="users.php"><i class="bi bi-people me-1"></i><?php echo $t['users']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="approve_transfers.php">
            <i class="bi bi-check-circle me-1"></i><?php echo $t['approve_transfers']; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="report.php"><i class="bi bi-file-earmark-text me-1"></i><?php echo $t['reports']; ?></a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <!-- Language Switcher -->
        <li class="nav-item">
          <?php echo getLanguageSwitcher($lang); ?>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i>
            <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i><?php echo $t['profile']; ?></a></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i><?php echo $t['logout']; ?></a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container py-4">
  <!-- Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="d-flex align-items-center">
                <div class="me-3">
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary" style="width: 50px; height: 50px;">
                    <i class="bi bi-people-fill text-white" style="font-size: 1.2rem;"></i>
                  </div>
                </div>
                <div>
                  <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['users']; ?></h4>
                  <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>">Manage members and access control</p>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button class="btn btn-outline-primary <?php echo getLangClass($lang); ?>" data-bs-toggle="collapse" data-bs-target="#filterSection">
                  <i class="bi bi-funnel me-2"></i><?php echo $t['filter']; ?>
                </button>
                <button class="btn btn-primary <?php echo getLangClass($lang); ?>" data-bs-toggle="modal" data-bs-target="#addUserModal">
                  <i class="bi bi-person-plus me-2"></i><?php echo $t['add_user']; ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="collapse" id="filterSection">
        <div class="card">
          <div class="card-body">
            <form method="GET" action="users.php">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="from" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar me-1"></i><?php echo $t['from_date']; ?>
                  </label>
                  <input type="date" class="form-control" id="from" name="from" value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-3">
                  <label for="to" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar me-1"></i><?php echo $t['to_date']; ?>
                  </label>
                  <input type="date" class="form-control" id="to" name="to" value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-3">
                  <label for="year" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar3 me-1"></i><?php echo $t['year']; ?>
                  </label>
                  <select class="form-select" id="year" name="year">
                    <?php 
                      $years->data_seek(0); // Reset result pointer
                      while($yearRow = $years->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $yearRow['year']; ?>" <?php echo $selectedYear == $yearRow['year'] ? 'selected' : ''; ?>>
                      <?php echo $yearRow['year']; ?>
                    </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-search me-2"></i><?php echo $t['apply_filter']; ?>
                  </button>
                  <a href="users.php" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-arrow-clockwise me-2"></i><?php echo $t['reset']; ?>
                  </a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Summary -->
  <div class="row mb-2">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0 <?php echo getLangClass($lang); ?>">
          <i class="bi bi-people me-2"></i>User Financial Summary
        </h5>
        <span class="badge bg-primary">
          <?php if ($from_date || $to_date): ?>
            <i class="bi bi-calendar-range me-1"></i>
            <?php 
              if ($from_date && $to_date) {
                echo date('M j, Y', strtotime($from_date)) . ' - ' . date('M j, Y', strtotime($to_date));
              } elseif ($from_date) {
                echo 'From ' . date('M j, Y', strtotime($from_date));
              } elseif ($to_date) {
                echo 'Until ' . date('M j, Y', strtotime($to_date));
              }
            ?>
          <?php else: ?>
            <i class="bi bi-calendar3 me-1"></i><?php echo $selectedYear; ?>
          <?php endif; ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Messages -->
  <?php if($message): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?>">
        <i class="bi bi-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Users Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 <?php echo getLangClass($lang); ?>">
            <i class="bi bi-list me-2"></i>
            <?php echo $t['users']; ?>
          </h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
              <thead>
                <tr>
                  <th class="<?php echo getLangClass($lang); ?>">User</th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['email_address']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['role']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['transactions']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['collection']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['expense']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>">Balance</th>
                  <th class="<?php echo getLangClass($lang); ?>">Joined</th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['actions']; ?></th>
                </tr>
              </thead>
              <tbody>
                <?php while($user = $users->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                      </div>
                      <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($user['name']); ?></div>
                        <?php if($user['id'] == $_SESSION['user']['id']): ?>
                        <small class="text-muted">(You)</small>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td>
                    <span class="badge <?php echo $user['role'] == 'manager' ? 'bg-primary' : 'bg-secondary'; ?> <?php echo getLangClass($lang); ?>">
                      <i class="bi bi-<?php echo $user['role'] == 'manager' ? 'shield-check' : 'person'; ?> me-1"></i>
                      <?php echo $user['role'] == 'manager' ? $t['manager'] : $t['member']; ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-info"><?php echo $user['transaction_count']; ?></span>
                  </td>
                  <td class="text-success fw-semibold">৳<?php echo number_format($user['total_collections'], 2); ?></td>
                  <td class="text-danger fw-semibold">৳<?php echo number_format($user['total_expenses'], 2); ?></td>
                  <?php 
                    // Debug: Show individual components
                    $collections = floatval($user['total_collections']);
                    $expenses = floatval($user['total_expenses']);
                    $transfer_in = floatval($user['transfer_in']);
                    $transfer_out = floatval($user['transfer_out']);
                    
                    // Individual balance: Collections + Transfers In - Transfers Out - Own Expenses
                    $user_balance = $collections + $transfer_in - $transfer_out - $expenses;
                    $balance_class = $user_balance >= 0 ? 'text-success' : 'text-danger';
                    
                    // Debug output (remove after testing)
                    // echo "<!-- User {$user['name']}: C=$collections, E=$expenses, TI=$transfer_in, TO=$transfer_out, Balance=$user_balance -->";
                  ?>
                  <td class="<?php echo $balance_class; ?> fw-semibold">৳<?php echo number_format($user_balance, 2); ?></td>
                  <td>
                  <small class="text-muted">
                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                  </small>
                </td>
                <td>
                  <?php if($user['id'] != $_SESSION['user']['id']): ?>
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="changeRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')">
                      <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                  <?php else: ?>
                  <small class="text-muted">Current user</small>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title <?php echo getLangClass($lang); ?>">
          <i class="bi bi-person-plus me-2"></i>
          <?php echo $t['add_user']; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_user">
          
          <div class="mb-3">
            <label for="name" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
              <i class="bi bi-person me-2"></i><?php echo $t['full_name']; ?>
            </label>
            <input type="text" class="form-control" id="name" name="name" placeholder="<?php echo $t['name_placeholder']; ?>" required>
          </div>
          
          <div class="mb-3">
            <label for="email" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
              <i class="bi bi-envelope me-2"></i><?php echo $t['email_address']; ?>
            </label>
            <input type="email" class="form-control" id="email" name="email" placeholder="<?php echo $t['email_address']; ?>" required>
          </div>
          
          <div class="mb-3">
            <label for="password" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
              <i class="bi bi-lock me-2"></i><?php echo $t['password']; ?>
            </label>
            <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $t['password_placeholder']; ?>" required minlength="6">
            <div class="form-text">Minimum 6 characters</div>
          </div>
          
          <div class="mb-3">
            <label for="role" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
              <i class="bi bi-shield me-2"></i><?php echo $t['role']; ?>
            </label>
            <select class="form-select" id="role" name="role" required>
              <option value="member"><?php echo $t['member']; ?></option>
              <option value="manager"><?php echo $t['manager']; ?></option>
            </select>
            <div class="form-text">
              Members can add transactions. Managers can also manage users.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>" data-bs-dismiss="modal"><?php echo $t['cancel']; ?></button>
          <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>">
            <i class="bi bi-person-plus me-2"></i><?php echo $t['add_user_btn']; ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-arrow-repeat me-2"></i>
          Change User Role
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="changeRoleForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="update_role">
          <input type="hidden" name="user_id" id="changeRoleUserId">
          
          <p>Change role for this user:</p>
          
          <div class="mb-3">
            <label for="new_role" class="form-label fw-semibold">
              <i class="bi bi-shield me-2"></i>New Role
            </label>
            <select class="form-select" id="new_role" name="new_role" required>
              <option value="member">Member</option>
              <option value="manager">Manager</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check me-2"></i>Update Role
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Delete User
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="deleteUserForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="delete_user">
          <input type="hidden" name="user_id" id="deleteUserId">
          
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Warning!</strong> This action cannot be undone.
          </div>
          
          <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
          <p class="text-muted small">All transactions added by this user will remain, but they will be orphaned.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-2"></i>Delete User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function changeRole(userId, currentRole) {
  document.getElementById('changeRoleUserId').value = userId;
  document.getElementById('new_role').value = currentRole === 'manager' ? 'member' : 'manager';
  new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
}

function deleteUser(userId, userName) {
  document.getElementById('deleteUserId').value = userId;
  document.getElementById('deleteUserName').textContent = userName;
  new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>
</body>
</html>
