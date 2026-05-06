<?php

/**
 * Radius database connection
 */
function radiusDbConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        $config = [
            'host' => defined('RADIUS_DB_HOST') ? RADIUS_DB_HOST : 'localhost',
            'database' => defined('RADIUS_DB_NAME') ? RADIUS_DB_NAME : 'radius',
            'username' => defined('RADIUS_DB_USER') ? RADIUS_DB_USER : 'root',
            'password' => defined('RADIUS_DB_PASS') ? RADIUS_DB_PASS : '',
        ];

        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function radiusBeginTransaction()
{
    radiusDbConnection()->beginTransaction();
}

function radiusCommit()
{
    radiusDbConnection()->commit();
}

function radiusRollback()
{
    radiusDbConnection()->rollBack();
}

function radiusQualifiedTable($table)
{
    $db = defined('RADIUS_DB_NAME') ? trim((string) RADIUS_DB_NAME) : '';
    if ($db === '') {
        return $table;
    }

    return '`' . str_replace('`', '', $db) . '`.`' . str_replace('`', '', (string) $table) . '`';
}

function radiusDisplayNas()
{
    try {
        if (function_exists('getNasList')) {
            return getNasList();
        }

        $pdo = radiusDbConnection();
        $stmt = $pdo->query('SELECT id, shortname, nasname, secret FROM nas ORDER BY id');
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logError('Failed to fetch NAS list: ' . $e->getMessage());
        return [];
    }
}

function radiusAddNas($name, $ip, $secret)
{
    $name = trim((string) $name);
    $ip = trim((string) $ip);
    $secret = trim((string) $secret);

    if ($name === '' || $ip === '' || $secret === '') {
        return false;
    }

    try {
        if (function_exists('addNas')) {
            return (bool) addNas($name, $ip, $secret);
        }

        $pdo = radiusDbConnection();
        $sql = "INSERT INTO nas (nasname, shortname, secret, type) VALUES (?, ?, ?, 'other') ON DUPLICATE KEY UPDATE shortname = VALUES(shortname), secret = VALUES(secret)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$ip, $name, $secret]);
    } catch (Exception $e) {
        logError('Failed to add NAS: ' . $e->getMessage());
        return false;
    }
}

function radiusDeleteNas($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    try {
        if (function_exists('deleteNas')) {
            return (bool) deleteNas($id);
        }

        $pdo = radiusDbConnection();
        $stmt = $pdo->prepare('DELETE FROM nas WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        logError('Failed to delete NAS: ' . $e->getMessage());
        return false;
    }
}

function radiusResolveUsernameById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    $table = radiusQualifiedTable('radcheck');
    $row = fetchOne("SELECT username FROM {$table} WHERE id = ? LIMIT 1", [$id]);

    return $row ? (string) ($row['username'] ?? '') : null;
}

function radiusRenameUser($oldUsername, $newUsername)
{
    $oldUsername = trim((string) $oldUsername);
    $newUsername = trim((string) $newUsername);

    if ($oldUsername === '' || $newUsername === '' || $oldUsername === $newUsername) {
        return true;
    }

    $radcheck = radiusQualifiedTable('radcheck');
    $radusergroup = radiusQualifiedTable('radusergroup');

    query("UPDATE {$radcheck} SET username = ? WHERE username = ?", [$newUsername, $oldUsername]);
    query("UPDATE {$radusergroup} SET username = ? WHERE username = ?", [$newUsername, $oldUsername]);

    return true;
}

function radiusSetUser($username, $password, $profile, $serviceType = 'Login-User', $replyAttributes = [])
{
    if (!radiusUserProvisioningReady()) {
        return false;
    }

    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }

    $password = (string) $password;
    $profile = trim((string) $profile);
    if ($profile === '') {
        $profile = 'default';
    }

    $radcheck = radiusQualifiedTable('radcheck');
    $radusergroup = radiusQualifiedTable('radusergroup');

    query("DELETE FROM {$radcheck} WHERE username = ? AND attribute IN ('Cleartext-Password','User-Password')", [$username]);
    query("INSERT INTO {$radcheck} (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)", [$username, $password]);

    query("DELETE FROM {$radusergroup} WHERE username = ?", [$username]);
    query("INSERT INTO {$radusergroup} (username, groupname, priority) VALUES (?, ?, 1)", [$username, $profile]);

    return true;
}

