<?php
include 'auth.php';
include 'db.php';
include 'lang.php';
include 'categories.php';

$lang = getCurrentLanguage();
$t = getTranslations($lang);

// Get filter parameters
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';
$selectedYear = $_GET['year'] ?? date('Y');

// Build WHERE clause for filters
$whereClause = "WHERE added_by = ?";
$params = [$_SESSION['user']['id']];
$paramTypes = 'i';

if ($from_date) {
    $whereClause .= " AND date >= ?";
    $params[] = $from_date;
    $paramTypes .= 's';
}

if ($to_date) {
    $whereClause .= " AND date <= ?";
    $params[] = $to_date;
    $paramTypes .= 's';
}

if ($type) {
    $whereClause .= " AND type = ?";
    $params[] = $type;
    $paramTypes .= 's';
}

// Get user's transactions with filters
$stmt = $conn->prepare("
    SELECT t.*, 
           CASE 
               WHEN t.type = 'transfer' AND t.description LIKE '%Transfer to%' THEN 
                   CONCAT('Transfer: ', (SELECT name FROM users WHERE id = t.added_by), ' → ', 
                          SUBSTRING(t.description, LOCATE('Transfer to ', t.description) + 12, 
                                   LOCATE(' : ', t.description) - LOCATE('Transfer to ', t.description) - 12))
               WHEN t.type = 'transfer' AND t.description LIKE '%Transfer from%' THEN 
                   CONCAT('Transfer: ', 
                          SUBSTRING(t.description, LOCATE('Transfer from ', t.description) + 13, 
                                   LOCATE(' : ', t.description) - LOCATE('Transfer from ', t.description) - 13),
                          ' → ', (SELECT name FROM users WHERE id = t.added_by))
               ELSE t.description 
           END as display_description
    FROM transactions t 
    $whereClause ORDER BY created_at DESC");
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

// Build balance calculation WHERE clause with date filters
$balanceWhereClause = "WHERE added_by = ?";
$balanceParams = [$_SESSION['user']['id']];
$balanceParamTypes = 'i';

// Add date filters if specified
if ($from_date) {
    $balanceWhereClause .= " AND date >= ?";
    $balanceParams[] = $from_date;
    $balanceParamTypes .= 's';
}

if ($to_date) {
    $balanceWhereClause .= " AND date <= ?";
    $balanceParams[] = $to_date;
    $balanceParamTypes .= 's';
} else {
    // If no to_date specified, filter by selected year
    $balanceWhereClause .= " AND YEAR(date) = ?";
    $balanceParams[] = $selectedYear;
    $balanceParamTypes .= 'i';
}

// Calculate user's balance with date filters
$totColStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions $balanceWhereClause AND type='collection'");
$totColStmt->bind_param($balanceParamTypes, ...$balanceParams);
$totColStmt->execute();
$totCol = $totColStmt->get_result()->fetch_assoc()['total'];

$totExpStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions $balanceWhereClause AND type='expense'");
$totExpStmt->bind_param($balanceParamTypes, ...$balanceParams);
$totExpStmt->execute();
$totExp = $totExpStmt->get_result()->fetch_assoc()['total'];

// Calculate transfer amounts (incoming transfers are positive, outgoing are negative)
$transferInStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions $balanceWhereClause AND type='transfer' AND description LIKE '%Transfer from%'");
$transferInStmt->bind_param($balanceParamTypes, ...$balanceParams);
$transferInStmt->execute();
$transferIn = $transferInStmt->get_result()->fetch_assoc()['total'];

$transferOutStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions $balanceWhereClause AND type='transfer' AND description LIKE '%Transfer to%'");
$transferOutStmt->bind_param($balanceParamTypes, ...$balanceParams);
$transferOutStmt->execute();
$transferOut = $transferOutStmt->get_result()->fetch_assoc()['total'];

$transferBalance = $transferIn - $transferOut;
$balance = $totCol - $totExp + $transferBalance;

// Get available years for dropdown
$yearsStmt = $conn->prepare("SELECT DISTINCT YEAR(date) as year FROM transactions WHERE added_by = ? ORDER BY year DESC");
$yearsStmt->bind_param('i', $_SESSION['user']['id']);
$yearsStmt->execute();
$years = $yearsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title><?php echo $t['my_profile']; ?> - <?php echo $t['app_name']; ?></title>
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
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: white;
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
            <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person me-2"></i><?php echo $t['profile']; ?></a></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i><?php echo $t['logout']; ?></a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

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
                    <i class="bi bi-person text-white" style="font-size: 1.2rem;"></i>
                  </div>
                </div>
                <div>
                  <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['my_profile']; ?></h4>
                  <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['view_my_transaction_history']; ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button class="btn btn-outline-primary <?php echo getLangClass($lang); ?>" data-bs-toggle="collapse" data-bs-target="#filterSection">
                  <i class="bi bi-funnel me-2"></i><?php echo $t['filter']; ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Balance Summary -->
  <div class="row mb-2">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0 <?php echo getLangClass($lang); ?>">
          <i class="bi bi-bar-chart me-2"></i>Financial Summary
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
  <div class="row mb-4">
    <div class="col-md-4 mb-3">
      <div class="card stat-card" style="--accent-color: #28a745;">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success me-3">
              <i class="bi bi-wallet2"></i>
            </div>
            <div>
              <h6 class="text-muted mb-1 <?php echo getLangClass($lang); ?>"><?php echo $t['my_balance']; ?></h6>
              <h4 class="mb-0 fw-bold <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                ৳<?php echo number_format(abs($balance), 2); ?>
                <?php if($balance < 0): ?><small class="text-danger">(<?php echo $t['deficit']; ?>)</small><?php endif; ?>
              </h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card stat-card" style="--accent-color: #007bff;">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success me-3">
              <i class="bi bi-arrow-down-circle"></i>
            </div>
            <div>
              <h6 class="text-muted mb-1 <?php echo getLangClass($lang); ?>"><?php echo $t['my_collections']; ?></h6>
              <h4 class="mb-0 fw-bold text-success">৳<?php echo number_format($totCol, 2); ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card stat-card" style="--accent-color: #dc3545;">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-danger me-3">
              <i class="bi bi-arrow-up-circle"></i>
            </div>
            <div>
              <h6 class="text-muted mb-1 <?php echo getLangClass($lang); ?>"><?php echo $t['my_expenses']; ?></h6>
              <h4 class="mb-0 fw-bold text-danger">৳<?php echo number_format($totExp, 2); ?></h4>
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
            <form method="GET" action="profile.php">
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
                  <label for="type" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-funnel me-1"></i><?php echo $t['transaction_type']; ?>
                  </label>
                  <select class="form-select" id="type" name="type">
                    <option value=""><?php echo $t['all']; ?></option>
                    <option value="collection" <?php echo $type === 'collection' ? 'selected' : ''; ?>><?php echo $t['collection']; ?></option>
                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>><?php echo $t['expense']; ?></option>
                    <option value="transfer" <?php echo $type === 'transfer' ? 'selected' : ''; ?>><?php echo $t['transfer']; ?></option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="year" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                    <i class="bi bi-calendar3 me-1"></i><?php echo $t['year']; ?>
                  </label>
                  <select class="form-select" id="year" name="year">
                    <?php while($yearRow = $years->fetch_assoc()): ?>
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
                  <a href="profile.php" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>">
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

  <!-- Transactions Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 <?php echo getLangClass($lang); ?>">
            <i class="bi bi-list-ul me-2"></i>
            <?php echo $t['my_transactions']; ?> (<?php echo $transactions->num_rows; ?>)
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if ($transactions->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['date']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['transaction_type']; ?></th>
                  <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['description']; ?></th>
                  <th class="text-end <?php echo getLangClass($lang); ?>"><?php echo $t['amount']; ?></th>
                </tr>
              </thead>
              <tbody>
              <?php while ($row = $transactions->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="fw-semibold"><?php echo date('M j, Y', strtotime($row['date'])); ?></span>
                  </td>
                  <td>
                    <span class="badge <?php 
                      if ($row['type'] == 'collection') echo 'bg-success';
                      elseif ($row['type'] == 'expense') echo 'bg-danger';
                      else echo 'bg-primary';
                    ?>">
                      <?php 
                        if ($row['type'] == 'collection') {
                          echo '<i class="bi bi-arrow-down-circle me-1"></i>' . $t['collection'];
                        } elseif ($row['type'] == 'expense') {
                          echo '<i class="bi bi-arrow-up-circle me-1"></i>' . $t['expense'];
                        } else {
                          echo '<i class="bi bi-arrow-left-right me-1"></i>' . $t['transfer'];
                        }
                      ?>
                    </span>
                  </td>
                  <td>
                    <?php 
                      if (isset($row['display_description'])) {
                        echo htmlspecialchars($row['display_description']);
                      } else {
                        echo htmlspecialchars($row['description']);
                      }
                    ?>
                  </td>
                  <td class="text-end">
                    <span class="fw-semibold <?php 
                      if ($row['type'] == 'collection') echo 'text-success';
                      elseif ($row['type'] == 'expense') echo 'text-danger';
                      else echo 'text-primary';
                    ?>">
                      <?php 
                        if ($row['type'] == 'collection') echo '+';
                        elseif ($row['type'] == 'expense') echo '-';
                      ?>৳<?php echo number_format($row['amount'], 2); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 <?php echo getLangClass($lang); ?>"><?php echo $t['no_transactions_yet']; ?></h5>
            <p class="text-muted <?php echo getLangClass($lang); ?>"><?php echo $t['start_first_transaction']; ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
