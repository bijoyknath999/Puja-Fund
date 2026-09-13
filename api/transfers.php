<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);
requireManager($user);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $activeYear = getActiveYear($conn);
    $year = isset($_GET['year']) ? intval($_GET['year']) : $activeYear;

    $stmt = $conn->prepare("
        SELECT t.*, u1.name as from_user_name, u2.name as to_user_name
        FROM transfers t
        JOIN users u1 ON t.from_user_id = u1.id
        JOIN users u2 ON t.to_user_id = u2.id
        WHERE YEAR(t.transfer_date) = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $transfers = [];
    while ($row = $res->fetch_assoc()) {
        $transfers[] = formatTransferRow($row);
    }

    jsonSuccess(['transfers' => $transfers]);
} elseif ($method === 'POST') {
    // ?id=123&action=approve|reject
    $id = intval($_GET['id'] ?? 0);
    $action = $_GET['action'] ?? '';
    if (!$id || !in_array($action, ['approve', 'reject'], true)) {
        jsonError('id and a valid action (approve|reject) are required', 400);
    }

    if ($action === 'approve') {
        // Same side-effects as approve_transfers.php: two INSERT INTO transactions rows,
        // then mark the transfer completed.
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $transfer = $stmt->get_result()->fetch_assoc();

            if (!$transfer) {
                $conn->rollback();
                jsonError('Transfer not found or already processed', 404);
            }

            $transferDescOut = "Transfer to " . getUserNameById($conn, $transfer['to_user_id']) . " : " . $transfer['description'];
            $stmtOut = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES ('transfer', ?, ?, ?, NULL, ?)");
            $stmtOut->bind_param('sdsi', $transferDescOut, $transfer['amount'], $transfer['transfer_date'], $transfer['from_user_id']);
            $stmtOut->execute();

            $transferDescIn = "Transfer from " . getUserNameById($conn, $transfer['from_user_id']) . " : " . $transfer['description'];
            $stmtIn = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES ('transfer', ?, ?, ?, NULL, ?)");
            $stmtIn->bind_param('sdsi', $transferDescIn, $transfer['amount'], $transfer['transfer_date'], $transfer['to_user_id']);
            $stmtIn->execute();

            $stmtUpd = $conn->prepare("UPDATE transfers SET status = 'completed' WHERE id = ?");
            $stmtUpd->bind_param('i', $id);
            $stmtUpd->execute();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            jsonError('Error processing transfer: ' . $e->getMessage(), 500);
        }
    } else {
        // reject -> cancelled, same as approve_transfers.php
        $stmt = $conn->prepare("UPDATE transfers SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            jsonError('Transfer not found or already processed', 404);
        }
    }

    $row = fetchTransferByIdWithNames($conn, $id);
    jsonSuccess(['transfer' => formatTransferRow($row)]);
} elseif ($method === 'DELETE') {
    // Deletes a completed transfer and its two linked transaction rows (same as transfers.php's delete)
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonError('id is required', 400);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'completed'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $transfer = $stmt->get_result()->fetch_assoc();

        if (!$transfer) {
            $conn->rollback();
            jsonError('Transfer not found or not completed', 404);
        }

        $delOut = $conn->prepare("DELETE FROM transactions WHERE type = 'transfer' AND added_by = ? AND amount = ? AND date = ? AND description LIKE '%Transfer to%'");
        $delOut->bind_param('ids', $transfer['from_user_id'], $transfer['amount'], $transfer['transfer_date']);
        $delOut->execute();

        $delIn = $conn->prepare("DELETE FROM transactions WHERE type = 'transfer' AND added_by = ? AND amount = ? AND date = ? AND description LIKE '%Transfer from%'");
        $delIn->bind_param('ids', $transfer['to_user_id'], $transfer['amount'], $transfer['transfer_date']);
        $delIn->execute();

        $delTransfer = $conn->prepare("DELETE FROM transfers WHERE id = ?");
        $delTransfer->bind_param('i', $id);
        $delTransfer->execute();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        jsonError('Error deleting transfer: ' . $e->getMessage(), 500);
    }

    jsonSuccess(null, 200);
} else {
    jsonError('Method not allowed', 400);
}

function fetchTransferByIdWithNames($conn, $id) {
    $stmt = $conn->prepare("SELECT t.*, u1.name as from_user_name, u2.name as to_user_name FROM transfers t JOIN users u1 ON t.from_user_id = u1.id JOIN users u2 ON t.to_user_id = u2.id WHERE t.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
