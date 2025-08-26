<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'lang.php';

// Check if user is manager
if($_SESSION['user']['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-12-31');
$filterUser = $_GET['user'] ?? '';
$filterType = $_GET['type'] ?? '';

// Build query with filtering
$whereConditions = ["date BETWEEN ? AND ?"];
$params = [$from, $to];
$paramTypes = 'ss';

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

$stmt = $conn->prepare($baseQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// Get all users for filter dropdown
$usersQuery = "SELECT id, name FROM users ORDER BY name";
$usersResult = $conn->query($usersQuery);

// Calculate totals
$totalCollection = 0;
$totalExpense = 0;
$totalTransfers = 0;
$transactions = [];

while($row = $res->fetch_assoc()) {
    $transactions[] = $row;
    if($row['type'] == 'collection') {
        $totalCollection += $row['amount'];
    } elseif($row['type'] == 'expense') {
        $totalExpense += $row['amount'];
    } elseif($row['type'] == 'transfer' && strpos($row['description'], 'Transfer to') === 0) {
        // Only count outgoing transfers to avoid double counting
        $totalTransfers += $row['amount'];
    }
}

$balance = $totalCollection - $totalExpense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['page_title_reports']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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

        @media print {
            .no-print { display: none !important; }
            body { 
                font-size: 12px; 
                line-height: 1.4;
                background: white !important;
                color: #000 !important;
            }
            .container { 
                max-width: 100% !important; 
                padding: 1rem !important; 
                margin: 0 !important;
            }
            .report-header {
                background: white !important;
                color: #000 !important;
                border: 2px solid #000 !important;
                border-radius: 0 !important;
                padding: 1rem !important;
                margin-bottom: 1.5rem !important;
                page-break-inside: avoid;
                text-align: center !important;
            }
            .card {
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                margin-bottom: 1rem !important;
                background: white !important;
            }
            .card-body {
                padding: 1rem !important;
            }
            .icon-circle {
                display: none !important;
            }
            .table {
                font-size: 11px !important;
                border-collapse: collapse !important;
                width: 100% !important;
            }
            .table th, .table td {
                border: 1px solid #000 !important;
                padding: 0.5rem !important;
                text-align: left !important;
            }
            .table thead th {
                background: #f8f9fa !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            .badge {
                background: none !important;
                color: #000 !important;
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                padding: 0.2rem 0.4rem !important;
                font-weight: normal !important;
            }
            .text-success, .text-danger, .text-primary, .text-dark { 
                color: #000 !important; 
            }
            h2, h4, h5 { 
                color: #000 !important;
                margin-bottom: 0.5rem !important;
            }
            .row { margin: 0 !important; }
            .col-md-3 { 
                width: 25% !important; 
                float: left !important; 
                padding: 0.5rem !important;
            }
            .clearfix::after {
                content: "";
                display: table;
                clear: both;
            }
            .fw-bold { font-weight: bold !important; }
            .fw-semibold { font-weight: 600 !important; }
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        .summary-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .collection-card {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 5px solid #28a745;
        }
        .expense-card {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 5px solid #dc3545;
        }
        .transfer-card {
            background: linear-gradient(135deg, #cce5ff 0%, #b3d9ff 100%);
            border-left: 5px solid #17a2b8;
        }
        .balance-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 5px solid #ffc107;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
        .badge {
            background: none !important;
            color: #000 !important;
            border: 1px solid #000 !important;
            border-radius: 0 !important;
            padding: 0.2rem 0.4rem !important;
            font-weight: normal !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .btn-outline-secondary {
            border-radius: 20px;
            border: 2px solid #6c757d;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        .btn-outline-secondary:hover {
            background: #6c757d;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .collection-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .expense-icon {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
        }
        .balance-icon {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
        }
        .transaction-icon {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg no-print">
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
                        <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="report.php">
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
        <!-- Date Filter Form -->
        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="from" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                            <i class="bi bi-calendar-event me-1"></i><?php echo $t['from_date']; ?>
                        </label>
                        <input type="date" class="form-control" id="from" name="from" value="<?php echo $from; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="to" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                            <i class="bi bi-calendar-check me-1"></i><?php echo $t['to_date']; ?>
                        </label>
                        <input type="date" class="form-control" id="to" name="to" value="<?php echo $to; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="user" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                            <i class="bi bi-person me-1"></i><?php echo $t['user']; ?>
                        </label>
                        <select class="form-select" id="user" name="user">
                            <option value=""><?php echo $t['all']; ?></option>
                            <?php while ($user = $usersResult->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($filterUser == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                            <i class="bi bi-tag me-1"></i><?php echo $t['type']; ?>
                        </label>
                        <select class="form-select" id="type" name="type">
                            <option value=""><?php echo $t['all']; ?></option>
                            <option value="collection" <?php echo ($filterType == 'collection') ? 'selected' : ''; ?>><?php echo $t['collection']; ?></option>
                            <option value="expense" <?php echo ($filterType == 'expense') ? 'selected' : ''; ?>><?php echo $t['expense']; ?></option>
                            <option value="transfer" <?php echo ($filterType == 'transfer') ? 'selected' : ''; ?>><?php echo $t['transfer']; ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 <?php echo getLangClass($lang); ?>">
                            <i class="bi bi-search me-2"></i><?php echo $t['generate']; ?>
                        </button>
                    </div>
                </form>
                
                <!-- Quick Date Filters -->
                <div class="mt-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="text-muted me-2">Quick filters:</small>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-day me-1"></i><?php echo $t['today']; ?>
                            </a>
                            <a href="?from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-week me-1"></i><?php echo $t['last_7_days']; ?>
                            </a>
                            <a href="?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-month me-1"></i><?php echo $t['this_month']; ?>
                            </a>
                            <a href="?from=<?php echo date('Y-01-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-range me-1"></i><?php echo $t['this_year']; ?>
                            </a>
                            <a href="?from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-minus me-1"></i><?php echo $t['last_30_days']; ?>
                            </a>
                            <a href="?from=<?php echo date('Y-01-01', strtotime('-1 year')); ?>&to=<?php echo date('Y-12-31', strtotime('-1 year')); ?>" class="btn btn-outline-secondary btn-sm <?php echo getLangClass($lang); ?>">
                                <i class="bi bi-calendar-x me-1"></i><?php echo $t['last_year']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Header -->
        <div class="report-header text-center">
            <h2 class="mb-2 <?php echo getLangClass($lang); ?>"><i class="bi bi-file-earmark-text me-2"></i><?php echo $t['financial_report']; ?></h2>
            <p class="mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['date_range']; ?>: <?php echo date('M j, Y', strtotime($from)); ?> to <?php echo date('M j, Y', strtotime($to)); ?></p>
            <small>Generated on <?php echo date('F j, Y \\a\\t g:i A'); ?></small>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4 clearfix">
            <div class="col-md-3">
                <div class="card text-center collection-card">
                    <div class="card-body">
                        <div class="icon-circle collection-icon">
                            <i class="bi bi-arrow-down-circle"></i>
                        </div>
                        <h4 class="text-success fw-bold">৳<?php echo number_format($totalCollection, 0); ?></h4>
                        <p class="text-muted mb-0 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['collection']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center expense-card">
                    <div class="card-body">
                        <div class="icon-circle expense-icon">
                            <i class="bi bi-arrow-up-circle"></i>
                        </div>
                        <h4 class="text-danger fw-bold">৳<?php echo number_format($totalExpense, 0); ?></h4>
                        <p class="text-muted mb-0 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['expense']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center transfer-card">
                    <div class="card-body">
                        <div class="icon-circle transfer-icon">
                            <i class="bi bi-arrow-left-right"></i>
                        </div>
                        <h4 class="text-info fw-bold">৳<?php echo number_format($totalTransfers, 0); ?></h4>
                        <p class="text-muted mb-0 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['transfer']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center balance-card">
                    <div class="card-body">
                        <div class="icon-circle balance-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <h4 class="text-primary fw-bold">৳<?php echo number_format($balance, 0); ?></h4>
                        <p class="text-muted mb-0 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['net_balance']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 <?php echo getLangClass($lang); ?>"><i class="bi bi-table me-2"></i><?php echo $t['transaction_details']; ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if(count($transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['date']; ?></th>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['transaction_type']; ?></th>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['description']; ?></th>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['category']; ?></th>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['amount']; ?></th>
                                <th class="<?php echo getLangClass($lang); ?>"><?php echo $t['added_by']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transactions as $r): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($r['date'])); ?></td>
                                <td>
                                    <span class="badge <?php 
                                      echo $r['type'] == 'collection' ? 'bg-success' : 
                                           ($r['type'] == 'expense' ? 'bg-danger' : 'bg-primary'); 
                                    ?> <?php echo getLangClass($lang); ?>">
                                        <i class="bi bi-<?php 
                                          echo $r['type'] == 'collection' ? 'arrow-down' : 
                                               ($r['type'] == 'expense' ? 'arrow-up' : 'arrow-left-right'); 
                                        ?> me-1"></i>
                                        <?php 
                                          echo $r['type'] == 'collection' ? $t['collection'] : 
                                               ($r['type'] == 'expense' ? $t['expense'] : $t['transfer']); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php 
                                  if ($r['type'] == 'transfer' && isset($r['display_description'])) {
                                    echo htmlspecialchars($r['display_description']); // Use formatted transfer description
                                  } elseif ($r['type'] == 'transfer' && (strpos($r['description'], 'Transfer to') === 0 || strpos($r['description'], 'Transfer from') === 0)) {
                                    echo htmlspecialchars($r['description']); // Escape transfer descriptions
                                  } else {
                                    echo htmlspecialchars($r['description']); // Escape other descriptions
                                  }
                                ?></td>
                                <td>
                                    <?php if($r['category']): ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($r['category']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold <?php 
                                  echo $r['type'] == 'collection' ? 'text-success' : 
                                       ($r['type'] == 'expense' ? 'text-danger' : 'text-primary'); 
                                ?>">
                                    <?php 
                                      echo $r['type'] == 'collection' ? '+' : 
                                           ($r['type'] == 'expense' ? '-' : ''); 
                                    ?>৳<?php echo number_format($r['amount'], 0); ?>
                                </td>
                                <td><?php echo htmlspecialchars($r['added_by_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3 <?php echo getLangClass($lang); ?>"><?php echo $t['no_transactions']; ?></h5>
                    <p class="text-muted <?php echo getLangClass($lang); ?>"><?php echo $t['no_transactions_period']; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary me-2 <?php echo getLangClass($lang); ?>" onclick="window.print()">
                <i class="bi bi-printer me-2"></i><?php echo $t['print_report']; ?>
            </button>
            <a href="index.php" class="btn btn-secondary <?php echo getLangClass($lang); ?>">
                <i class="bi bi-arrow-left me-2"></i><?php echo $t['back_to_dashboard']; ?>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        </div>
    </div>
