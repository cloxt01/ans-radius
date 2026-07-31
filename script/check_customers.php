<?php

require_once '../includes/db.php';
require_once '../includes/mikrotik_api.php';

// Ambil semua customer real
$customers = fetchAll("
    SELECT c.pppoe_username
    FROM customers c
    LEFT JOIN fiktif_customers fc
        ON fc.customer_id = c.id
    WHERE fc.customer_id IS NULL
");

$dbUsers = [];

foreach ($customers as $customer) {
    $dbUsers[strtolower(trim($customer['pppoe_username']))] = true;
}

$totalRealCustomer = count($dbUsers);

// Ambil semua router
$routers = fetchAll("
    SELECT id, name
    FROM routers
");

$totalActiveSession = 0;
$totalNotFound = 0;

foreach ($routers as $router) {

    echo PHP_EOL;
    echo "===== {$router['name']} =====" . PHP_EOL;

    $sessions = mikrotikGetActiveSessions($router['id']);

    foreach ($sessions as $session) {

        $totalActiveSession++;

        $username = strtolower(trim($session['name']));

        if (!isset($dbUsers[$username])) {

            $totalNotFound++;

            printf(
                "%-30s %-15s %-15s\n",
                $session['name'],
                $session['address'] ?? '-',
                $session['uptime'] ?? '-'
            );
        }
    }
}

echo PHP_EOL;
echo "======================================" . PHP_EOL;
echo "Total Customer Real      : {$totalRealCustomer}" . PHP_EOL;
echo "Total Active Session     : {$totalActiveSession}" . PHP_EOL;
echo "Tidak Ada di Database    : {$totalNotFound}" . PHP_EOL;
echo "======================================" . PHP_EOL;