function radiusDeleteUser($username)
{
    if (!radiusUserProvisioningReady()) {
        return false;
    }

    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }

    $radcheck = radiusQualifiedTable('radcheck');
    $radusergroup = radiusQualifiedTable('radusergroup');

    query("DELETE FROM {$radcheck} WHERE username = ?", [$username]);
    query("DELETE FROM {$radusergroup} WHERE username = ?", [$username]);

    return true;
}

function radiusLooksLikeHotspotUser($user)
{
    $serviceType = strtolower(trim((string) ($user['service-type'] ?? $user['service_type'] ?? '')));
    if ($serviceType === '') {
        return true;
    }

    return $serviceType !== 'framed-user';
}

function radiusGetUsersByService($serviceType)
{
    if (!radiusUserProvisioningReady()) {
        return [];
    }

    $radcheck = radiusQualifiedTable('radcheck');
    $radusergroup = radiusQualifiedTable('radusergroup');
    $radgroupreply = radiusQualifiedTable('radgroupreply');
    $sql = "SELECT c.id,
            c.username,
            c.value AS password,
            COALESCE(ug.groupname, 'default') AS profile,
            -- Mengambil Auth-Type dari radcheck
            MAX(CASE WHEN c2.attribute = 'Auth-Type' THEN c2.value END) AS disabled,
            MAX(CASE WHEN rr.attribute = 'Service-Type' THEN rr.value END) AS service_type,
            MAX(CASE WHEN rr.attribute = 'Mikrotik-Comment' THEN rr.value END) AS user_comment,
            MAX(CASE WHEN rr.attribute = 'Session-Timeout' THEN rr.value END) AS session_timeout,
            COALESCE(MAX(CASE WHEN rr.attribute = 'Mikrotik-Total-Limit' THEN rr.value END),
                        MAX(CASE WHEN rr.attribute = 'Mikrotik-Recv-Limit' THEN rr.value END),
                        MAX(CASE WHEN rr.attribute = 'Mikrotik-Xmit-Limit' THEN rr.value END)) AS bytes_total,
            MAX(CASE WHEN rr.attribute = 'Mikrotik-Price' THEN rr.value END) AS voucher_price,
            MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) AS rate_limit,
            MAX(CASE WHEN rr.attribute = 'Idle-Timeout' THEN rr.value END) AS idle_timeout,
            MAX(CASE WHEN rr.attribute = 'Framed-Pool' THEN rr.value END) AS address_pool,
            MAX(CASE WHEN rr.attribute = 'Mikrotik-Parent-Queue' THEN rr.value END) AS parent_queue
        FROM {$radcheck} c
        -- Join ke diri sendiri untuk mencari atribut lain dengan username yang sama
        LEFT JOIN {$radcheck} c2 ON c2.username = c.username AND c2.attribute = 'Auth-Type'
        LEFT JOIN {$radusergroup} ug ON ug.username = c.username
        LEFT JOIN {$radgroupreply} rr ON rr.groupname = ug.groupname
        WHERE c.attribute IN ('Cleartext-Password', 'User-Password')
        GROUP BY c.id, c.username, c.value, ug.groupname
        ORDER BY c.id DESC";

    $rows = fetchAll($sql);
    $users = [];
    $targetService = strtolower(trim((string) $serviceType));

    foreach ($rows as $row) {
        $rowService = strtolower(trim((string) ($row['service_type'] ?? '')));
        if ($targetService !== '' && $rowService !== $targetService) {
            continue;
        }

        $serviceLabel = $targetService === 'framed-user' ? 'pppoe' : 'hotspot';
        $serviceTypeLabel = $targetService !== '' ? ($targetService === 'framed-user' ? 'Framed-User' : 'Login-User') : (string) ($row['service_type'] ?? '');

        $users[] = [
            '.id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['username'] ?? ''),
            'password' => (string) ($row['password'] ?? ''),
            'profile' => (string) ($row['profile'] ?? 'default'),
            'service-type' => $serviceTypeLabel,
            'service' => $serviceLabel,
            'comment' => (string) ($row['user_comment'] ?? ''),
            'limit-uptime' => (string) ($row['session_timeout'] ?? ''),
            'limit-bytes-total' => (string) ($row['bytes_total'] ?? ''),
            'price' => (string) ($row['voucher_price'] ?? ''),
            'disabled' => (strpos(strtolower((string) ($row['disabled'] ?? '')), 'reject') !== false) ? 'true' : 'false',
            'rate-limit' => (string) ($row['rate_limit'] ?? ''),
            'idle-timeout' => (string) ($row['idle_timeout'] ?? ''),
            'address-pool' => (string) ($row['address_pool'] ?? ''),
            'parent-queue' => (string) ($row['parent_queue'] ?? ''),
            'server' => '',
            'uptime' => '0s',
            'bytes-in' => 0,
            'bytes-out' => 0,
        ];
    }

    return $users;
}

