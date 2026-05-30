<?php
/**
 * API: Invoices
 */

header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    // $page = $_GET['page'] ?? 1; // Moved inside GET block and casted
    // $perPage = $_GET['per_page'] ?? 20; // Moved inside GET block and casted

    if ($method === 'GET') {
        // Get invoices with pagination
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(500, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) ($_GET['search'] ?? ''));
        $filter_status = trim((string)($_GET['filter_status'] ?? ''));
        $filter_due_from = trim((string)($_GET['filter_due_from'] ?? ''));
        $filter_due_to = trim((string)($_GET['filter_due_to'] ?? ''));
        $filter_created_from = trim((string)($_GET['filter_created_from'] ?? ''));
        $filter_created_to = trim((string)($_GET['filter_created_to'] ?? ''));
        $filter_paid_from = trim((string)($_GET['filter_paid_from'] ?? ''));
        $filter_paid_to = trim((string)($_GET['filter_paid_to'] ?? ''));

        if ($search !== '' && strlen($search) < 2 && $filter_status === '' && $filter_due_from === '' && $filter_due_to === '' && $filter_created_from === '' && $filter_created_to === '' && $filter_paid_from === '' && $filter_paid_to === '') {
            echo json_encode([
                'success' => true,
                'data' => [
                    'invoices' => [],
                    'total' => 0,
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalPages' => 0
                ],
                'message' => 'Ketik minimal 2 karakter untuk mencari'
            ]);
            exit;
        }

        $whereParts = ['1=1'];
        $params = [];

        if ($filter_status !== '') {
            if ($filter_status === 'telat') {
                $whereParts[] = "i.status = 'unpaid'";
                $whereParts[] = 'i.due_date < CURDATE()';
            } elseif ($filter_status === 'unpaid') {
                $whereParts[] = "i.status = 'unpaid'";
                $whereParts[] = 'i.due_date >= CURDATE()';
            } else {
                $whereParts[] = 'i.status = ?';
                $params[] = $filter_status;
            }
        }
        if ($filter_due_from !== '') {
            $whereParts[] = 'i.due_date >= ?';
            $params[] = $filter_due_from;
        }
        if ($filter_due_to !== '') {
            $whereParts[] = 'i.due_date <= ?';
            $params[] = $filter_due_to;
        }
        if ($filter_created_from !== '') {
            $whereParts[] = 'i.created_at >= ?';
            $params[] = $filter_created_from . ' 00:00:00';
        }
        if ($filter_created_to !== '') {
            $whereParts[] = 'i.created_at <= ?';
            $params[] = $filter_created_to . ' 23:59:59';
        }
        if ($filter_paid_from !== '') {
            $whereParts[] = 'i.paid_at >= ?';
            $params[] = $filter_paid_from . ' 00:00:00';
        }
        if ($filter_paid_to !== '') {
            $whereParts[] = 'i.paid_at <= ?';
            $params[] = $filter_paid_to . ' 23:59:59';
        }
        if ($search !== '' && strlen($search) >= 2) {
            // Support searching by invoice number, customer name, pppoe username,
            // phone, numeric amount, and status. Also allow common Indonesian
            // status keywords (e.g. "lunas", "telat") to match DB status values.
            $searchLower = mb_strtolower($search);
            $mappedStatus = null;
            $statusMap = [
                'lunas' => 'paid',
                'paid' => 'paid',
                'lun' => 'paid',
                'belum bayar' => 'unpaid',
                'unpaid' => 'unpaid',
                'telat' => 'unpaid'
            ];

            foreach ($statusMap as $k => $v) {
                if (mb_strpos($searchLower, $k) !== false) {
                    $mappedStatus = $v;
                    break;
                }
            }

            $whereParts[] = '(i.invoice_number LIKE ? OR c.name LIKE ? OR c.pppoe_username LIKE ? OR c.phone LIKE ? OR CAST(i.amount AS CHAR) LIKE ? OR i.status LIKE ?' . ($mappedStatus ? ' OR i.status = ?' : '') . ')';
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like; // amount
            $params[] = $like; // status LIKE

            if ($mappedStatus) {
                $params[] = $mappedStatus;
            }
        }

        $where = 'WHERE ' . implode(' AND ', $whereParts);

        $selectSql = "SELECT i.*, c.name as customer_name, c.pppoe_username, c.phone 
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.id 
            {$where}
            ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC 
            LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as total FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id {$where}";;

        // Debug: log the SELECT SQL and bound params to help diagnose
        // cases where COUNT > 0 but SELECT returns an empty array.
        $debugLogPath = __DIR__ . '/../logs/invoices_api_debug.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] SELECT: " . $selectSql . PHP_EOL
            . "PARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($debugLogPath, $logEntry, FILE_APPEND);

        $invoices = fetchAll($selectSql, $params);

        // Log number of rows returned by SELECT
        @file_put_contents($debugLogPath, "[" . date('Y-m-d H:i:s') . "] ROWS: " . count($invoices) . PHP_EOL, FILE_APPEND);

        // Also log COUNT SQL and params
        @file_put_contents($debugLogPath, "[" . date('Y-m-d H:i:s') . "] COUNT: " . $countSql . PHP_EOL, FILE_APPEND);
        @file_put_contents($debugLogPath, "[" . date('Y-m-d H:i:s') . "] COUNT_PARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

        $totalResult = fetchOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        // If COUNT > 0 but SELECT returned no rows, try SELECT without LIMIT to diagnose
        if ($total > 0 && count($invoices) === 0) {
            $selectNoLimit = "SELECT i.*, c.name as customer_name, c.pppoe_username, c.phone 
                FROM invoices i 
                LEFT JOIN customers c ON i.customer_id = c.id 
                {$where}
                ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC";

            $allRows = fetchAll($selectNoLimit, $params);
            @file_put_contents($debugLogPath, "[" . date('Y-m-d H:i:s') . "] NO_LIMIT_ROWS: " . count($allRows) . PHP_EOL, FILE_APPEND);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ]
        ]);
    }

} catch (Exception $e) {
    logError("API Error (invoices.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
