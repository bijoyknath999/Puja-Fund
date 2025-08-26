<?php
include 'auth.php';
include 'db.php';
include 'lang.php';
include 'categories.php';

$lang = getCurrentLanguage();
$t = getTranslations($lang);

// Handle filtering
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterType = $_GET['type'] ?? '';

// Helper function to get user name
function getUserName($user_id, $conn) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['name'] : 'Unknown User';
}

// Handle transaction form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $category = $_POST['category'] ?? null;
    $date = $_POST['date'];
    $user_id = $_SESSION['user']['id'];
    
    if ($type == 'transfer') {
        if (!isset($_POST['transfer_user_id']) || empty($_POST['transfer_user_id'])) {
            $_SESSION['error'] = 'Please select a user for transfer';
            header('Location: transactions.php');
            exit();
        }
        
        // Handle transfer transaction
        $transfer_user_id = intval($_POST['transfer_user_id']);
        
        // Check if user has sufficient balance
        $balance_check = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as balance
            FROM transactions 
            WHERE added_by = ?
        ");
        $balance_check->bind_param('i', $user_id);
        $balance_check->execute();
        $current_balance = $balance_check->get_result()->fetch_assoc()['balance'];
        
        if ($current_balance < $amount) {
            $_SESSION['error'] = 'Insufficient balance. Your current balance is ৳' . number_format($current_balance, 2) . ' but you are trying to transfer ৳' . number_format($amount, 2);
            header('Location: transactions.php');
            exit();
        }
        
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
                header('Location: transactions.php');
                exit();
            }
        }
        
        // Handle regular transaction
        $stmt = $conn->prepare("INSERT INTO transactions (type, amount, description, category, date, added_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsssi", $type, $amount, $description, $category, $date, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = $t['transaction_added_success'];
        } else {
            $_SESSION['error'] = $t['error_adding_transaction'] . ": " . $conn->error;
        }
    }
    
    header('Location: transactions.php');
    exit;
}

// Build query with filtering - Group transfers to show as single entries
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($from && $to) {
    $whereConditions[] = "t.date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $paramTypes .= 'ss';
}

if ($filterUser) {
    $whereConditions[] = "t.added_by = ?";
    $params[] = $filterUser;
    $paramTypes .= 'i';
}

if ($filterType && $filterType != 'transfer') {
    $whereConditions[] = "t.type = ?";
    $params[] = $filterType;
    $paramTypes .= 's';
} elseif ($filterType == 'transfer') {
    // Only show outgoing transfers to avoid duplicates
    $whereConditions[] = "t.type = 'transfer' AND t.description LIKE '%Transfer to%'";
}

// For transfers, only show outgoing transfers to avoid showing duplicates
$baseQuery = "
    SELECT t.*, u.name as added_by_name,
           CASE 
               WHEN t.type = 'transfer' AND t.description LIKE '%Transfer to%' THEN 
                   CONCAT('Transfer: ', u.name, ' → ', 
                          SUBSTRING(t.description, LOCATE('Transfer to ', t.description) + 12, 
                                   LOCATE(' : ', t.description) - LOCATE('Transfer to ', t.description) - 12))
               ELSE t.description 
           END as display_description
    FROM transactions t 
    JOIN users u ON u.id=t.added_by
    WHERE (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
";

if (!empty($whereConditions)) {
    $baseQuery .= " AND " . implode(" AND ", $whereConditions);
}
$baseQuery .= " ORDER BY t.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($baseQuery);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($baseQuery);
}

// Get all users for filter dropdown
$usersQuery = "SELECT id, name FROM users ORDER BY name";
$usersResult = $conn->query($usersQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title><?php echo $t['page_title_transactions']; ?></title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8f9fa;
    }
    .navbar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
