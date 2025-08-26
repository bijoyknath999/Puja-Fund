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

// Handle transfer deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_transfer'])) {
    $transfer_id = intval($_POST['transfer_id']);
    
    $conn->begin_transaction();
    try {
        // Get transfer details first
        $stmt = $conn->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'completed'");
        $stmt->bind_param('i', $transfer_id);
        $stmt->execute();
        $transfer = $stmt->get_result()->fetch_assoc();
        
        if ($transfer) {
            // Delete corresponding transaction records
            $stmt_del_out = $conn->prepare("DELETE FROM transactions WHERE type = 'transfer' AND added_by = ? AND amount = ? AND date = ? AND description LIKE '%Transfer to%'");
            $stmt_del_out->bind_param('ids', $transfer['from_user_id'], $transfer['amount'], $transfer['transfer_date']);
            $stmt_del_out->execute();
            
            $stmt_del_in = $conn->prepare("DELETE FROM transactions WHERE type = 'transfer' AND added_by = ? AND amount = ? AND date = ? AND description LIKE '%Transfer from%'");
            $stmt_del_in->bind_param('ids', $transfer['to_user_id'], $transfer['amount'], $transfer['transfer_date']);
            $stmt_del_in->execute();
            
            // Delete the transfer record
            $stmt_del_transfer = $conn->prepare("DELETE FROM transfers WHERE id = ?");
            $stmt_del_transfer->bind_param('i', $transfer_id);
            $stmt_del_transfer->execute();
            
            $conn->commit();
            $_SESSION['success'] = 'Transfer deleted successfully. Both user balances have been updated.';
        } else {
            $_SESSION['error'] = 'Transfer not found or not completed.';
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Error deleting transfer: ' . $e->getMessage();
    }
    
    header('Location: transfers.php');
    exit();
}

// Get user balance function
function getUserBalance($user_id, $conn) {
    // Get collections
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'collection' AND added_by = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $collections = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get expenses
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense' AND added_by = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get incoming transfers
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'transfer' AND added_by = ? AND description LIKE '%Transfer from%'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $transferIn = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get outgoing transfers
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'transfer' AND added_by = ? AND description LIKE '%Transfer to%'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $transferOut = $stmt->get_result()->fetch_assoc()['total'];
    
    return $collections - $expenses + $transferIn - $transferOut;
}

// Get all transfers (completed, pending, cancelled)
$transfers_query = "
    SELECT t.*, 
           u1.name as from_user_name, 
           u2.name as to_user_name,
           u3.name as created_by_name
    FROM transfers t 
    JOIN users u1 ON t.from_user_id = u1.id 
    JOIN users u2 ON t.to_user_id = u2.id 
    JOIN users u3 ON t.created_by = u3.id 
    ORDER BY t.created_at DESC
";
$transfers_result = $conn->query($transfers_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['transfers']; ?> - Puja Fund</title>
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
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        .btn-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
        }
        .language-switcher {
            display: flex;
            align-items: center;
            margin-right: 1rem;
        }
        .language-switcher .btn {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
            margin: 0 0.25rem;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        .language-switcher .btn.active {
            background-color: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
        }
        .language-switcher .btn:hover {
            background-color: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-piggy-bank me-2"></i>Puja Fund
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i><?php echo $t['dashboard']; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="bi bi-list-ul me-1"></i><?php echo $t['transactions']; ?>
                        </a>
                    </li>
                    <?php if ($_SESSION['user']['role'] === 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people me-1"></i><?php echo $t['users']; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="approve_transfers.php">
                            <i class="bi bi-check-circle me-1"></i><?php echo $t['approve_transfers']; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="transfers.php">
                            <i class="bi bi-arrow-left-right me-1"></i><?php echo $t['transfers']; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">
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
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary" style="width: 50px; height: 50px;">
                                    <i class="bi bi-arrow-left-right text-white" style="font-size: 1.2rem;"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-semibold"><?php echo $t['transfers']; ?></h4>
                                <p class="text-muted mb-0">View all transfer history and manage completed transfers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

        <!-- Transfers Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo $t['date']; ?></th>
                                <th><?php echo $t['from']; ?></th>
                                <th><?php echo $t['to']; ?></th>
                                <th><?php echo $t['amount']; ?></th>
                                <th><?php echo $t['description']; ?></th>
                                <th><?php echo $t['status']; ?></th>
                                <th><?php echo $t['created_by']; ?></th>
                                <th><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transfers_result->num_rows > 0): ?>
                                <?php while ($transfer = $transfers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($transfer['transfer_date'])); ?></td>
                                    <td>
                                        <span class="text-danger fw-bold">
                                            <?php echo htmlspecialchars($transfer['from_user_name']); ?>
                                        </span>
                                        <small class="text-muted d-block">
                                            Balance: ৳<?php echo number_format(getUserBalance($transfer['from_user_id'], $conn), 2); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="text-success fw-bold">
                                            <?php echo htmlspecialchars($transfer['to_user_name']); ?>
                                        </span>
                                        <small class="text-muted d-block">
                                            Balance: ৳<?php echo number_format(getUserBalance($transfer['to_user_id'], $conn), 2); ?>
                                        </small>
                                    </td>
                                    <td class="fw-bold">৳<?php echo number_format($transfer['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($transfer['description']); ?></td>
                                    <td>
                                        <?php if ($transfer['status'] == 'completed'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i><?php echo $t['completed']; ?>
                                            </span>
                                        <?php elseif ($transfer['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-clock me-1"></i><?php echo $t['pending']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle me-1"></i><?php echo $t['cancelled']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($transfer['created_by_name']); ?></td>
                                    <td>
                                        <?php if ($transfer['status'] == 'completed'): ?>
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $transfer['id']; ?>, '<?php echo htmlspecialchars($transfer['from_user_name']); ?>', '<?php echo htmlspecialchars($transfer['to_user_name']); ?>', <?php echo $transfer['amount']; ?>)">
                                                <i class="bi bi-trash me-1"></i><?php echo $t['delete']; ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-arrow-left-right display-4 d-block mb-3"></i>
                                            <h5>No transfers found</h5>
                                            <p>No transfer records available.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                        <?php echo $t['confirm_delete']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Warning:</strong> This will permanently delete the transfer and update both user balances.</p>
                    <div id="deleteDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $t['cancel']; ?></button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="transfer_id" id="deleteTransferId">
                        <button type="submit" name="delete_transfer" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i><?php echo $t['delete']; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(transferId, fromUser, toUser, amount) {
            document.getElementById('deleteTransferId').value = transferId;
            document.getElementById('deleteDetails').innerHTML = 
                '<div class="alert alert-info">' +
                '<strong>Transfer Details:</strong><br>' +
                'From: <span class="text-danger fw-bold">' + fromUser + '</span><br>' +
                'To: <span class="text-success fw-bold">' + toUser + '</span><br>' +
                'Amount: <strong>৳' + parseFloat(amount).toLocaleString() + '</strong>' +
                '</div>';
            
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
