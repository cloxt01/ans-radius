<?php

/**
 * VPN database connection
 */
function vpnDbConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        $config = [
            'host' => defined('VPN_DB_HOST') ? VPN_DB_HOST : 'localhost',
            'database' => defined('VPN_DB_NAME') ? VPN_DB_NAME : 'vpndb',
            'username' => defined('VPN_DB_USER') ? VPN_DB_USER : 'vpnuser',
            'password' => defined('VPN_DB_PASS') ? VPN_DB_PASS : 'vpnpass',
        ];

        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
function fetchOneVpn($query, $params = [])
{
    $pdo = vpnDbConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

function checkVpnUser($username)
{
    $user = fetchOneVpn("SELECT * FROM vpndb WHERE username = ?", [$username]);
    return $user !== false;
}
function upsertVpnUser($data)
{
    $pdo = vpnDbConnection();
    $stmt = $pdo->prepare("INSERT INTO vpndb (username, password, created_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), updated_at = VALUES(updated_at)");
    return $stmt->execute([$data['username'], $data['password'], date('Y-m-d H:i:s')]) ? true : false;
}
