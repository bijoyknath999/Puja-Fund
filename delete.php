<?php
include 'auth.php';
include 'db.php';
if ($_SESSION['user']['role'] !== 'manager') die('Access denied');
$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();

if ($transaction['type'] == 'transfer') {
    // For transfers, we need to delete both the outgoing and incoming transaction records
    $transfer_amount = $transaction['amount'];
    $transfer_date = $transaction['date'];
    
    // Delete both transfer transactions based on amount, date, and description patterns
    if (strpos($transaction['description'], 'Transfer to') !== false) {
        // This is an outgoing transfer, find and delete both
        preg_match('/Transfer to <span[^>]*>([^<]+)<\/span>/', $transaction['description'], $matches);
        if (isset($matches[1])) {
            $to_user_name = $matches[1];
            
            // Delete the outgoing transfer (current transaction)
            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            
            // Find and delete the corresponding incoming transfer
            $stmt_find = $conn->prepare("SELECT t.id FROM transactions t JOIN users u ON t.added_by = u.id WHERE t.type = 'transfer' AND t.amount = ? AND t.date = ? AND u.name = ? AND t.description LIKE '%Transfer from%'");
            $stmt_find->bind_param('dss', $transfer_amount, $transfer_date, $to_user_name);
            $stmt_find->execute();
            $result = $stmt_find->get_result();
            if ($incoming_transfer = $result->fetch_assoc()) {
                $stmt_del = $conn->prepare("DELETE FROM transactions WHERE id = ?");
                $stmt_del->bind_param('i', $incoming_transfer['id']);
                $stmt_del->execute();
            }
        }
    } elseif (strpos($transaction['description'], 'Transfer from') !== false) {
        // This is an incoming transfer, find and delete both
        preg_match('/Transfer from <span[^>]*>([^<]+)<\/span>/', $transaction['description'], $matches);
        if (isset($matches[1])) {
            $from_user_name = $matches[1];
            
            // Delete the incoming transfer (current transaction)
            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            
            // Find and delete the corresponding outgoing transfer
            $stmt_find = $conn->prepare("SELECT t.id FROM transactions t JOIN users u ON t.added_by = u.id WHERE t.type = 'transfer' AND t.amount = ? AND t.date = ? AND u.name = ? AND t.description LIKE '%Transfer to%'");
            $stmt_find->bind_param('dss', $transfer_amount, $transfer_date, $from_user_name);
            $stmt_find->execute();
            $result = $stmt_find->get_result();
            if ($outgoing_transfer = $result->fetch_assoc()) {
                $stmt_del = $conn->prepare("DELETE FROM transactions WHERE id = ?");
                $stmt_del->bind_param('i', $outgoing_transfer['id']);
                $stmt_del->execute();
            }
        }
    }
} else {
    // For regular transactions, just delete the single record
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
header('Location: transactions.php');
?>