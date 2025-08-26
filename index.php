<?php
include 'auth.php';
include 'db.php';
include 'lang.php';
include 'categories.php';

$lang = getCurrentLanguage();
$t = getTranslations($lang);

// Handle year filtering
$selectedYear = $_GET['year'] ?? date('Y');

// Handle transaction form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $category = $_POST['category'] ?? null;
    
    if ($type == 'transfer') {
        if (!isset($_POST['transfer_user_id']) || empty($_POST['transfer_user_id'])) {
            $_SESSION['error'] = 'Please select a user for transfer';
            header('Location: index.php');
            exit();
        }
        
        // Handle transfer transaction
        $transfer_user_id = intval($_POST['transfer_user_id']);
        $user_id = $_SESSION['user']['id'];
        
        try {
            // Record in transfers table with 'pending' status - requires manager approval
            $stmt_transfer = $conn->prepare("INSERT INTO transfers (from_user_id, to_user_id, amount, description, transfer_date, status, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt_transfer->bind_param('iidssi', $user_id, $transfer_user_id, $amount, $description, $date, $user_id);
            $stmt_transfer->execute();
            
            $_SESSION['success'] = 'Transfer request submitted for manager approval';
        } catch (Exception $e) {
            $_SESSION['error'] = $t['transfer_failed'] . ": " . $e->getMessage();
        }
    } else {
        // Check balance for expense transactions
        if ($type == 'expense') {
            // Get user's current balance
            $user_id = $_SESSION['user']['id'];
            $balanceStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) as collections,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses,
                    COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) as transfer_in,
                    COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as transfer_out
                FROM transactions 
                WHERE added_by = ?
            ");
            $balanceStmt->bind_param('i', $user_id);
            $balanceStmt->execute();
            $balanceData = $balanceStmt->get_result()->fetch_assoc();
            
            $current_balance = $balanceData['collections'] + $balanceData['transfer_in'] - $balanceData['transfer_out'] - $balanceData['expenses'];
            
            if ($current_balance < $amount) {
                $_SESSION['error'] = "Insufficient balance! Your current balance is ৳" . number_format($current_balance, 2) . " but you're trying to add an expense of ৳" . number_format($amount, 2);
                header('Location: index.php');
                exit();
            }
        }
        
        // Handle regular transaction
        $stmt = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdssi', $type, $description, $amount, $date, $category, $_SESSION['user']['id']);
        $stmt->execute();
    }
    
    header('Location: index.php');
    exit();
}

// Helper function to get user name
function getUserName($user_id, $conn) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['name'] : 'Unknown User';
    exit;
}

// Get financial summary with year filtering (TOTAL FUND BALANCE - ALL USERS)
$totColStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='collection' AND YEAR(date) = ?");
$totColStmt->bind_param('i', $selectedYear);
$totColStmt->execute();
$totCol = $totColStmt->get_result()->fetch_assoc()['total'];

$totExpStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='expense' AND YEAR(date) = ?");
$totExpStmt->bind_param('i', $selectedYear);
$totExpStmt->execute();
$totExp = $totExpStmt->get_result()->fetch_assoc()['total'];

// Calculate balance: Collections - Expenses (transfers cancel out in total fund calculation)
$balance = $totCol - $totExp;