function radiusGetHotspotUsers()
{
    return radiusGetUsersByService('Login-User');
}

function radiusHotspotProfileUsageCount($profileName)
{
    if (!radiusUserProvisioningReady()) {
        return 0;
    }

    $profileName = trim((string) $profileName);
    if ($profileName === '') {
        return 0;
    }

    $radusergroup = radiusQualifiedTable('radusergroup');
    $row = fetchOne("SELECT COUNT(*) AS total FROM {$radusergroup} WHERE groupname = ?", [$profileName]);

    return (int) ($row['total'] ?? 0);
}

function radiusGetHotspotProfilesCloud()
{
    if (!radiusUserProvisioningReady()) {
        return [];
    }

    $pdo = radiusDbConnection();
    $hotspotProfiles = [];

    try {
        $sql = "SELECT hp.id,
                       hp.profile_name,
                       hp.shared_users,
                       hp.rate_limit,
                       hp.session_timeout,
                       hp.idle_timeout,
                       hp.address_pool,
                       hp.price,
                       hp.selling_price,
                       hp.on_login,
                       hp.comment,
                       MAX(CASE WHEN rgr.attribute = 'Service-Type' THEN rgr.value END) AS service_type,
                       MAX(CASE WHEN rgr.attribute = 'Mikrotik-Rate-Limit' THEN rgr.value END) AS reply_rate_limit,
                       MAX(CASE WHEN rgr.attribute = 'Session-Timeout' THEN rgr.value END) AS reply_session_timeout,
                       MAX(CASE WHEN rgr.attribute = 'Idle-Timeout' THEN rgr.value END) AS reply_idle_timeout,
                       MAX(CASE WHEN rgr.attribute = 'Framed-Pool' THEN rgr.value END) AS reply_address_pool,
                       MAX(CASE WHEN rgr.attribute = 'Mikrotik-Comment' THEN rgr.value END) AS reply_comment
                FROM hotspot_profiles hp
                LEFT JOIN radgroupreply rgr ON rgr.groupname = hp.profile_name
                GROUP BY hp.id, hp.profile_name, hp.shared_users, hp.rate_limit, hp.session_timeout,
                         hp.idle_timeout, hp.address_pool, hp.price, hp.selling_price, hp.on_login, hp.comment
                ORDER BY hp.profile_name ASC";

        $rows = $pdo->query($sql)->fetchAll();

        foreach ($rows as $row) {
            $name = trim((string) ($row['profile_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $onLogin = (string) ($row['on_login'] ?? '');
            if ($onLogin === '') {
                $onLogin = generateHotspotExpiryScript('none', (float) ($row['price'] ?? 0), (string) ($row['session_timeout'] ?? ''), (float) ($row['selling_price'] ?? 0));
            }

            $hotspotProfiles[] = [
                '.id' => (string) ($row['id'] ?? ''),
                'name' => $name,
                'shared-users' => (string) ($row['shared_users'] ?? '1'),
                'rate-limit' => (string) ($row['rate_limit'] ?: ($row['reply_rate_limit'] ?? '')),
                'session-timeout' => (string) ($row['session_timeout'] ?: ($row['reply_session_timeout'] ?? '')),
                'idle-timeout' => (string) ($row['idle_timeout'] ?: ($row['reply_idle_timeout'] ?? '')),
                'address-pool' => (string) ($row['address_pool'] ?: ($row['reply_address_pool'] ?? '')),
                'price' => (float) ($row['price'] ?? 0),
                'selling-price' => (float) ($row['selling_price'] ?? 0),
                'on-login' => $onLogin,
                'comment' => (string) ($row['comment'] ?: ($row['reply_comment'] ?? '')),
                'service-type' => 'Login-User',
                'service' => 'hotspot',
            ];
        }
    } catch (Exception $e) {
        logError('Failed to fetch hotspot profiles from Radius: ' . $e->getMessage());
        return [];
    }

    return $hotspotProfiles;
}

function radiusUpsertHotspotProfileCloud($id, $data)
{
    if (!radiusUserProvisioningReady()) {
        return false;
    }

    $hotspotProfiles = radiusQualifiedTable('hotspot_profiles');
    $existing = null;
    if ($id !== null && $id !== '') {
        $existing = fetchOne("SELECT * FROM {$hotspotProfiles} WHERE id = ? LIMIT 1", [(int) $id]);
    }

    $profileName = trim((string) ($data['name'] ?? ($existing['profile_name'] ?? '')));
    if ($profileName === '') {
        return false;
    }

    $sharedUsers = isset($data['shared-users']) ? (int) $data['shared-users'] : (isset($data['shared_users']) ? (int) $data['shared_users'] : ($existing['shared_users'] ?? 1));
    if ($sharedUsers < 1) {
        $sharedUsers = 1;
    }

    $rateLimit = trim((string) ($data['rate-limit'] ?? ($data['rate_limit'] ?? ($existing['rate_limit'] ?? ''))));
    $sessionTimeout = trim((string) ($data['session-timeout'] ?? ($data['validity'] ?? ($existing['session_timeout'] ?? ''))));
    $idleTimeout = trim((string) ($data['idle-timeout'] ?? ($data['idle_timeout'] ?? ($existing['idle_timeout'] ?? ''))));
    $addressPool = trim((string) ($data['address-pool'] ?? ($data['address_pool'] ?? ($existing['address_pool'] ?? ''))));
    $price = isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : (float) ($existing['price'] ?? 0);
    $sellingPrice = isset($data['selling-price']) && is_numeric($data['selling-price']) ? (float) $data['selling-price'] : (isset($data['selling_price']) && is_numeric($data['selling_price']) ? (float) $data['selling_price'] : (float) ($existing['selling_price'] ?? 0));
    $comment = trim((string) ($data['comment'] ?? ($existing['comment'] ?? '')));
    $onLogin = trim((string) ($data['on-login'] ?? ($data['on_login'] ?? ($existing['on_login'] ?? ''))));

    if ($onLogin === '') {
        $onLogin = generateHotspotExpiryScript('none', $price, $sessionTimeout, $sellingPrice);
    }

    $hotspotProfiles = radiusQualifiedTable('hotspot_profiles');

    try {
        radiusBeginTransaction();

        if ($id !== null && (string) $id !== '') {
            $stmt = query("UPDATE {$hotspotProfiles} SET profile_name = ?, shared_users = ?, rate_limit = ?, session_timeout = ?, idle_timeout = ?, address_pool = ?, price = ?, selling_price = ?, on_login = ?, comment = ?, updated_at = NOW() WHERE id = ?", [
                $profileName,
                $sharedUsers,
                $rateLimit,
                $sessionTimeout,
                $idleTimeout,
                $addressPool,
                $price,
                $sellingPrice,
                $onLogin,
                $comment,
                (int) $id,
            ]);
        } else {
            $stmt = query("INSERT INTO {$hotspotProfiles} (profile_name, shared_users, rate_limit, session_timeout, idle_timeout, address_pool, price, selling_price, on_login, comment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", [
                $profileName,
                $sharedUsers,
                $rateLimit,
                $sessionTimeout,
                $idleTimeout,
                $addressPool,
                $price,
                $sellingPrice,
                $onLogin,
                $comment,
            ]);
        }

        $groupReplyTable = radiusQualifiedTable('radgroupreply');
        $groupCheckTable = radiusQualifiedTable('radgroupcheck');

        $replyRows = [
            'Service-Type' => 'Framed-User',
            'Mikrotik-Rate-Limit' => $rateLimit,
            'Session-Timeout' => $sessionTimeout,
            'Idle-Timeout' => $idleTimeout,
            'Framed-Pool' => $addressPool,
            'Mikrotik-Comment' => $comment,
        ];

        foreach ($replyRows as $attribute => $value) {
            $value = trim((string) $value);
            if ($attribute !== 'Service-Type' && $value === '') {
                query("DELETE FROM {$groupReplyTable} WHERE groupname = ? AND attribute = ?", [$profileName, $attribute]);
                continue;
            }

            query("INSERT INTO {$groupReplyTable} (groupname, attribute, op, value) VALUES (?, ?, '=', ?) ON DUPLICATE KEY UPDATE value = VALUES(value), op = VALUES(op)", [
                $profileName,
                $attribute,
                $value,
            ]);
        }

        query("INSERT INTO {$groupCheckTable} (groupname, attribute, op, value) VALUES (?, 'Simultaneous-Use', '=', ?) ON DUPLICATE KEY UPDATE value = VALUES(value), op = VALUES(op)", [
            $profileName,
            $sharedUsers,
        ]);

        radiusCommit();
        return true;
    } catch (Exception $e) {
        radiusRollback();
        logError('Failed to save hotspot profile to Radius: ' . $e->getMessage());
        return false;
    }
}

function radiusDeleteHotspotProfileCloud($id)
{
    if (!radiusUserProvisioningReady()) {
        return false;
    }

    $id = trim((string) $id);
    if ($id === '') {
        return false;
    }

    try {
        radiusBeginTransaction();

        $profileRow = fetchOne("SELECT profile_name FROM " . radiusQualifiedTable('hotspot_profiles') . " WHERE id = ? LIMIT 1", [(int) $id]);
        $profileName = trim((string) ($profileRow['profile_name'] ?? ''));
        if ($profileName === '') {
            radiusRollback();
            return false;
        }

        $usageCount = radiusHotspotProfileUsageCount($profileName);
        if ($usageCount > 0) {
            radiusRollback();
            logError("Cannot delete hotspot profile '{$profileName}' - used by {$usageCount} users");
            return false;
        }

        $hotspotProfiles = radiusQualifiedTable('hotspot_profiles');
        $groupReplyTable = radiusQualifiedTable('radgroupreply');
        $groupCheckTable = radiusQualifiedTable('radgroupcheck');

        query("DELETE FROM {$hotspotProfiles} WHERE profile_name = ?", [$profileName]);
        query("DELETE FROM {$groupReplyTable} WHERE groupname = ?", [$profileName]);
        query("DELETE FROM {$groupCheckTable} WHERE groupname = ?", [$profileName]);

        radiusCommit();
        return true;
    } catch (Exception $e) {
        radiusRollback();
        logError('Failed to delete hotspot profile from Radius: ' . $e->getMessage());
        return false;
    }
}

function radiusGetPppoeProfilesCloud()
{
    try {
        $pdo = radiusDbConnection();
        $sql = "SELECT r.groupname,
                       MAX(CASE WHEN r.attribute = 'Mikrotik-Rate-Limit' THEN r.value END) AS rate_limit,
                       MAX(CASE WHEN r.attribute = 'Framed-IP-Address' THEN r.value END) AS local_address,
                       MAX(CASE WHEN r.attribute = 'Framed-Pool' THEN r.value END) AS remote_address,
                       MAX(CASE WHEN r.attribute = 'Mikrotik-Primary-DNS' THEN r.value END) AS dns_server,
                       MAX(CASE WHEN r.attribute = 'Mikrotik-Comment' THEN r.value END) AS profile_comment,
                       MAX(CASE WHEN r.attribute = 'Service-Type' THEN r.value END) AS service_type
                FROM radgroupreply r
                GROUP BY r.groupname
                HAVING MAX(CASE WHEN r.attribute = 'Service-Type' THEN r.value END) = 'Framed-User'
                ORDER BY r.groupname ASC";

        $rows = $pdo->query($sql)->fetchAll();
        $profiles = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['groupname'] ?? ''));
            if ($name === '') {
                continue;
            }

            $profiles[] = [
                '.id' => $name,
                'name' => $name,
                'rate-limit' => (string) ($row['rate_limit'] ?? ''),
                'local-address' => (string) ($row['local_address'] ?? ''),
                'remote-address' => (string) ($row['remote_address'] ?? ''),
                'dns-server' => (string) ($row['dns_server'] ?? ''),
                'comment' => (string) ($row['profile_comment'] ?? ''),
                'service-type' => 'Framed-User',
                'service' => 'pppoe',
            ];
        }

        return $profiles;
    } catch (Exception $e) {
        logError('Failed to fetch PPPoE profiles from Radius: ' . $e->getMessage());
        return [];
    }
}

