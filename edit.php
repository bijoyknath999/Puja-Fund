<?php
include 'auth.php';
include 'db.php';
include 'lang.php';
include 'categories.php';

$lang = getCurrentLanguage();
$t = getTranslations($lang);

// Allow managers and users to edit their own transactions
$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();

if (!$tx) {
    header('Location: transactions.php');
    exit();
}

// Check if user can edit this transaction
if ($_SESSION['user']['role'] !== 'manager' && $tx['added_by'] != $_SESSION['user']['id']) {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];
    $category = $_POST['category'] ?? null;
    
    // Prevent editing transfer transactions
    if ($tx['type'] == 'transfer') {
        $message = 'Transfer transactions cannot be edited. Please delete and create a new transfer if needed.';
        $messageType = 'error';
    } elseif ($description && $amount > 0 && $date) {
        // Check balance for expense transactions
        if ($type == 'expense') {
            // Get user's current balance (excluding the current transaction being edited)
            $user_id = $tx['added_by'];
            $balanceStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) as collections,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND id != ? THEN amount ELSE 0 END), 0) as expenses,
                    COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) as transfer_in,
                    COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as transfer_out
                FROM transactions 
                WHERE added_by = ?
            ");
            $balanceStmt->bind_param('ii', $id, $user_id);
            $balanceStmt->execute();
            $balanceData = $balanceStmt->get_result()->fetch_assoc();
            
            $current_balance = $balanceData['collections'] + $balanceData['transfer_in'] - $balanceData['transfer_out'] - $balanceData['expenses'];
            
            if ($current_balance < $amount) {
                $message = "Insufficient balance! User's current balance is ৳" . number_format($current_balance, 2) . " but you're trying to set an expense of ৳" . number_format($amount, 2);
                $messageType = 'error';
            } else {
                $u = $conn->prepare("UPDATE transactions SET type=?, description=?, amount=?, date=?, category=? WHERE id=?");
                $u->bind_param('ssdssi', $type, $description, $amount, $date, $category, $id);
                if ($u->execute()) {
                    header('Location: transactions.php?updated=1');
                    exit();
                } else {
                    $message = 'Error updating transaction.';
                    $messageType = 'error';
                }
            }
        } else {
            $u = $conn->prepare("UPDATE transactions SET type=?, description=?, amount=?, date=?, category=? WHERE id=?");
            $u->bind_param('ssdssi', $type, $description, $amount, $date, $category, $id);
            if ($u->execute()) {
                header('Location: transactions.php?updated=1');
                exit();
            } else {
                $message = 'Error updating transaction.';
                $messageType = 'error';
            }
        }
    } else {
        $message = 'Please fill all required fields.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title><?php echo $t['page_title_edit_transaction']; ?></title>
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
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i>
            <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item <?php echo getLangClass($lang); ?>" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i><?php echo $t['logout']; ?></a></li>
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
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning" style="width: 50px; height: 50px;">
                    <i class="bi bi-pencil text-white" style="font-size: 1.2rem;"></i>
                  </div>
                </div>
                <div>
                  <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['edit_transaction']; ?></h4>
                  <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['update_transaction_details']; ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <a href="transactions.php" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>">
                <i class="bi bi-arrow-left me-2"></i><?php echo $t['back_to_transactions']; ?>
              </a>
            </div>
          </div>
        </div>
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

  <!-- Edit Form -->
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <form method="POST">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-arrow-left-right me-2"></i><?php echo $t['transaction_type']; ?>
                </label>
                <?php if ($tx['type'] == 'transfer'): ?>
                  <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Transfer transactions cannot be edited. Please delete and create a new transfer if needed.
                  </div>
                  <input type="hidden" name="type" value="transfer">
                  <div class="btn-group w-100" role="group">
                    <span class="btn btn-outline-primary disabled">
                      <i class="bi bi-arrow-left-right me-1"></i><?php echo $t['transfer']; ?>
                    </span>
                  </div>
                <?php else: ?>
                  <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="type" id="collection" value="collection" <?php echo $tx['type'] == 'collection' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-success <?php echo getLangClass($lang); ?>" for="collection">
                      <i class="bi bi-arrow-down-circle me-1"></i><?php echo $t['collection']; ?>
                    </label>
                    <input type="radio" class="btn-check" name="type" id="expense" value="expense" <?php echo $tx['type'] == 'expense' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-danger <?php echo getLangClass($lang); ?>" for="expense">
                      <i class="bi bi-arrow-up-circle me-1"></i><?php echo $t['expense']; ?>
                    </label>
                  </div>
                <?php endif; ?>
              </div>
              
              <div class="col-md-6">
                <label for="amount" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-currency-rupee me-2"></i><?php echo $t['amount']; ?>
                </label>
                <div class="input-group">
                  <span class="input-group-text">৳</span>
                  <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" value="<?php echo $tx['amount']; ?>" <?php echo $tx['type'] == 'transfer' ? 'readonly' : 'required'; ?>>
                </div>
              </div>
              
              <div class="col-12">
                <label for="description" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-card-text me-2"></i><?php echo $t['description']; ?>
                </label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="<?php echo $t['brief_description']; ?>" <?php echo $tx['type'] == 'transfer' ? 'readonly' : 'required'; ?>><?php 
                  if ($tx['type'] == 'transfer' && (strpos($tx['description'], 'Transfer to') === 0 || strpos($tx['description'], 'Transfer from') === 0)) {
                    echo htmlspecialchars($tx['description']); // Escape transfer descriptions for editing
                  } else {
                    echo htmlspecialchars($tx['description']); // Escape other descriptions
                  }
                ?></textarea>
              </div>
              
              <div class="col-md-6">
                <label for="date" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-calendar me-2"></i><?php echo $t['date']; ?>
                </label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $tx['date']; ?>" <?php echo $tx['type'] == 'transfer' ? 'readonly' : 'required'; ?>>
              </div>
              
              <div class="col-md-6" id="categoryGroup" style="display: <?php echo $tx['type'] == 'expense' ? 'block' : 'none'; ?>;">
                <label for="category" class="form-label fw-semibold <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-tag me-2"></i><?php echo $t['category']; ?>
                </label>
                <select class="form-select" id="category" name="category">
                  <?php echo renderCategoryOptions($tx['category'], $lang, $t['select_category']); ?>
                </select>
              </div>
            </div>
            
            <div class="row mt-4">
              <div class="col-12 d-flex flex-column flex-md-row justify-content-end gap-2">
                <a href="transactions.php" class="btn btn-outline-secondary <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-x-circle me-2"></i><?php echo $t['cancel']; ?>
                </a>
                <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>" <?php echo $tx['type'] == 'transfer' ? 'disabled' : ''; ?>>
                  <i class="bi bi-check-circle me-2"></i><?php echo $t['update_transaction']; ?>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Show/hide category based on transaction type
  document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', function() {
      const categoryGroup = document.getElementById('categoryGroup');
      if (this.value === 'expense') {
        categoryGroup.style.display = 'block';
      } else {
        categoryGroup.style.display = 'none';
        document.getElementById('category').value = '';
      }
    });
  });
</script>
</body>
</html>