// Get recent transactions with year filtering (all users for managers, user-specific for members)
// For transfers, only show outgoing transfers to avoid showing duplicates
if ($_SESSION['user']['role'] == 'manager') {
    $recentStmt = $conn->prepare("
        SELECT t.*, u.name as added_by_name,
               CASE 
                   WHEN t.type = 'transfer' AND t.description LIKE '%Transfer to%' THEN 
                       CONCAT('Transfer: ', u.name, ' → ', 
                              SUBSTRING(t.description, LOCATE('Transfer to ', t.description) + 12, 
                                       LOCATE(' : ', t.description) - LOCATE('Transfer to ', t.description) - 12))
                   ELSE t.description 
               END as display_description
        FROM transactions t 
        JOIN users u ON t.added_by = u.id 
        WHERE YEAR(t.date) = ? 
        AND (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $recentStmt->bind_param('i', $selectedYear);
} else {
    $recentStmt = $conn->prepare("
        SELECT t.*, u.name as added_by_name,
               CASE 
                   WHEN t.type = 'transfer' AND t.description LIKE '%Transfer to%' THEN 
                       CONCAT('Transfer: ', u.name, ' → ', 
                              SUBSTRING(t.description, LOCATE('Transfer to ', t.description) + 12, 
                                       LOCATE(' : ', t.description) - LOCATE('Transfer to ', t.description) - 12))
                   ELSE t.description 
               END as display_description
        FROM transactions t 
        JOIN users u ON t.added_by = u.id 
        WHERE t.added_by = ? AND YEAR(t.date) = ? 
        AND (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $recentStmt->bind_param('ii', $_SESSION['user']['id'], $selectedYear);
}
$recentStmt->execute();
$recentTransactions = $recentStmt->get_result();

// Get available years for filter dropdown
$yearsStmt = $conn->prepare("SELECT DISTINCT YEAR(date) as year FROM transactions ORDER BY year DESC");
$yearsStmt->execute();
$availableYears = $yearsStmt->get_result();

// Get user count
$userCountStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$userCountStmt->execute();
$userCount = $userCountStmt->get_result()->fetch_assoc()['total'];

// Get transaction count
$transactionCountStmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions");
$transactionCountStmt->execute();
$transactionCount = $transactionCountStmt->get_result()->fetch_assoc()['total'];

// Get today's transactions
$todayStmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE DATE(created_at) = CURDATE()");
$todayStmt->execute();
$todayCount = $todayStmt->get_result()->fetch_assoc()['total'];

// Get this month's data
$monthlyStmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN type='collection' THEN amount ELSE 0 END) as month_collections,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as month_expenses
    FROM transactions 
    WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
");
$monthlyStmt->execute();
$monthlyData = $monthlyStmt->get_result()->fetch_assoc();

$progress = $totCol > 0 ? min(100, ($totCol / max($totCol, 10000)) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title><?php echo $t['page_title_dashboard']; ?></title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }
    .main-content {
      background: #f8f9fa;
      min-height: calc(100vh - 76px);
      margin-top: 76px;
      border-radius: 20px 20px 0 0;
    }
    .navbar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border: none;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }
    .navbar-brand, .nav-link {
      color: white !important;
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    .stat-card {
      background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: var(--accent-color);
    }
    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }
    .welcome-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 20px;
      margin: 0 0 30px 0;
      padding: 30px 20px;
      position: relative;
      overflow: hidden;
    }
    .welcome-section::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 200px;
      height: 200px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
    }
    
    @media (max-width: 767.98px) {
      .welcome-section {
        text-align: center;
        padding: 20px 15px;
      }
      .welcome-section h1 {
        font-size: 1.8rem !important;
      }
      .welcome-section .lead {
        font-size: 1rem;
      }
    }
    
    @media (max-width: 575.98px) {
      .welcome-section {
        padding: 15px 10px;
      }
      .welcome-section h1 {
        font-size: 1.5rem !important;
      }
    }
    .chart-container {
      height: 300px;
      position: relative;
    }
    .progress-ring {
      width: 120px;
      height: 120px;
    }
    .table-modern {
      border-radius: 12px;
      overflow: hidden;
    }
    .table-modern th {
      background: #f8f9fa;
      border: none;
      font-weight: 600;
      color: #495057;
    }
    .table-modern td {
      border: none;
      vertical-align: middle;
    }
    .transaction-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #6c757d;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 600;
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
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center fw-bold" href="index.php">
        <i class="bi bi-gem me-2 fs-4"></i>
        <span class="<?php echo getLangClass($lang); ?>"><?php echo $t['app_name']; ?></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="index.php">
              <i class="bi bi-house me-1"></i><?php echo $t['dashboard']; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo getLangClass($lang); ?>" href="transactions.php">
              <i class="bi bi-list-ul me-1"></i><?php echo $t['transactions']; ?>
            </a>
          </li>
          <?php if($_SESSION['user']['role'] == 'manager'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo getLangClass($lang); ?>" href="users.php">
              <i class="bi bi-people me-1"></i><?php echo $t['users']; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo getLangClass($lang); ?>" href="approve_transfers.php">
              <i class="bi bi-check-circle me-1"></i><?php echo $t['approve_transfers']; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo getLangClass($lang); ?>" href="report.php">
              <i class="bi bi-file-earmark-text me-1"></i><?php echo $t['reports']; ?>
            </a>
          </li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav">
          <!-- Language Switcher -->
          <li class="nav-item">
            <?php echo getLanguageSwitcher($lang); ?>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle fw-semibold" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
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
  <div class="main-content">
    <div class="container py-4">
      
      <!-- Welcome Section -->
      <div class="welcome-section">
        <div class="row align-items-center">
          <div class="col-lg-8 col-12">
            <h1 class="display-6 fw-bold mb-2 <?php echo getLangClass($lang); ?>">
              <?php echo $t['welcome_back']; ?>, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>! 👋
            </h1>
            <p class="lead mb-0 opacity-90 <?php echo getLangClass($lang); ?>">
              <?php echo $t['fund_overview']; ?>, <?php echo date('F j, Y'); ?>
            </p>
          </div>
          <div class="col-lg-4 col-12 text-lg-end text-center mt-lg-0 mt-3">
            <!-- Year Filter -->
            <form method="GET" action="index.php" class="d-inline-block">
              <div class="input-group" style="max-width: 200px; margin-left: auto;">
                <label class="input-group-text <?php echo getLangClass($lang); ?>" for="yearSelect">
                  <i class="bi bi-calendar me-1"></i><?php echo $t['year']; ?>
                </label>
                <select class="form-select" id="yearSelect" name="year" onchange="this.form.submit()">
                  <?php while($yearRow = $availableYears->fetch_assoc()): ?>
                    <option value="<?php echo $yearRow['year']; ?>" <?php echo $yearRow['year'] == $selectedYear ? 'selected' : ''; ?>>
                      <?php echo $yearRow['year']; ?>
                    </option>
                  <?php endwhile; ?>
                  <?php if($availableYears->num_rows == 0): ?>
                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                  <?php endif; ?>
                </select>
              </div>
            </form>
          </div>
          <div class="col-12 text-center mt-4">
            <div class="d-inline-flex align-items-center rounded-3 px-4 py-3" style="background: linear-gradient(145deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(15px); box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);">
              <div class="h3 mb-0 text-white fw-bold">৳<?php echo number_format($balance, 0); ?></div>
              <small class="ms-3 text-white-50 <?php echo getLangClass($lang); ?>"><?php echo $t['current_balance']; ?></small>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="card stat-card h-100" style="--accent-color: #198754;">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #198754, #20c997);">
                  <i class="bi bi-arrow-down-circle"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="h4 mb-0 text-success">৳<?php echo number_format($totCol, 0); ?></div>
                  <div class="text-muted small <?php echo getLangClass($lang); ?>"><?php echo $t['total_collections']; ?></div>
                  <div class="text-success small <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-arrow-up me-1"></i>
                    ৳<?php echo number_format($monthlyData['month_collections'] ?? 0, 0); ?> <?php echo $t['this_month']; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card h-100" style="--accent-color: #dc3545;">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #dc3545, #fd7e14);">
                  <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="h4 mb-0 text-danger">৳<?php echo number_format($totExp, 0); ?></div>
                  <div class="text-muted small <?php echo getLangClass($lang); ?>"><?php echo $t['total_expenses']; ?></div>
                  <div class="text-danger small <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-arrow-up me-1"></i>
                    ৳<?php echo number_format($monthlyData['month_expenses'] ?? 0, 0); ?> <?php echo $t['this_month']; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card h-100" style="--accent-color: #0dcaf0;">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #0dcaf0, #0d6efd);">
                  <i class="bi bi-receipt"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="h4 mb-0 text-info"><?php echo $transactionCount; ?></div>
                  <div class="text-muted small <?php echo getLangClass($lang); ?>"><?php echo $t['total_transactions']; ?></div>
                  <div class="text-info small <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-plus me-1"></i>
                    <?php echo $todayCount; ?> <?php echo $t['added_today']; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card h-100" style="--accent-color: #6f42c1;">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #6f42c1, #d63384);">
                  <i class="bi bi-people"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="h4 mb-0 text-primary"><?php echo $userCount; ?></div>
                  <div class="text-muted small <?php echo getLangClass($lang); ?>"><?php echo $t['active_members']; ?></div>
                  <div class="text-primary small <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-person-check me-1"></i>
                    <?php echo $t['all_verified']; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Dashboard Content -->
      <div class="row g-4">
        <!-- Recent Transactions -->
        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-header bg-transparent border-0 pb-0">
              <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0">
                  <i class="bi bi-clock-history me-2 text-primary"></i>
                  Recent Transactions
                </h5>
                <a href="transactions.php" class="btn btn-outline-primary btn-sm">
                  View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
            <div class="card-body">
              <?php if($recentTransactions->num_rows > 0): ?>
              <!-- Desktop Table View -->
              <div class="d-none d-lg-block">
                <div class="table-responsive">
                  <table class="table table-modern">
                    <thead>
                      <tr>
                        <th>Transaction</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Added By</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $recentTransactions->data_seek(0); // Reset pointer
                      while($tx = $recentTransactions->fetch_assoc()): ?>
                      <tr>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="me-3">
                              <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                   style="width: 40px; height: 40px; background: <?php echo $tx['type'] == 'collection' ? '#d4edda' : ($tx['type'] == 'expense' ? '#f8d7da' : '#cce5ff'); ?>; flex-shrink: 0;">
                                <i class="bi bi-<?php echo $tx['type'] == 'collection' ? 'arrow-down text-success' : ($tx['type'] == 'expense' ? 'arrow-up text-danger' : 'arrow-left-right text-primary'); ?>"></i>
                              </div>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                              <div class="fw-semibold text-truncate small" title="<?php echo htmlspecialchars(isset($tx['display_description']) ? $tx['display_description'] : $tx['description']); ?>"><?php 
                                $displayDesc = isset($tx['display_description']) ? $tx['display_description'] : $tx['description'];
                                $description = htmlspecialchars($displayDesc);
                                echo strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                              ?></div>
                              <div class="d-flex gap-1">
                                <span class="badge <?php echo $tx['type'] == 'collection' ? 'bg-success' : ($tx['type'] == 'expense' ? 'bg-danger' : 'bg-primary'); ?> badge-sm">
                                  <?php echo $tx['type'] == 'collection' ? 'Collection' : ($tx['type'] == 'expense' ? 'Expense' : 'Transfer'); ?>
                                </span>
                                <?php if($tx['category']): ?>
                                <span class="badge bg-secondary badge-sm"><?php echo ucfirst($tx['category']); ?></span>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="text-end">
                          <span class="fw-bold <?php echo $tx['type'] == 'collection' ? 'text-success' : ($tx['type'] == 'expense' ? 'text-danger' : 'text-primary'); ?>">
                            <?php echo $tx['type'] == 'collection' ? '+' : ($tx['type'] == 'expense' ? '-' : ''); ?>৳<?php echo number_format($tx['amount'], 2); ?>
                          </span>
                        </td>
                        <td>
                          <div class="text-muted small">
                            <div><?php echo date('M j, Y', strtotime($tx['date'])); ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('g:i A', strtotime($tx['created_at'])); ?></div>
                          </div>
                        </td>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="transaction-avatar me-2 flex-shrink-0">
                              <?php echo strtoupper(substr($tx['added_by_name'], 0, 2)); ?>
                            </div>
                            <small class="text-truncate"><?php echo htmlspecialchars($tx['added_by_name']); ?></small>
                          </div>
                        </td>
                      </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <!-- Mobile Card View -->
              <div class="d-lg-none">
                <?php 
                $recentTransactions->data_seek(0); // Reset pointer
                while($tx = $recentTransactions->fetch_assoc()): ?>
                <div class="card mb-3 border-0 shadow-sm">
                  <div class="card-body p-3">
                    <div class="d-flex align-items-start">
                      <div class="me-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 48px; height: 48px; background: <?php echo $tx['type'] == 'collection' ? '#d4edda' : ($tx['type'] == 'expense' ? '#f8d7da' : '#cce5ff'); ?>; flex-shrink: 0;">
                          <i class="bi bi-<?php echo $tx['type'] == 'collection' ? 'arrow-down text-success' : ($tx['type'] == 'expense' ? 'arrow-up text-danger' : 'arrow-left-right text-primary'); ?>" style="font-size: 1.2rem;"></i>
                        </div>
                      </div>
                      <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <div class="flex-grow-1 min-width-0 me-3">
                            <div class="fw-semibold mb-1" style="line-height: 1.3;"><?php 
                              $displayDesc = isset($tx['display_description']) ? $tx['display_description'] : $tx['description'];
                              echo htmlspecialchars($displayDesc);
                            ?></div>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                              <span class="badge <?php echo $tx['type'] == 'collection' ? 'bg-success' : ($tx['type'] == 'expense' ? 'bg-danger' : 'bg-primary'); ?>">
                                <?php echo $tx['type'] == 'collection' ? 'Collection' : ($tx['type'] == 'expense' ? 'Expense' : 'Transfer'); ?>
                              </span>
                              <?php if($tx['category']): ?>
                              <span class="badge bg-secondary"><?php echo ucfirst($tx['category']); ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="text-end flex-shrink-0">
                            <div class="fw-bold fs-5 <?php echo $tx['type'] == 'collection' ? 'text-success' : ($tx['type'] == 'expense' ? 'text-danger' : 'text-primary'); ?>">
                              <?php echo $tx['type'] == 'collection' ? '+' : ($tx['type'] == 'expense' ? '-' : ''); ?>৳<?php echo number_format($tx['amount'], 2); ?>
                            </div>
                          </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center text-muted small">
                          <div class="d-flex align-items-center">
                            <div class="transaction-avatar me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                              <?php echo strtoupper(substr($tx['added_by_name'], 0, 2)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($tx['added_by_name']); ?></span>
                          </div>
                          <div class="text-end">
                            <div><?php echo date('M j, Y', strtotime($tx['date'])); ?></div>
                            <div style="font-size: 0.75rem;"><?php echo date('g:i A', strtotime($tx['created_at'])); ?></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endwhile; ?>
              </div>
              <?php else: ?>
              <div class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <h5 class="mt-3 text-muted">No transactions yet</h5>
                <p class="text-muted">Start by adding your first transaction</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddModal">
                  <i class="bi bi-plus-circle me-2"></i>Add Transaction
                </button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Fund Overview & Quick Stats -->
        <div class="col-lg-4">
          <!-- Balance Overview -->
          <div class="card mb-4">
            <div class="card-header bg-transparent border-0">
              <h6 class="mb-0">
                <i class="bi bi-wallet2 me-2 text-primary"></i>
                Fund Balance
              </h6>
            </div>
            <div class="card-body text-center">
              <div class="display-6 fw-bold mb-2 <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                ৳<?php echo number_format(abs($balance), 2); ?>
              </div>
              <div class="mb-3">
                <?php if($balance >= 0): ?>
                <span class="badge bg-success-subtle text-success px-3 py-2">
                  <i class="bi bi-arrow-up me-1"></i>Surplus
                </span>
                <?php else: ?>
                <span class="badge bg-danger-subtle text-danger px-3 py-2">
                  <i class="bi bi-arrow-down me-1"></i>Deficit
                </span>
                <?php endif; ?>
              </div>
              <div class="row text-center">
                <div class="col-6">
                  <div class="border-end">
                    <div class="h6 text-success mb-0">৳<?php echo number_format($totCol, 0); ?></div>
                    <small class="text-muted">Collections</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="h6 text-danger mb-0">৳<?php echo number_format($totExp, 0); ?></div>
                  <small class="text-muted">Expenses</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Actions Card -->
          <div class="card">
            <div class="card-header bg-transparent border-0">
              <h6 class="mb-0">
                <i class="bi bi-lightning me-2 text-primary"></i>
                Quick Actions
              </h6>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddModal">
                  <i class="bi bi-plus-circle me-2"></i>Add Transaction
                </button>
                <a href="transactions.php" class="btn btn-outline-primary">
                  <i class="bi bi-list-ul me-2"></i>View All Transactions
                </a>
                <?php if($_SESSION['user']['role'] == 'manager'): ?>
                <a href="users.php" class="btn btn-outline-secondary">
                  <i class="bi bi-people me-2"></i>Manage Users
                </a>
                <a href="report.php" class="btn btn-outline-info">
                  <i class="bi bi-graph-up me-2"></i>Generate Report
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Add Transaction Modal -->
  <div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-plus-circle me-2"></i>
            Quick Add Transaction
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="index.php">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">
                  <i class="bi bi-arrow-left-right me-2"></i>Transaction Type
                </label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="type" id="quickCollection" value="collection" checked>
                  <label class="btn btn-outline-success" for="quickCollection">
                    <i class="bi bi-arrow-down-circle me-1"></i>Collection
                  </label>
                  <input type="radio" class="btn-check" name="type" id="quickExpense" value="expense">
                  <label class="btn btn-outline-danger" for="quickExpense">
                    <i class="bi bi-arrow-up-circle me-1"></i>Expense
                  </label>
                  <input type="radio" class="btn-check" name="type" id="quickTransfer" value="transfer">
                  <label class="btn btn-outline-primary" for="quickTransfer">
                    <i class="bi bi-arrow-left-right me-1"></i><?php echo $t['transfer']; ?>
                  </label>
                </div>
              </div>
              
              <div class="col-12">
                <label for="quickAmount" class="form-label fw-semibold">
                  <i class="bi bi-currency-rupee me-2"></i>Amount
                </label>
                <div class="input-group">
                  <span class="input-group-text">৳</span>
                  <input type="number" class="form-control" id="quickAmount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
              </div>
              
              <div class="col-12">
                <label for="quickDescription" class="form-label fw-semibold">
                  <i class="bi bi-card-text me-2"></i>Description
                </label>
                <input type="text" class="form-control" id="quickDescription" name="description" placeholder="Brief description" required>
              </div>
              
              <div class="col-md-6">
                <label for="quickDate" class="form-label fw-semibold">
                  <i class="bi bi-calendar me-2"></i>Date
                </label>
                <input type="date" class="form-control" id="quickDate" name="date" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              
              <div class="col-md-6" id="quickCategoryGroup" style="display: none;">
                <label for="quickCategory" class="form-label fw-semibold">
                  <i class="bi bi-tag me-2"></i>Category
                </label>
                <select class="form-select" id="quickCategory" name="category">
                  <?php echo renderCategoryOptions('', $lang, $t['select_category']); ?>
                </select>
              </div>
              
              <div class="col-md-6" id="quickTransferUserGroup" style="display: none;">
                <label for="quickTransferUser" class="form-label fw-semibold">
                  <i class="bi bi-person me-2"></i><?php echo $t['transfer_to_user']; ?>
                </label>
                <select class="form-select" id="quickTransferUser" name="transfer_user_id">
                  <option value=""><?php echo $t['select_user']; ?></option>
                  <?php 
                  $users_query = "SELECT id, name FROM users WHERE id != {$_SESSION['user']['id']} ORDER BY name";
                  $users_result = $conn->query($users_query);
                  while ($user = $users_result->fetch_assoc()): 
                  ?>
                  <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle me-2"></i>Add Transaction
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Handle form field visibility based on transaction type
    document.addEventListener('DOMContentLoaded', function() {
      const categoryGroup = document.getElementById('quickCategoryGroup');
      const transferUserGroup = document.getElementById('quickTransferUserGroup');
      const typeRadios = document.querySelectorAll('input[name="type"]');
      const amountInput = document.getElementById('quickAmount');
      const form = document.querySelector('#quickAddModal form');
      
      // User's current balance (from PHP)
      const userBalance = <?php 
        // Calculate current user balance for JavaScript validation
        $user_id = $_SESSION['user']['id'];
        $balanceStmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) as collections,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses,
                COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) as transfer_in,
                COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as transfer_out
            FROM transactions 
            WHERE added_by = ?
        ");
        $balanceStmt->bind_param('i', $user_id);
        $balanceStmt->execute();
        $balanceData = $balanceStmt->get_result()->fetch_assoc();
        $js_balance = $balanceData['collections'] + $balanceData['transfer_in'] - $balanceData['transfer_out'] - $balanceData['expenses'];
        echo number_format($js_balance, 2, '.', '');
      ?>;
      
      function toggleFormFields() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        
        if (selectedType === 'expense') {
          categoryGroup.style.display = 'block';
          transferUserGroup.style.display = 'none';
          document.getElementById('quickTransferUser').required = false;
        } else if (selectedType === 'transfer') {
          categoryGroup.style.display = 'none';
          transferUserGroup.style.display = 'block';
          document.getElementById('quickTransferUser').required = true;
        } else {
          categoryGroup.style.display = 'none';
          transferUserGroup.style.display = 'none';
          document.getElementById('quickTransferUser').required = false;
        }
      }
      
      // Validate expense amount against balance
      function validateExpenseAmount() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        const amount = parseFloat(amountInput.value) || 0;
        
        if (selectedType === 'expense' && amount > userBalance) {
          amountInput.setCustomValidity(`Insufficient balance! Your current balance is ৳${userBalance.toFixed(2)} but you're trying to add an expense of ৳${amount.toFixed(2)}`);
          return false;
        } else {
          amountInput.setCustomValidity('');
          return true;
        }
      }
      
      // Form validation on submit
      form.addEventListener('submit', function(e) {
        if (!validateExpenseAmount()) {
          e.preventDefault();
          amountInput.reportValidity();
        }
      });
      
      // Real-time validation on amount input
      amountInput.addEventListener('input', validateExpenseAmount);
      
      typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
          toggleFormFields();
          validateExpenseAmount();
        });
      });
      
      toggleFormFields();
    });

    // Add smooth animations
    document.addEventListener('DOMContentLoaded', function() {
      // Animate cards on load
      const cards = document.querySelectorAll('.card');
      cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
          card.style.transition = 'all 0.6s ease';
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, index * 100);
      });
    });
  </script>
</body>
</html>
