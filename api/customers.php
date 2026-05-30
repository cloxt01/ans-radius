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
    $perPage = min(500, max(1, (int) ($_GET['per_page'] ?? 20)));
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

            // Accept filter params from client
            $filter_status = trim((string)($_GET['filter_status'] ?? ''));
            $filter_package = isset($_GET['filter_package']) && $_GET['filter_package'] !== '' ? (int)$_GET['filter_package'] : null;
            $filter_router = isset($_GET['filter_router']) && $_GET['filter_router'] !== '' ? (int)$_GET['filter_router'] : null;
            $filter_tech = isset($_GET['filter_tech']) && $_GET['filter_tech'] !== '' ? (int)$_GET['filter_tech'] : null;
            $filter_last_paid_from = trim((string)($_GET['filter_last_paid_from'] ?? ''));
            $filter_last_paid_to = trim((string)($_GET['filter_last_paid_to'] ?? ''));
            $filter_isolation_from = isset($_GET['filter_isolation_from']) && $_GET['filter_isolation_from'] !== '' ? (int)$_GET['filter_isolation_from'] : null;
            $filter_isolation_to = isset($_GET['filter_isolation_to']) && $_GET['filter_isolation_to'] !== '' ? (int)$_GET['filter_isolation_to'] : null;
            $filter_register_from = trim((string)($_GET['filter_register_from'] ?? ''));
            $filter_register_to = trim((string)($_GET['filter_register_to'] ?? ''));

            $searchTrimmed = trim((string)$search);
            // If no filters provided and search shorter than 2, ask user to type more
            if ($searchTrimmed !== '' && strlen($searchTrimmed) < 2 && empty($filter_status) && empty($filter_package) && empty($filter_router) && empty($filter_tech) && empty($filter_last_paid_from) && empty($filter_last_paid_to) && empty($filter_isolation_from) && empty($filter_isolation_to) && empty($filter_register_from) && empty($filter_register_to)) {
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

            $whereParts = ['1=1'];

            if ($filter_status !== '') {
                $whereParts[] = 'c.status = ?';
                $params[] = $filter_status;
            }
            if ($filter_package) {
                $whereParts[] = 'c.package_id = ?';
                $params[] = $filter_package;
            }
            if ($filter_router) {
                $whereParts[] = 'c.router_id = ?';
                $params[] = $filter_router;
            }
            if ($filter_tech) {
                $whereParts[] = 'c.installed_by = ?';
                $params[] = $filter_tech;
            }

            if ($filter_last_paid_from !== '') {
                $whereParts[] = "(SELECT MAX(i.due_date) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') >= ?";
                $params[] = $filter_last_paid_from . ' 00:00:00';
            }
            if ($filter_last_paid_to !== '') {
                $whereParts[] = "(SELECT MAX(i.due_date) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') <= ?";
                $params[] = $filter_last_paid_to . ' 23:59:59';
            }

            if ($filter_isolation_from !== null) {
                $whereParts[] = 'c.isolation_date >= ?';
                $params[] = $filter_isolation_from;
            }
            if ($filter_isolation_to !== null) {
                $whereParts[] = 'c.isolation_date <= ?';
                $params[] = $filter_isolation_to;
            }

            if ($filter_register_from !== '') {
                $whereParts[] = 'c.created_at >= ?';
                $params[] = $filter_register_from . ' 00:00:00';
            }
            if ($filter_register_to !== '') {
                $whereParts[] = 'c.created_at <= ?';
                $params[] = $filter_register_to . ' 23:59:59';
            }

            if ($searchTrimmed !== '' && strlen($searchTrimmed) >= 2) {
                $whereParts[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.pppoe_username LIKE ?)';
                $like = "%{$searchTrimmed}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $where = 'WHERE ' . implode(' AND ', $whereParts);

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