function radiusUpsertPppoeProfileCloud($id, $data)
{
    $payload = pppoeNormalizeProfileData($data);
    $targetName = '';

    if (isset($payload['name'])) {
        $targetName = trim((string) $payload['name']);
    }

    if ($targetName === '') {
        $targetName = trim((string) $id);
    }

    if ($targetName === '') {
        return false;
    }

    try {
        $pdo = radiusDbConnection();
        radiusBeginTransaction();

        $oldName = trim((string) $id);
        if ($oldName !== '' && $oldName !== $targetName) {
            $stmtRename = $pdo->prepare('UPDATE radgroupreply SET groupname = ? WHERE groupname = ?');
            $stmtRename->execute([$targetName, $oldName]);

            $stmtRenameCheck = $pdo->prepare('UPDATE radgroupcheck SET groupname = ? WHERE groupname = ?');
            $stmtRenameCheck->execute([$targetName, $oldName]);

            $stmtRenameUserGroup = $pdo->prepare('UPDATE radusergroup SET groupname = ? WHERE groupname = ?');
            $stmtRenameUserGroup->execute([$targetName, $oldName]);
        }

        $attributeMap = [
            'Mikrotik-Rate-Limit' => $payload['rate-limit'] ?? null,
            'Framed-IP-Address' => $payload['local-address'] ?? null,
            'Framed-Pool' => $payload['remote-address'] ?? null,
            'Mikrotik-Primary-DNS' => $payload['dns-server'] ?? null,
            'Mikrotik-Comment' => $payload['comment'] ?? null,
            'Service-Type' => 'Framed-User',
        ];

        foreach ($attributeMap as $attribute => $value) {
            $value = is_null($value) ? '' : trim((string) $value);

            // Selalu hapus dulu yang lama untuk groupname & attribute ini agar tidak duplikat
            $stmtClear = $pdo->prepare('DELETE FROM radgroupreply WHERE groupname = ? AND attribute = ?');
            $stmtClear->execute([$targetName, $attribute]);

            if ($attribute !== 'Service-Type' && $value === '') {
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, ?, \'=\', ?)'
            );
            $stmt->execute([$targetName, $attribute, $value]);
        }

        radiusCommit();
        return true;
    } catch (Exception $e) {
        radiusRollback();
        logError('Failed to save PPPoE profile to Radius: ' . $e->getMessage());
        return false;
    }
}