<nav class="navbar navbar-expand-lg">
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
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="index.php">
            <i class="bi bi-house me-1"></i><?php echo $t['dashboard']; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="transactions.php">
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
                    <i class="bi bi-list-ul text-white" style="font-size: 1.2rem;"></i>
                  </div>
                </div>
                <div>
                  <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['transactions']; ?></h4>
                  <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['view_manage_transactions']; ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button class="btn btn-outline-primary <?php echo getLangClass($lang); ?>" data-bs-toggle="collapse" data-bs-target="#filterSection">
                  <i class="bi bi-funnel me-2"></i><?php echo $t['filter']; ?>
                </button>
                <?php if ($_SESSION['user']['role'] === 'manager'): ?>
                <button class="btn btn-primary <?php echo getLangClass($lang); ?>" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                  <i class="bi bi-plus-circle me-2"></i><?php echo $t['add_transaction']; ?>
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Messages -->
  <?php if(isset($_SESSION['success'])): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['success']); endif; ?>

  <?php if(isset($_SESSION['error'])): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['error']); endif; ?>

  <!-- Filter Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="collapse" id="filterSection">
        <div class="card">
          <div class="card-body">
            <form method="GET" action="transactions.php">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="from" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar me-1"></i><?php echo $t['from_date']; ?>
                  </label>
                  <input type="date" class="form-control" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>">
                </div>
                <div class="col-md-3">
                  <label for="to" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar me-1"></i><?php echo $t['to_date']; ?>
                  </label>
                  <input type="date" class="form-control" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>">
                </div>
                <div class="col-md-2">
                  <label for="user" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-person me-1"></i><?php echo $t['user']; ?>
                  </label>
                  <select class="form-select" id="user" name="user">
                    <option value=""><?php echo $t['all']; ?></option>
                    <?php 
                      $usersResult->data_seek(0); // Reset result pointer
                      while ($user = $usersResult->fetch_assoc()): 
                    ?>
                      <option value="<?php echo $user['id']; ?>" <?php echo ($filterUser == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="type" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-funnel me-1"></i><?php echo $t['transaction_type']; ?>
                  </label>
                  <select class="form-select" id="type" name="type">
                    <option value=""><?php echo $t['all']; ?></option>
                    <option value="collection" <?php echo ($filterType == 'collection') ? 'selected' : ''; ?>><?php echo $t['collection']; ?></option>
                    <option value="expense" <?php echo ($filterType == 'expense') ? 'selected' : ''; ?>><?php echo $t['expense']; ?></option>
                    <option value="transfer" <?php echo ($filterType == 'transfer') ? 'selected' : ''; ?>><?php echo $t['transfer']; ?></option>
                  </select>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-search me-2"></i><?php echo $t['apply_filter']; ?>
                  </button>
                  <a href="transactions.php" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>">
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

  <!-- Add Transaction Modal -->
  <div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title <?php echo getLangClass($lang); ?>">
            <i class="bi bi-plus-circle me-2"></i>
            <?php echo $t['add_transaction']; ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="transactions.php">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-arrow-left-right me-2"></i><?php echo $t['transaction_type']; ?>
                </label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="type" id="collection" value="collection" checked>
                  <label class="btn btn-outline-success <?php echo getLangClass($lang); ?>" for="collection">
                    <i class="bi bi-arrow-down-circle me-1"></i><?php echo $t['collection']; ?>
                  </label>
                  <input type="radio" class="btn-check" name="type" id="expense" value="expense">
                  <label class="btn btn-outline-danger <?php echo getLangClass($lang); ?>" for="expense">
                    <i class="bi bi-arrow-up-circle me-1"></i><?php echo $t['expense']; ?>
                  </label>
                  <input type="radio" class="btn-check" name="type" id="transfer" value="transfer">
                  <label class="btn btn-outline-primary <?php echo getLangClass($lang); ?>" for="transfer">
                    <i class="bi bi-arrow-left-right me-1"></i><?php echo $t['transfer']; ?>
                  </label>
                </div>
              </div>
              
              <div class="col-12">
                <label for="amount" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-currency-rupee me-2"></i><?php echo $t['amount']; ?>
                </label>
                <div class="input-group">
                  <span class="input-group-text">৳</span>
                  <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
              </div>
              
              <div class="col-12">
                <label for="description" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-text-paragraph me-2"></i><?php echo $t['description']; ?>
                </label>
                <input type="text" class="form-control" id="description" name="description" placeholder="<?php echo $t['brief_description']; ?>" required>
              </div>
              <div class="col-md-6" id="categoryGroup" style="display: none;">
                <label for="category" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-tag me-2"></i><?php echo $t['category']; ?>
                </label>
                <select class="form-select" id="category" name="category">
                  <?php echo renderCategoryOptions('', $lang, $t['select_category']); ?>
                </select>
              </div>
              
              <div class="col-md-6" id="transferUserGroup" style="display: none;">
                <label for="transferUser" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-person me-2"></i><?php echo $t['transfer_to_user']; ?>
                </label>
                <select class="form-select" id="transferUser" name="transfer_user_id">
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
              
              <div class="col-12">
                <label for="date" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-calendar me-2"></i><?php echo $t['date']; ?>
                </label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary <?php echo getLangClass($lang); ?>" data-bs-dismiss="modal"><?php echo $t['cancel']; ?></button>
            <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>">
              <i class="bi bi-check-lg me-2"></i><?php echo $t['add_transaction']; ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['date']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['transaction_type']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['description']; ?></th>
                  <th class="text-end <?php echo getLangClass($lang); ?>"><?php echo $t['amount']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['added_by']; ?></th>
                  <?php if ($_SESSION['user']['role'] === 'manager'): ?><th class="<?php echo getLangClass($lang); ?>"><?php echo $t['actions']; ?></th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
              <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="fw-semibold"><?php echo date('M j, Y', strtotime($row['date'])); ?></span>
                  </td>
                  <td>
                    <span class="badge <?php 
                      echo $row['type'] == 'collection' ? 'bg-success' : 
                           ($row['type'] == 'expense' ? 'bg-danger' : 'bg-primary'); 
                    ?> <?php echo getLangClass($lang); ?>">
                      <i class="bi bi-<?php 
                        echo $row['type'] == 'collection' ? 'arrow-down' : 
                             ($row['type'] == 'expense' ? 'arrow-up' : 'arrow-left-right'); 
                      ?> me-1"></i>
                      <?php 
                        echo $row['type'] == 'collection' ? $t['collection'] : 
                             ($row['type'] == 'expense' ? $t['expense'] : $t['transfer']); 
                      ?>
                    </span>
                  </td>
                  <td><?php 
                    if ($row['type'] == 'transfer' && isset($row['display_description'])) {
                      echo htmlspecialchars($row['display_description']); // Use formatted transfer description
                    } elseif ($row['type'] == 'transfer' && (strpos($row['description'], 'Transfer to') === 0 || strpos($row['description'], 'Transfer from') === 0)) {
                      echo htmlspecialchars($row['description']); // Escape transfer descriptions
                    } else {
                      echo htmlspecialchars($row['description']); // Escape other descriptions
                    }
                  ?></td>
                  <td class="text-end fw-semibold <?php 
                    echo $row['type'] == 'collection' ? 'text-success' : 
                         ($row['type'] == 'expense' ? 'text-danger' : 'text-primary'); 
                  ?>">
                    ৳<?php echo number_format($row['amount'], 2); ?>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?php echo strtoupper(substr($row['added_by_name'], 0, 2)); ?>
                      </div>
                      <span><?php echo htmlspecialchars($row['added_by_name']); ?></span>
                    </div>
                  </td>
                  <?php if ($_SESSION['user']['role'] === 'manager'): ?>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-outline-primary" href="edit.php?id=<?php echo $row['id']; ?>">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a class="btn btn-outline-danger" href="delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('<?php echo $t['confirm_delete_transaction']; ?>')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
          <?php endif; ?>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const categoryGroup = document.getElementById('categoryGroup');
    const transferUserGroup = document.getElementById('transferUserGroup');
    const categorySelect = document.getElementById('category');
    const transferUserSelect = document.getElementById('transferUser');

    function toggleFormFields() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        
        if (selectedType === 'expense') {
            categoryGroup.style.display = 'block';
            transferUserGroup.style.display = 'none';
            transferUserSelect.required = false;
        } else if (selectedType === 'transfer') {
            categoryGroup.style.display = 'none';
            transferUserGroup.style.display = 'block';
            transferUserSelect.required = true;
        } else {
            categoryGroup.style.display = 'none';
            transferUserGroup.style.display = 'none';
            transferUserSelect.required = false;
        }
    }

    typeRadios.forEach(radio => {
        radio.addEventListener('change', toggleFormFields);
    });

    // Initial call
    toggleFormFields();
});
</script>
</body>
</html>
  </div>
</div>
