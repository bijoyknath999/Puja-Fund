<?php
// Per-user transfer balance, shared by the web app and api/*.php so both
// enforce the exact same rule.
//
// A user can transfer out of their balance for the transfer's year:
//   collections - expenses + transfers_in - transfers_out   (dated in that year)
// which is the same number profile.php / api/profile.php show for the year.
// Pending outgoing transfer requests for that year are already spoken for,
// so they are held back from what's available.

function getUserTransferBalance($conn, $user_id, $year, $exclude_transfer_id = 0) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) +
            COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as balance
        FROM transactions
        WHERE added_by = ? AND YEAR(date) = ?
    ");
    $stmt->bind_param('ii', $user_id, $year);
    $stmt->execute();
    $balance = round(floatval($stmt->get_result()->fetch_assoc()['balance']), 2);

    $pendingStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as pending
        FROM transfers
        WHERE from_user_id = ? AND status = 'pending' AND YEAR(transfer_date) = ? AND id != ?
    ");
    $pendingStmt->bind_param('iii', $user_id, $year, $exclude_transfer_id);
    $pendingStmt->execute();
    $pending = round(floatval($pendingStmt->get_result()->fetch_assoc()['pending']), 2);

    return [
        'balance' => $balance,
        'pending' => $pending,
        'available' => round($balance - $pending, 2),
    ];
}

// Returns null when the transfer is allowed, otherwise the error message.
function transferBalanceError($conn, $user_id, $amount, $date, $exclude_transfer_id = 0) {
    $year = intval(substr($date, 0, 4));
    $b = getUserTransferBalance($conn, $user_id, $year, $exclude_transfer_id);
    if (round($amount, 2) <= $b['available']) {
        return null;
    }
    $msg = 'Insufficient balance. Your ' . $year . ' balance is ৳' . number_format($b['balance'], 2);
    if ($b['pending'] > 0) {
        $msg .= ' (৳' . number_format($b['pending'], 2) . ' is held by pending transfer requests, ৳' .
            number_format($b['available'], 2) . ' available)';
    }
    return $msg . ' but the transfer is ৳' . number_format($amount, 2);
}