function radiusDeletePppoeProfileCloud($id)
{
    $name = trim((string) $id);
    if ($name === '') {
        return false;
    }

    try {
        $pdo = radiusDbConnection();
        radiusBeginTransaction();

        $stmtUsage = $pdo->prepare('SELECT COUNT(*) FROM radusergroup WHERE groupname = ?');
        $stmtUsage->execute([$name]);
        $usageCount = (int) $stmtUsage->fetchColumn();

        if ($usageCount > 0) {
            radiusRollback();
            logError("Cannot delete PPPoE profile '{$name}' - used by {$usageCount} users");
            return false;
        }

        $stmtReply = $pdo->prepare('DELETE FROM radgroupreply WHERE groupname = ?');
        $stmtReply->execute([$name]);

        $stmtCheck = $pdo->prepare('DELETE FROM radgroupcheck WHERE groupname = ?');
        $stmtCheck->execute([$name]);

        radiusCommit();
        return true;
    } catch (Exception $e) {
        radiusRollback();
        logError('Failed to delete PPPoE profile from Radius: ' . $e->getMessage());
        return false;
    }
}

function radiusUserProvisioningReady()
{
    try {
        $pdo = radiusDbConnection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'radcheck'");
        $hasRadcheck = $stmt->rowCount() > 0;
        $stmt = $pdo->query("SHOW TABLES LIKE 'radusergroup'");
        $hasRadusergroup = $stmt->rowCount() > 0;
        return $hasRadcheck && $hasRadusergroup;
    } catch (Exception $e) {
        return false;
    }
}
