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
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) ($_GET['search'] ?? ''));

        if ($search !== '' && strlen($search) < 2) {
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

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE i.invoice_number LIKE ? OR c.name LIKE ? OR c.pppoe_username LIKE ? OR c.phone LIKE ?";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
        }

        $invoices = fetchAll("
            SELECT i.*, c.name as customer_name, c.pppoe_username 
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.id 
            {$where}
            ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC 
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        $totalResult = fetchOne("SELECT COUNT(*) as total FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id {$where}", $params);
        $total = $totalResult['total'] ?? 0;

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
