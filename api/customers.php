<?php
/**
 * API: Customers
 */

header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $search = $_GET['search'] ?? '';
    $routersTableExists = tableExists('routers');

    if ($method === 'GET') {
        // Get password for a username
        if (isset($_GET['action']) && $_GET['action'] === 'get_password') {
            $username = $_GET['username'] ?? '';
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Username required']);
                exit;
            }
            
            $password = radiusGetUserPassword($username);
            
            echo json_encode([
                'success' => true,
                'password' => $password ?? '',
                'debug' => [
                    'username' => $username,
                    'provisioning_ready' => radiusUserProvisioningReady()
                ]
            ]);
            exit;
        }

        // Get single customer
        if (isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);

            if ($customer) {
                echo json_encode(['success' => true, 'data' => $customer]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
            }
            exit;
        }

        // Get customers with pagination
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];

        if (!empty($search) && strlen(trim($search)) < 2) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'customers' => [],
                    'total' => 0,
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalPages' => 0
                ],
                'message' => 'Ketik minimal 2 karakter untuk mencari'
            ]);
            exit;
        }

        if (!empty($search)) {
            $where = "WHERE c.name LIKE ? OR c.phone LIKE ? OR c.pppoe_username LIKE ?";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }

        $customers = fetchAll("
            SELECT c.*, 
                p.name as package_name,
                p.price as package_price,
                " . ($routersTableExists ? "r.name as router_name," : "'' as router_name,") . "
                (
                    SELECT MAX(i.due_date)
                    FROM invoices i
                    WHERE i.customer_id = c.id AND i.status = 'paid'
                ) as last_paid
            FROM customers c 
            LEFT JOIN packages p ON c.package_id = p.id 
            " . ($routersTableExists ? "LEFT JOIN routers r ON c.router_id = r.id" : "") . "
            {$where}
            ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC 
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        $totalResult = fetchOne("SELECT COUNT(*) as total FROM customers c {$where}", $params);
        $total = $totalResult['total'] ?? 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'customers' => $customers,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ]
        ]);
    }

} catch (Exception $e) {
    logError("API Error (customers.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
