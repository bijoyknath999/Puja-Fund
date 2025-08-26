<?php
include 'auth.php';
include 'db.php';
include 'lang.php';

// Check if user is manager
if($_SESSION['user']['role'] != 'manager') {
    header('Location: index.php');
    exit();
}

$lang = getCurrentLanguage();
$t = getTranslations($lang);

$message = '';
$messageType = '';

// Helper function to get user name
function getUserName($user_id, $conn) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['name'] : 'Unknown User';
}

// Handle transfer approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $transfer_id = intval($_POST['transfer_id']);
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        $conn->begin_transaction();
        try {
            // Get transfer details
            $stmt = $conn->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('i', $transfer_id);
            $stmt->execute();
            $transfer = $stmt->get_result()->fetch_assoc();
            
            if ($transfer) {
                // Create transfer transaction for sender (outgoing)
                $transfer_desc = "Transfer to " . getUserName($transfer['to_user_id'], $conn) . " : " . $transfer['description'];
                $stmt_out = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES ('transfer', ?, ?, ?, NULL, ?)");
                $stmt_out->bind_param('sdsi', $transfer_desc, $transfer['amount'], $transfer['transfer_date'], $transfer['from_user_id']);
                $stmt_out->execute();
                
                // Create transfer transaction for receiver (incoming)
                $transfer_desc_in = "Transfer from " . getUserName($transfer['from_user_id'], $conn) . " : " . $transfer['description'];
                $stmt_in = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES ('transfer', ?, ?, ?, NULL, ?)");
                $stmt_in->bind_param('sdsi', $transfer_desc_in, $transfer['amount'], $transfer['transfer_date'], $transfer['to_user_id']);
                $stmt_in->execute();
                
                // Update transfer status to completed
                $stmt_update = $conn->prepare("UPDATE transfers SET status = 'completed' WHERE id = ?");
                $stmt_update->bind_param('i', $transfer_id);
                $stmt_update->execute();
                
                $conn->commit();
                $message = 'Transfer approved and processed successfully';
                $messageType = 'success';
            } else {
                $message = 'Transfer not found or already processed';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error processing transfer: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action == 'reject') {
        try {
            $stmt = $conn->prepare("UPDATE transfers SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('i', $transfer_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Transfer rejected successfully';
                $messageType = 'success';
            } else {
                $message = 'Transfer not found or already processed';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error rejecting transfer: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get pending transfers
$pending_query = "
    SELECT t.*, 
           u1.name as from_user_name, 
           u2.name as to_user_name,
           u3.name as created_by_name
    FROM transfers t 
    JOIN users u1 ON t.from_user_id = u1.id 
    JOIN users u2 ON t.to_user_id = u2.id 
    JOIN users u3 ON t.created_by = u3.id 
    WHERE t.status = 'pending' 
    ORDER BY t.created_at DESC
";
$pending_transfers = $conn->query($pending_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Transfers - <?php echo $t['app_name']; ?></title>
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
        .alert {
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
          <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="approve_transfers.php">
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
                    <i class="bi bi-check-circle text-white" style="font-size: 1.2rem;"></i>
                  </div>
                </div>
                <div>
                  <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['approve_transfers']; ?></h4>
                  <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['approve_transfers_desc']; ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <a href="transactions.php" class="btn btn-outline-primary <?php echo getLangClass($lang); ?>">
                  <i class="bi bi-list-ul me-2"></i><?php echo $t['transactions']; ?>
                </a>
              </div>
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

  <!-- Pending Transfers Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 <?php echo getLangClass($lang); ?>">
            <i class="bi bi-clock me-2"></i>
            <?php echo $t['pending_transfers']; ?> (<?php echo $pending_transfers->num_rows; ?>)
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if ($pending_transfers->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>From</th>
                  <th>To</th>
                  <th>Amount</th>
                  <th>Description</th>
                  <th>Requested By</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while($transfer = $pending_transfers->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="fw-semibold"><?php echo date('M j, Y', strtotime($transfer['transfer_date'])); ?></span>
                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($transfer['created_at'])); ?></small>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?php echo strtoupper(substr($transfer['from_user_name'], 0, 2)); ?>
                      </div>
                      <span><?php echo htmlspecialchars($transfer['from_user_name']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?php echo strtoupper(substr($transfer['to_user_name'], 0, 2)); ?>
                      </div>
                      <span><?php echo htmlspecialchars($transfer['to_user_name']); ?></span>
                    </div>
                  </td>
                  <td class="text-primary fw-semibold">৳<?php echo number_format($transfer['amount'], 2); ?></td>
                  <td><?php echo htmlspecialchars($transfer['description']); ?></td>
                  <td>
                    <small class="text-muted"><?php echo htmlspecialchars($transfer['created_by_name']); ?></small>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="transfer_id" value="<?php echo $transfer['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-outline-success" onclick="return confirm('<?php echo $t['confirm_approve_transfer']; ?>')">
                          <i class="bi bi-check-lg"></i>
                        </button>
                      </form>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="transfer_id" value="<?php echo $transfer['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('<?php echo $t['confirm_reject_transfer']; ?>')">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
            <h5 class="mt-3 <?php echo getLangClass($lang); ?>"><?php echo $t['no_pending_transfers']; ?></h5>
            <p class="text-muted <?php echo getLangClass($lang); ?>"><?php echo $t['all_transfers_processed']; ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
