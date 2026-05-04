<?php

// Ensure config is loaded
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

function getMikrotikSettings($routerId = null)
{
    $legacyOverrides = [];
    $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('MIKROTIK_HOST', 'MIKROTIK_USER', 'MIKROTIK_PASS', 'MIKROTIK_PORT')");
    foreach ($dbSettings as $s) {
        $legacyOverrides[$s['setting_key']] = $s['setting_value'];
    }

    // If routerId is provided, always fetch that specific router
    if ($routerId !== null && (int)$routerId > 0) {
        $router = fetchOne("SELECT * FROM routers WHERE id = ?", [$routerId]);
        if ($router) {
            
            if (!empty($legacyOverrides['MIKROTIK_HOST'])) {
                $router['host'] = $legacyOverrides['MIKROTIK_HOST'];
            }
            if (!empty($legacyOverrides['MIKROTIK_PORT'])) {
                $router['port'] = $legacyOverrides['MIKROTIK_PORT'];
            }
            if (!empty($legacyOverrides['MIKROTIK_USER'])) {
                $router['username'] = $legacyOverrides['MIKROTIK_USER'];
            }
            if (!empty($legacyOverrides['MIKROTIK_PASS'])) {
                $router['password'] = $legacyOverrides['MIKROTIK_PASS'];
            }

            return [
                'id' => $router['id'],
                'host' => $router['host'],
                'user' => $router['username'],
                'pass' => $router['password'],
                'port' => (int) $router['port'],
                'name' => $router['name']
            ];
        }
    }

    static $settings = null;
    if ($settings === null) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $activeRouterId = $_SESSION['active_router_id'] ?? null;

        $router = null;
        if ($activeRouterId) {
            $router = fetchOne("SELECT * FROM routers WHERE id = ?", [$activeRouterId]);
        }

        if (!$router) {
            // Try to get active router or first router
            $router = fetchOne("SELECT * FROM routers WHERE is_active = 1 LIMIT 1");
            if (!$router) {
                $router = fetchOne("SELECT * FROM routers LIMIT 1");
            }
        }

        if ($router) {
            if (!empty($legacyOverrides['MIKROTIK_HOST'])) {
                $router['host'] = $legacyOverrides['MIKROTIK_HOST'];
            }
            if (!empty($legacyOverrides['MIKROTIK_PORT'])) {
                $router['port'] = $legacyOverrides['MIKROTIK_PORT'];
            }
            if (!empty($legacyOverrides['MIKROTIK_USER'])) {
                $router['username'] = $legacyOverrides['MIKROTIK_USER'];
            }
            if (!empty($legacyOverrides['MIKROTIK_PASS'])) {
                $router['password'] = $legacyOverrides['MIKROTIK_PASS'];
            }

            $_SESSION['active_router_id'] = $router['id'];
            $settings = [
                'id' => $router['id'],
                'host' => $router['host'],
                'user' => $router['username'],
                'pass' => $router['password'],
                'port' => (int) $router['port'],
                'name' => $router['name']
            ];
            return $settings;
        }

        // Bridge migration/Fallback: Get from legacy settings table
        $settings = [
            'id' => 0,
            'host' => defined('MIKROTIK_HOST') ? MIKROTIK_HOST : '',
            'user' => defined('MIKROTIK_USER') ? MIKROTIK_USER : '',
            'pass' => defined('MIKROTIK_PASS') ? MIKROTIK_PASS : '',
            'port' => defined('MIKROTIK_PORT') ? MIKROTIK_PORT : 8728,
            'name' => 'Default Router'
        ];

        foreach ($legacyOverrides as $settingKey => $settingValue) {
            switch ($settingKey) {
                case 'MIKROTIK_HOST':

                    $settings['host'] = $settingValue;
                    break;
                case 'MIKROTIK_USER':
                    $settings['user'] = $settingValue;
                    break;
                case 'MIKROTIK_PASS':
                    $settings['pass'] = $settingValue;
                    break;
                case 'MIKROTIK_PORT':

                    $settings['port'] = (int) $settingValue;
                    break;

            }
        }
    }
    return $settings;
}

// Get all routers from database
function getAllRouters()
{
    if (!tableExists('routers')) {
        return [];
    }
    return fetchAll("SELECT * FROM routers ORDER BY name ASC");
}
/**
 * Get a persistent MikroTik connection for the remainder of the request
 */
function getMikrotikConnection($routerId = null)
{
    static $sockets = [];
    static $lastHosts = [];

    $mikrotik = getMikrotikSettings($routerId);
    $rId = (int)($mikrotik['id'] ?? 0);
    $currentHost = $mikrotik['host'] . ':' . $mikrotik['port'];

    // If socket is dead or doesn't exist for this router, reconnect
    if (!isset($sockets[$rId]) || !is_resource($sockets[$rId]) || feof($sockets[$rId]) || ($lastHosts[$rId] ?? '') !== $currentHost) {
        if (isset($sockets[$rId]) && is_resource($sockets[$rId])) {
            @fclose($sockets[$rId]);
        }

        $sockets[$rId] = mikrotikConnect($routerId);
        if ($sockets[$rId]) {
            if (!mikrotikLogin($sockets[$rId], $routerId)) {
                @fclose($sockets[$rId]);
                $sockets[$rId] = null;
            } else {
                $lastHosts[$rId] = $currentHost;
            }
        }
    }

    return $sockets[$rId];
}

function mikrotikConnect($routerId = null)
{
    $mikrotik = getMikrotikSettings($routerId);

    if (empty($mikrotik['host']) || empty($mikrotik['user'])) {
        logError("MikroTik config incomplete: host or user is empty");
        return false;
    }

    $connectTimeout = 2;
    $socket = @fsockopen($mikrotik['host'], $mikrotik['port'], $errno, $errstr, $connectTimeout);

    if (!$socket) {
        logError("MikroTik connection failed: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, $connectTimeout);
    stream_set_blocking($socket, true);

    return $socket;
}

function mikrotikLogin($socket, $routerId = null)
{
    $mikrotik = getMikrotikSettings($routerId);
    $username = $mikrotik['user'];
    $password = $mikrotik['pass'];

    // Method 1: Plain text password (RouterOS 6.43+)
    // This is the preferred method for modern RouterOS
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }

    // If plain text method failed, try MD5 challenge-response (older RouterOS)
    // Reconnect is needed, but we'll try a different approach

    // Method 2: MD5 Challenge-Response (RouterOS pre-6.43)
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    if (empty($response)) {
        return false;
    }

    // Extract challenge from response
    $challenge = null;
    foreach ($response as $word) {
        if (strpos($word, '=ret=') === 0) {
            $challenge = substr($word, 5);
            break;
        }
    }

    if (!$challenge) {
        return false;
    }

    // Calculate MD5 hash
    $hash = md5(chr(0) . $password . pack('H*', $challenge), true);

    // Send login with hash
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=response=' . bin2hex($hash));
    mikrotikWrite($socket, ''); // End sentence

    // Read response
    $response = mikrotikReadSentence($socket);

    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }

    return false;
}

function mikrotikWrite($socket, $word)
{
    if ($word === '') {
        fwrite($socket, chr(0));
        return;
    }

    $len = strlen($word);
    $encodedLen = '';

    if ($len < 0x80) {
        $encodedLen = chr($len);
    } elseif ($len < 0x4000) {
        $encodedLen = chr(($len >> 8) | 0x80) . chr($len & 0xFF);
    } elseif ($len < 0x200000) {
        $encodedLen = chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } elseif ($len < 0x10000000) {
        $encodedLen = chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } else {
        $encodedLen = chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }

    fwrite($socket, $encodedLen . $word);
}

function mikrotikWriteCommand($socket, $command)
{
    mikrotikWrite($socket, $command);
}

function mikrotikWriteWord($socket, $word)
{
    mikrotikWrite($socket, $word);
}

function mikrotikReadSentence($socket)
{
    $words = [];
    while (true) {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '')
            break;

        $byte = ord($byte);
        $len = 0;

        if (($byte & 0x80) == 0x00) {
            $len = $byte;
        } elseif (($byte & 0xC0) == 0x80) {
            $len = (($byte & 0x3F) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xE0) == 0xC0) {
            $len = (($byte & 0x1F) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF0) == 0xE0) {
            $len = (($byte & 0x0F) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF8) == 0xF0) {
            $len = (ord(fread($socket, 1)) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        }

        if ($len == 0) {
            break;
        }

        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '')
                break;
            $word .= $chunk;
            $remaining -= strlen($chunk);
        }

        $words[] = $word;
    }

    return $words;
}

function mikrotikRead($socket)
{
    return mikrotikReadSentence($socket);
}

function mikrotikTrapMessageFromResponse($response)
{
    if (!is_array($response) || empty($response)) {
        return '';
    }
    $isTrap = false;
    foreach ($response as $word) {
        if ($word === '!trap') {
            $isTrap = true;
            break;
        }
    }
    if (!$isTrap) {
        return '';
    }
    $parts = [];
    foreach ($response as $word) {
        if (strpos($word, '=message=') === 0) {
            $parts[] = substr($word, 9);
        } elseif (strpos($word, '=category=') === 0) {
            $parts[] = 'category=' . substr($word, 10);
        } elseif (strpos($word, '=reason=') === 0) {
            $parts[] = 'reason=' . substr($word, 8);
        }
    }
    return implode(' | ', $parts);
}

function mikrotikQuery($command, $params = [])
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    // Send command
    mikrotikWrite($socket, $command);
    foreach ($params as $key => $value) {
        mikrotikWrite($socket, '=' . $key . '=' . $value);
    }
    mikrotikWrite($socket, ''); // End sentence

    // Read response — mikrotikRead() returns an array of words
    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return mikrotikParseResponse($allWords);
}

function mikrotikParseResponse($response)
{
    // $response is an array of words from binary protocol
    $result = [];

    foreach ($response as $word) {
        if ($word === '!done' || strpos($word, '!trap') === 0) {
            break;
        }

        if (strpos($word, '=') === 0) {
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $result[$parts[0]] = $parts[1];
            }
        }
    }

    return $result;
}

function mikrotikParseTableRows($response)
{
    $rows = [];
    $currentRow = [];

    foreach ($response as $word) {
        if ($word === '!re') {
            if (!empty($currentRow)) {
                $rows[] = $currentRow;
                $currentRow = [];
            }
            continue;
        }

        if ($word === '!done') {
            if (!empty($currentRow)) {
                $rows[] = $currentRow;
            }
            break;
        }

        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentRow[$parts[0]] = $parts[1];
            }
        }
    }

    return $rows;
}

function mikrotikReadTableRows($socket, $command, $params = [], $timeoutSeconds = 5)
{
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, $command);
    foreach ($params as $key => $value) {
        mikrotikWrite($socket, '=' . $key . '=' . $value);
    }
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + max(1, (int) $timeoutSeconds);

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return mikrotikParseTableRows($allWords);
}

function wireGuardFormatBytes($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes < 0) {
        $bytes = 0;
    }

    if (function_exists('formatBytes')) {
        return formatBytes($bytes);
    }

    if ($bytes >= 1099511627776) {
        return round($bytes / 1099511627776, 2) . ' TB';
    }
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return (string) $bytes . ' B';
}

function wireGuardFormatHandshake($latestHandshake)
{
    $latestHandshake = trim((string) $latestHandshake);
    if ($latestHandshake === '' || $latestHandshake === '0' || $latestHandshake === 'never') {
        return 'never';
    }

    if (ctype_digit($latestHandshake)) {
        $handshakeAt = (int) $latestHandshake;
        if ($handshakeAt > 0) {
            $elapsed = time() - $handshakeAt;
            if ($elapsed < 0) {
                $elapsed = 0;
            }

            if ($elapsed < 60) {
                return $elapsed . ' seconds ago';
            }
            if ($elapsed < 3600) {
                return floor($elapsed / 60) . ' minutes ago';
            }
            if ($elapsed < 86400) {
                return floor($elapsed / 3600) . ' hours ago';
            }

            return floor($elapsed / 86400) . ' days ago';
        }
    }

    return $latestHandshake;
}

function mikrotikDurationToSeconds($duration)
{
    $duration = trim((string) $duration);
    if ($duration === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $duration)) {
        return (int) $duration;
    }

    if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $duration, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        $s = isset($m[3]) ? (int) $m[3] : 0;
        return ($h * 3600) + ($i * 60) + $s;
    }

    if (preg_match('/^(?=.*\d)(?:\d+\s*[wdhms]\s*)+$/i', $duration)) {
        $total = 0;
        preg_match_all('/(\d+)\s*([wdhms])/i', $duration, $parts, PREG_SET_ORDER);
        foreach ($parts as $part) {
            $val = (int) $part[1];
            $unit = strtolower($part[2]);
            if ($unit === 'w') {
                $total += $val * 604800;
            } elseif ($unit === 'd') {
                $total += $val * 86400;
            } elseif ($unit === 'h') {
                $total += $val * 3600;
            } elseif ($unit === 'm') {
                $total += $val * 60;
            } elseif ($unit === 's') {
                $total += $val;
            }
        }
        return $total > 0 ? $total : null;
    }

    return null;
}

function mikrotikBytesToInteger($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $value)) {
        return (int) $value;
    }

    if (preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt])b?$/i', $value, $m)) {
        $number = (float) $m[1];
        $unit = strtolower($m[2]);
        $multiplier = 1;

        if ($unit === 'k') {
            $multiplier = 1024;
        } elseif ($unit === 'm') {
            $multiplier = 1024 * 1024;
        } elseif ($unit === 'g') {
            $multiplier = 1024 * 1024 * 1024;
        } elseif ($unit === 't') {
            $multiplier = 1024 * 1024 * 1024 * 1024;
        }

        $bytes = (int) round($number * $multiplier);
        return $bytes > 0 ? $bytes : null;
    }

    return null;
}

// function mikrotikNormalizeTimeoutValue($duration)
// {
//     $duration = trim((string) $duration);
//     if ($duration === '') {
//         return '';
//     }

//     $lower = strtolower($duration);
//     if ($lower === 'none' || $lower === 'never') {
//         return 'none';
//     }

//     $seconds = mikrotikDurationToSeconds($duration);
//     if ($seconds !== null && $seconds > 0) {
//         return (string) $seconds . 's';
//     }

//     return null;
// }

function mikrotikSetProfile($username, $profile, $routerId = null)
{
    $socket = getMikrotikConnection($routerId);
    if (!$socket) {
        return false;
    }

    // Find user and get their secret ID
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $parsed = mikrotikParseUsers($allWords);

    if (empty($parsed)) {
        return false;
    }

    // Get the secret ID from first user
    $secretId = $parsed[0]['.id'] ?? null;
    if (!$secretId) {
        return false;
    }

    // Update profile using secret ID
    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $secretId);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, ''); // End sentence

    // Read response to confirm
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return true;
}

function mikrotikGetPppoeUsers()
{
    if (radiusUserProvisioningReady()) {
        $users = radiusGetUsersByService('Framed-User');
        return array_values(array_filter($users, function ($user) {
            return !radiusLooksLikeHotspotUser($user);
        }));
    }

    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30; // 30 second timeout for large user lists

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return mikrotikParseUsers($allWords);
}

function mikrotikParseUsers($response)
{
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=user1)
    $users = [];
    $currentUser = [];

    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentUser[$parts[0]] = $parts[1];
            }
        }
    }

    return $users;
}

// Add PPPoE Secret
function mikrotikAddSecret($name, $password, $profile = 'default', $service = 'pppoe')
{
    if (radiusUserProvisioningReady()) {
        $serviceType = (strtolower((string) $service) === 'pppoe') ? 'Framed-User' : 'Login-User';
        $ok = radiusSetUser($name, $password, $profile, $serviceType);
        return [
            'success' => $ok,
            'message' => $ok ? 'User saved to Radius DB' : 'Failed to save user to Radius DB',
        ];
    }

    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/add');
    mikrotikWrite($socket, '=name=' . $name);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, '=service=' . $service);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User added successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Update PPPoE Secret
function mikrotikUpdateSecret($id, $data)
{
    // Radius
    if (radiusUserProvisioningReady()) {
        $oldUsername = radiusResolveUsernameById($id);
        if ($oldUsername === null || $oldUsername === '') {
            return ['success' => false, 'message' => 'User not found in Radius DB'];
        }

        $newUsername = trim((string) ($data['name'] ?? $oldUsername));
        if ($newUsername === '') {
            return ['success' => false, 'message' => 'Username is required'];
        }

        if ($newUsername !== $oldUsername) {
            radiusRenameUser($oldUsername, $newUsername);
        }

        $password = isset($data['password']) ? (string) $data['password'] : '';
        if ($password === '') {
            $radcheck = radiusQualifiedTable('radcheck');
            $pwd = fetchOne("SELECT value FROM {$radcheck} WHERE username = ? AND attribute IN ('Cleartext-Password','User-Password') ORDER BY id DESC LIMIT 1", [$newUsername]);
            $password = (string) ($pwd['value'] ?? '');
        }

        $profile = trim((string) ($data['profile'] ?? 'default'));
        $serviceType = (strtolower((string) ($data['service'] ?? 'pppoe')) === 'pppoe') ? 'Framed-User' : 'Login-User';
        $reply = [];
        if (isset($data['disabled'])) {
            $reply['Mikrotik-Disabled'] = ((string) $data['disabled'] === 'true') ? 'true' : 'false';
        }

        $ok = radiusSetUser($newUsername, $password, $profile, $serviceType, $reply);
        return [
            'success' => $ok,
            'message' => $ok ? 'User updated in Radius DB' : 'Failed to update user in Radius DB',
        ];
    }
    logError('PPPoE blocked: Radius DB is not ready.');
    return false;

    // Mikrotik
    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $id);

    if (isset($data['name']))
        mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['password']))
        mikrotikWrite($socket, '=password=' . $data['password']);
    if (isset($data['profile']))
        mikrotikWrite($socket, '=profile=' . $data['profile']);
    if (isset($data['service']))
        mikrotikWrite($socket, '=service=' . $data['service']);
    if (isset($data['disabled']))
        mikrotikWrite($socket, '=disabled=' . $data['disabled']);

    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User updated successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Delete PPPoE Secret
function mikrotikDeleteSecret($id)
{
    // Radius
    if (radiusUserProvisioningReady()) {
        $username = radiusResolveUsernameById($id);
        if ($username === null || $username === '') {
            return ['success' => false, 'message' => 'User not found in Radius DB'];
        }

        $ok = radiusDeleteUser($username);
        return [
            'success' => $ok,
            'message' => $ok ? 'User deleted from Radius DB' : 'Failed to delete user from Radius DB',
        ];
    }
    logError('PPPoE blocked: Radius DB is not ready.');
    return false;

    // Mikrotik
    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User deleted successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Get Active PPPoE Sessions (users currently connected)
function mikrotikGetActiveSessions()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, '/ppp/active/print');
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30; // 30 second timeout for large user lists

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Parse active sessions
    $sessions = [];
    $currentSession = [];

    foreach ($allWords as $word) {
        if ($word === '!done') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
                $currentSession = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentSession[$parts[0]] = $parts[1];
            }
        }
    }

    return $sessions;
}

function mikrotikGetProfiles()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    logActivity('MIKROTIK_API', "Fetching PPPoE profiles");

    // Send print command
    mikrotikWrite($socket, '/ppp/profile/print');

    // End sentence
    mikrotikWrite($socket, '');

    // Read ALL sentences until !done (MikroTik sends multiple sentences)
    $allWords = [];
    $done = false;
    $timeout = time() + 10; // 10 second timeout

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $profiles = mikrotikParseProfiles($allWords);

    return $profiles;
}

function mikrotikParseProfiles($response)
{
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=default)
    $profiles = [];
    $currentProfile = [];

    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentProfile[$parts[0]] = $parts[1];
            }
        }
    }

    return $profiles;
}

function mikrotikAddPppoeProfile($data)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    mikrotikWrite($socket, '/ppp/profile/add');
    if (isset($data['name'])) {
        mikrotikWrite($socket, '=name=' . $data['name']);
    }
    if (isset($data['local-address'])) {
        mikrotikWrite($socket, '=local-address=' . $data['local-address']);
    }
    if (isset($data['remote-address'])) {
        mikrotikWrite($socket, '=remote-address=' . $data['remote-address']);
    }
    if (isset($data['rate-limit'])) {
        mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    }
    if (isset($data['dns-server'])) {
        mikrotikWrite($socket, '=dns-server=' . $data['dns-server']);
    }
    if (isset($data['comment'])) {
        mikrotikWrite($socket, '=comment=' . $data['comment']);
    }
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            $trap = mikrotikTrapMessageFromResponse($response);
            if ($trap !== '') {
                logError('MikroTik add PPPoE profile failed: ' . $trap);
            } else {
                logError('MikroTik add PPPoE profile failed.');
            }
            return false;
        }
    }
    return true;
}

function mikrotikUpdatePppoeProfile($id, $data)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    mikrotikWrite($socket, '/ppp/profile/set');
    mikrotikWrite($socket, '=.id=' . $id);
    if (isset($data['name'])) {
        mikrotikWrite($socket, '=name=' . $data['name']);
    }
    if (isset($data['local-address'])) {
        mikrotikWrite($socket, '=local-address=' . $data['local-address']);
    }
    if (isset($data['remote-address'])) {
        mikrotikWrite($socket, '=remote-address=' . $data['remote-address']);
    }
    if (isset($data['rate-limit'])) {
        mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    }
    if (isset($data['dns-server'])) {
        mikrotikWrite($socket, '=dns-server=' . $data['dns-server']);
    }
    if (isset($data['comment'])) {
        mikrotikWrite($socket, '=comment=' . $data['comment']);
    }
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            $trap = mikrotikTrapMessageFromResponse($response);
            if ($trap !== '') {
                logError('MikroTik update PPPoE profile failed: ' . $trap);
            } else {
                logError('MikroTik update PPPoE profile failed.');
            }
            return false;
        }
    }
    return true;
}

function mikrotikDeletePppoeProfile($id)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    mikrotikWrite($socket, '/ppp/profile/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    fclose($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            $trap = mikrotikTrapMessageFromResponse($response);
            if ($trap !== '') {
                logError('MikroTik delete PPPoE profile failed: ' . $trap);
            } else {
                logError('MikroTik delete PPPoE profile failed.');
            }
            return false;
        }
    }
    return true;
}

// Get MikroTik Hotspot Servers
function mikrotikGetHotspotServers()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $servers = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $servers[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $servers;
}

// Get MikroTik Hotspot User Profiles
function mikrotikGetHotspotProfiles()
{
    return radiusGetHotspotProfilesCloud();
}

// Add MikroTik Hotspot User with Mikhmon Metadata support
function mikrotikAddHotspotUser($username, $password, $profile = 'default', $extraData = [])
{
    if (radiusUserProvisioningReady()) {
        $reply = [];
        if (isset($extraData['limit-uptime']) && trim((string) $extraData['limit-uptime']) !== '') {
            $seconds = mikrotikDurationToSeconds((string) $extraData['limit-uptime']);
            if ($seconds !== null && $seconds > 0) {
                $reply['Session-Timeout'] = (string) $seconds;
            }
        }
        if (isset($extraData['limit-bytes-total']) && trim((string) $extraData['limit-bytes-total']) !== '') {
            $limit = mikrotikBytesToInteger((string) $extraData['limit-bytes-total']);
            if ($limit !== null && $limit > 0) {
                $limitValue = (string) $limit;
                $reply['Mikrotik-Recv-Limit'] = $limitValue;
                $reply['Mikrotik-Xmit-Limit'] = $limitValue;
                $reply['Mikrotik-Total-Limit'] = $limitValue;
            }
        }
        if (isset($extraData['rate-limit']) && trim((string) $extraData['rate-limit']) !== '') {
            $reply['Mikrotik-Rate-Limit'] = trim((string) $extraData['rate-limit']);
        }
        if (isset($extraData['idle-timeout']) && trim((string) $extraData['idle-timeout']) !== '') {
            $reply['Idle-Timeout'] = trim((string) $extraData['idle-timeout']);
        }
        if (isset($extraData['address-pool']) && trim((string) $extraData['address-pool']) !== '' && strtolower(trim((string) $extraData['address-pool'])) !== 'none') {
            $reply['Framed-Pool'] = trim((string) $extraData['address-pool']);
        }
        if (isset($extraData['parent-queue']) && trim((string) $extraData['parent-queue']) !== '' && strtolower(trim((string) $extraData['parent-queue'])) !== 'none') {
            $reply['Mikrotik-Parent-Queue'] = trim((string) $extraData['parent-queue']);
        }

        return radiusSetUser($username, $password, $profile, 'Login-User', $reply);
    }

    logError('Hotspot create blocked: Radius DB is not ready.');
    return false;

    // $socket = getMikrotikConnection();
    // if (!$socket) {
    //     return false;
    // }

    // // Add hotspot user
    // mikrotikWrite($socket, '/ip/hotspot/user/add');
    // mikrotikWrite($socket, '=name=' . $username);
    // mikrotikWrite($socket, '=password=' . $password);
    // mikrotikWrite($socket, '=profile=' . $profile);

    // // Add extra parameters if provided
    // if (isset($extraData['server'])) {
    //     mikrotikWrite($socket, '=server=' . $extraData['server']);
    // }
    // if (isset($extraData['limit-uptime'])) {
    //     mikrotikWrite($socket, '=limit-uptime=' . $extraData['limit-uptime']);
    // }
    // if (isset($extraData['limit-bytes-total'])) {
    //     mikrotikWrite($socket, '=limit-bytes-total=' . $extraData['limit-bytes-total']);
    // }

    // // Mikhmon Style Comment
    // $comment = $extraData['comment'] ?? "parent:{$profile}";
    // mikrotikWrite($socket, '=comment=' . $comment);

    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);

    // // Check for success (no !trap error)
    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0) {
    //         return false;
    //     }
    // }

    // return true;
}

// Delete MikroTik Hotspot User
function mikrotikDeleteHotspotUser($username)
{
    if (radiusUserProvisioningReady()) {
        return radiusDeleteUser($username);
    }

    logError('Hotspot delete blocked: Radius DB is not ready.');
    return false;

    // $socket = getMikrotikConnection();
    // if (!$socket) {
    //     return false;
    // }

    // // Find user first
    // mikrotikWrite($socket, '/ip/hotspot/user/print');
    // mikrotikWrite($socket, '?name=' . $username);
    // mikrotikWrite($socket, '');

    // $allWords = [];
    // $done = false;
    // $timeout = time() + 10;

    // while (!$done && time() < $timeout) {
    //     $words = mikrotikReadSentence($socket);
    //     if (empty($words))
    //         break;
    //     foreach ($words as $word) {
    //         $allWords[] = $word;
    //         if ($word === '!done') {
    //             $done = true;
    //             break;
    //         }
    //     }
    // }

    // // Find the .id
    // $userId = null;
    // foreach ($allWords as $word) {
    //     if (strpos($word, '=.id=') === 0) {
    //         $userId = substr($word, 5);
    //         break;
    //     }
    // }

    // if (!$userId) {
    //     return false; // User not found
    // }

    // // Remove user
    // mikrotikWrite($socket, '/ip/hotspot/user/remove');
    // mikrotikWrite($socket, '=.id=' . $userId);
    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);

    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0) {
    //         return false;
    //     }
    // }

    // return true;
}

// Toggle Hotspot User (Enable/Disable)
function mikrotikToggleHotspotUser($username, $status)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    // Find user first
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 5;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Find the .id
    $userId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $userId = substr($word, 5);
            break;
        }
    }

    if (!$userId) {
        return false;
    }

    // Toggle
    mikrotikWrite($socket, '/ip/hotspot/user/set');
    mikrotikWrite($socket, '=.id=' . $userId);
    mikrotikWrite($socket, '=disabled=' . ($status === 'enable' ? 'no' : 'yes'));
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }

    return true;
}

function radiusDbNameValue()
{
    if (!defined('RADIUS_DB_NAME')) {
        return null;
    }

    $db = trim((string) RADIUS_DB_NAME);
    return $db !== '' ? $db : null;
}

function radiusQualifiedTable($table)
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return null;
    }

    return '`' . $db . '`.`' . $table . '`';
}

function radiusTableAvailable($table)
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return false;
    }

    $row = fetchOne(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
        [$db, $table]
    );

    return $row !== null;
}

function radiusUserProvisioningReady()
{
    return radiusTableAvailable('radcheck') && radiusTableAvailable('radusergroup');
}

function radiusResolveUsernameById($id)
{
    $radcheck = radiusQualifiedTable('radcheck');
    if ($radcheck === null) {
        return null;
    }

    $row = fetchOne("SELECT username FROM {$radcheck} WHERE id = ? LIMIT 1", [(int) $id]);
    return $row ? (string) ($row['username'] ?? '') : null;
}
function radiusAddNas($nasName, $nasIp, $secret, $type = 'other')
{
    if (!radiusTableAvailable('nas')) {
        return false;
    }

    $nasTable = radiusQualifiedTable('nas');
    query("INSERT INTO {$nasTable} (nasname, shortname, secret, type) VALUES (?, ?, ?, ?)", [
        trim((string) $nasIp),
        trim((string) $nasName),
        trim((string) $secret),
        trim((string) $type),
    ]);

    return true;
}
function radiusDisplayNas()
{
    if (!radiusTableAvailable('nas')) {
        return [];
    }

    $nasTable = radiusQualifiedTable('nas');
    return fetchAll("SELECT id, shortname, nasname, secret FROM {$nasTable}");
}
function radiusDeleteNas($id)
{
    if (!radiusTableAvailable('nas')) {
        return false;
    }

    $nasTable = radiusQualifiedTable('nas');
    query("DELETE FROM {$nasTable} WHERE id = ?", [(int) $id]);
    return true;
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
    $radreply = radiusTableAvailable('radreply') ? radiusQualifiedTable('radreply') : null;

    query("UPDATE {$radcheck} SET username = ? WHERE username = ?", [$newUsername, $oldUsername]);
    query("UPDATE {$radusergroup} SET username = ? WHERE username = ?", [$newUsername, $oldUsername]);
    if ($radreply) {
        query("UPDATE {$radreply} SET username = ? WHERE username = ?", [$newUsername, $oldUsername]);
    }

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
    $radreply = radiusTableAvailable('radreply') ? radiusQualifiedTable('radreply') : null;

    query("DELETE FROM {$radcheck} WHERE username = ? AND attribute IN ('Cleartext-Password','User-Password')", [$username]);
    query("INSERT INTO {$radcheck} (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)", [$username, $password]);

    query("DELETE FROM {$radusergroup} WHERE username = ?", [$username]);
    query("INSERT INTO {$radusergroup} (username, groupname, priority) VALUES (?, ?, 1)", [$username, $profile]);

    if ($radreply) {
        query("DELETE FROM {$radreply} WHERE username = ? AND attribute IN ('Service-Type','Mikrotik-Comment','Session-Timeout','Mikrotik-Total-Limit','Mikrotik-Recv-Limit','Mikrotik-Xmit-Limit','Mikrotik-Disabled','Mikrotik-Price','Mikrotik-Rate-Limit','Idle-Timeout','Framed-Pool','Mikrotik-Parent-Queue')", [$username]);
        query("INSERT INTO {$radreply} (username, attribute, op, value) VALUES (?, 'Service-Type', ':=', ?)", [$username, $serviceType]);

        foreach ($replyAttributes as $attribute => $value) {
            $attribute = trim((string) $attribute);
            $value = trim((string) $value);
            if ($attribute === '' || $value === '') {
                continue;
            }
            query("INSERT INTO {$radreply} (username, attribute, op, value) VALUES (?, ?, ':=', ?)", [$username, $attribute, $value]);
        }
    }

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
    $radreply = radiusTableAvailable('radreply') ? radiusQualifiedTable('radreply') : null;

    query("DELETE FROM {$radcheck} WHERE username = ?", [$username]);
    query("DELETE FROM {$radusergroup} WHERE username = ?", [$username]);
    if ($radreply) {
        query("DELETE FROM {$radreply} WHERE username = ?", [$username]);
    }

    return true;
}

function radiusGetUsersByService($serviceType)
{
    if (!radiusUserProvisioningReady()) {
        return [];
    }

    $radcheck = radiusQualifiedTable('radcheck');
    $radusergroup = radiusQualifiedTable('radusergroup');
    $radreply = radiusTableAvailable('radreply') ? radiusQualifiedTable('radreply') : null;

    $serviceTypeSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Service-Type' THEN rr.value END) AS service_type"
        : "NULL AS service_type";
    $commentSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Mikrotik-Comment' THEN rr.value END) AS user_comment"
        : "'' AS user_comment";
    $sessionTimeoutSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Session-Timeout' THEN rr.value END) AS session_timeout"
        : "NULL AS session_timeout";
    $bytesTotalSelect = $radreply
        ? "COALESCE(MAX(CASE WHEN rr.attribute = 'Mikrotik-Total-Limit' THEN rr.value END), MAX(CASE WHEN rr.attribute = 'Mikrotik-Recv-Limit' THEN rr.value END), MAX(CASE WHEN rr.attribute = 'Mikrotik-Xmit-Limit' THEN rr.value END)) AS bytes_total"
        : "NULL AS bytes_total";
    $priceSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Mikrotik-Price' THEN rr.value END) AS voucher_price"
        : "NULL AS voucher_price";
    $disabledSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Mikrotik-Disabled' THEN rr.value END) AS disabled_status"
        : "'false' AS disabled_status";
    $rateLimitSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) AS rate_limit"
        : "NULL AS rate_limit";
    $idleTimeoutSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Idle-Timeout' THEN rr.value END) AS idle_timeout"
        : "NULL AS idle_timeout";
    $addressPoolSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Framed-Pool' THEN rr.value END) AS address_pool"
        : "NULL AS address_pool";
    $parentQueueSelect = $radreply
        ? "MAX(CASE WHEN rr.attribute = 'Mikrotik-Parent-Queue' THEN rr.value END) AS parent_queue"
        : "NULL AS parent_queue";

    $sql = "SELECT c.id,
                   c.username,
                   c.value AS password,
                   COALESCE(ug.groupname, 'default') AS profile,
                   {$serviceTypeSelect},
                   {$commentSelect},
                   {$sessionTimeoutSelect},
                   {$bytesTotalSelect},
                   {$priceSelect},
                   {$disabledSelect},
                   {$rateLimitSelect},
                   {$idleTimeoutSelect},
                   {$addressPoolSelect},
                   {$parentQueueSelect}
            FROM {$radcheck} c
            LEFT JOIN {$radusergroup} ug ON ug.username = c.username";

    if ($radreply) {
        $sql .= "\n            LEFT JOIN {$radreply} rr ON rr.username = c.username";
    }

    $sql .= "\n            WHERE c.attribute IN ('Cleartext-Password', 'User-Password')
            GROUP BY c.id, c.username, c.value, ug.groupname
            ORDER BY c.id DESC";

    $rows = fetchAll($sql);
    $users = [];

    foreach ($rows as $row) {
        $rowService = (string) ($row['service_type'] ?? '');
        if ($serviceType === 'Framed-User') {
            if ($rowService !== 'Framed-User') {
                continue;
            }
        } else {
            if ($rowService === 'Framed-User') {
                continue;
            }
        }

        $sessionTimeout = isset($row['session_timeout']) && $row['session_timeout'] !== null
            ? trim((string) $row['session_timeout'])
            : '';

        $users[] = [
            '.id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['username'] ?? ''),
            'password' => (string) ($row['password'] ?? ''),
            'profile' => (string) ($row['profile'] ?? 'default'),
            'comment' => (string) ($row['user_comment'] ?? ''),
            'limit-uptime' => $sessionTimeout !== '' ? $sessionTimeout : '∞',
            'validity' => $sessionTimeout !== '' ? $sessionTimeout : '-',
            'price' => is_numeric($row['voucher_price'] ?? null) ? (float) $row['voucher_price'] : 0,
            'rate-limit' => (string) ($row['rate_limit'] ?? ''),
            'idle-timeout' => (string) ($row['idle_timeout'] ?? ''),
            'address-pool' => (string) ($row['address_pool'] ?? ''),
            'parent-queue' => (string) ($row['parent_queue'] ?? ''),
            'limit-bytes-total' => is_numeric($row['bytes_total'] ?? null) ? (int) $row['bytes_total'] : 0,
            'uptime' => '0s',
            'bytes-in' => 0,
            'bytes-out' => 0,
            'server' => 'radius',
            'disabled' => ((string) ($row['disabled_status'] ?? 'false') === 'true') ? 'true' : 'false',
        ];
    }

    return $users;
}

function radiusGetHotspotUsers()
{
    $loginUsers = radiusGetUsersByService('Login-User');
    $framedUsers = radiusGetUsersByService('Framed-User');

    foreach ($framedUsers as $user) {
        if (radiusLooksLikeHotspotUser($user)) {
            $loginUsers[] = $user;
        }
    }

    return $loginUsers;
}

function radiusLooksLikeHotspotUser($user)
{
    if (!is_array($user)) {
        return false;
    }

    $profile = strtolower(trim((string) ($user['profile'] ?? '')));
    $hotspotProfiles = radiusGetHotspotProfileNameSet();
    if ($profile !== '' && isset($hotspotProfiles[$profile])) {
        return true;
    }

    if (trim((string) ($user['rate-limit'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($user['idle-timeout'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($user['address-pool'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($user['parent-queue'] ?? '')) !== '') {
        return true;
    }

    $bytesTotal = (int) ($user['limit-bytes-total'] ?? 0);
    if ($bytesTotal > 0) {
        return true;
    }

    $uptime = trim((string) ($user['limit-uptime'] ?? ''));
    if ($uptime !== '' && $uptime !== '∞') {
        return true;
    }

    return false;
}

function radiusGetHotspotProfileNameSet()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $db = radiusDbNameValue();
    if ($db === null) {
        return $cache;
    }

    if (!radiusTableAvailable('radius_hotspot_profiles')) {
        return $cache;
    }

    $table = radiusQualifiedTable('radius_hotspot_profiles');

    $rows = fetchAll("SELECT profile_name FROM {$table}");
    foreach ($rows as $row) {
        $name = strtolower(trim((string) ($row['profile_name'] ?? '')));
        if ($name !== '') {
            $cache[$name] = true;
        }
    }

    return $cache;
}

function radiusGetHotspotProfilesCloud()
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return [];
    }

    $table = radiusQualifiedTable('radius_hotspot_profiles');
    query("CREATE TABLE IF NOT EXISTS {$table} (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_name VARCHAR(64) NOT NULL UNIQUE,
        shared_users INT NOT NULL DEFAULT 1,
        rate_limit VARCHAR(64) NOT NULL DEFAULT '',
        session_timeout VARCHAR(64) NOT NULL DEFAULT '',
        idle_timeout VARCHAR(64) NOT NULL DEFAULT '',
        address_pool VARCHAR(64) NOT NULL DEFAULT '',
        parent_queue VARCHAR(64) NOT NULL DEFAULT '',
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        on_login TEXT NULL,
        comment TEXT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hasPrice = fetchOne(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = 'radius_hotspot_profiles' AND column_name = 'price' LIMIT 1",
        [$db]
    );
    if (!$hasPrice) {
        query("ALTER TABLE {$table} ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER parent_queue");
    }

    $hasSellingPrice = fetchOne(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = 'radius_hotspot_profiles' AND column_name = 'selling_price' LIMIT 1",
        [$db]
    );
    if (!$hasSellingPrice) {
        query("ALTER TABLE {$table} ADD COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER price");
    }

    $profiles = [];

    $rows = fetchAll("SELECT * FROM {$table} ORDER BY profile_name ASC");
    foreach ($rows as $row) {
        $parsedMeta = parseMikhmonOnLogin((string) ($row['on_login'] ?? ''));
        $storedPrice = is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0;
        $storedSellingPrice = is_numeric($row['selling_price'] ?? null) ? (float) $row['selling_price'] : 0;
        $profiles[] = [
            '.id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['profile_name'] ?? ''),
            'shared-users' => (string) ($row['shared_users'] ?? '1'),
            'rate-limit' => (string) ($row['rate_limit'] ?? ''),
            'session-timeout' => (string) ($row['session_timeout'] ?? ''),
            'idle-timeout' => (string) ($row['idle_timeout'] ?? ''),
            'address-pool' => (string) ($row['address_pool'] ?? ''),
            'parent-queue' => (string) ($row['parent_queue'] ?? ''),
            'price' => $storedPrice > 0 ? $storedPrice : (float) ($parsedMeta['price'] ?? 0),
            'selling-price' => $storedSellingPrice > 0 ? $storedSellingPrice : (float) ($parsedMeta['selling_price'] ?? 0),
            'on-login' => (string) ($row['on_login'] ?? ''),
            'comment' => (string) ($row['comment'] ?? ''),
        ];
    }

    if (empty($profiles) && radiusTableAvailable('radusergroup')) {
        $table = radiusQualifiedTable('radusergroup');
        $rows = fetchAll("SELECT DISTINCT groupname FROM {$table} WHERE groupname IS NOT NULL AND groupname <> '' ORDER BY groupname ASC");
        foreach ($rows as $row) {
            $profiles[] = [
                'name' => (string) ($row['groupname'] ?? ''),
                'session-timeout' => '',
                'on-login' => '',
            ];
        }
    }

    if (empty($profiles)) {
        $profiles[] = ['name' => 'default', 'session-timeout' => '', 'on-login' => ''];
    }

    return $profiles;
}

function radiusUpsertHotspotProfileCloud($id, $data)
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return false;
    }

    // Ensure table exists
    radiusGetHotspotProfilesCloud();
    $table = radiusQualifiedTable('radius_hotspot_profiles');

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return false;
    }

    $sharedUsers = max(1, (int) ($data['shared-users'] ?? 1));
    $rateLimit = trim((string) ($data['rate-limit'] ?? ''));
    $sessionTimeout = trim((string) ($data['session-timeout'] ?? ''));
    $idleTimeout = trim((string) ($data['idle-timeout'] ?? ''));
    $addressPool = trim((string) ($data['address-pool'] ?? ''));
    $parentQueue = trim((string) ($data['parent-queue'] ?? ''));
    $onLogin = (string) ($data['on-login'] ?? '');
    $comment = (string) ($data['comment'] ?? '');
    $parsedMeta = parseMikhmonOnLogin($onLogin);
    $price = isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : (float) ($parsedMeta['price'] ?? 0);
    $sellingPrice = isset($data['selling-price']) && is_numeric($data['selling-price']) ? (float) $data['selling-price'] : (float) ($parsedMeta['selling_price'] ?? 0);
    $now = date('Y-m-d H:i:s');

    $existing = null;
    if ($id !== null && $id !== '') {
        $existing = fetchOne("SELECT id FROM {$table} WHERE id = ?", [(int) $id]);
    }

    if ($existing) {
        return query(
            "UPDATE {$table}
             SET profile_name = ?, shared_users = ?, rate_limit = ?, session_timeout = ?, idle_timeout = ?, address_pool = ?, parent_queue = ?, price = ?, selling_price = ?, on_login = ?, comment = ?, updated_at = ?
             WHERE id = ?",
            [$name, $sharedUsers, $rateLimit, $sessionTimeout, $idleTimeout, $addressPool, $parentQueue, $price, $sellingPrice, $onLogin, $comment, $now, (int) $id]
        ) !== false;
    }

    return query(
        "INSERT INTO {$table} (profile_name, shared_users, rate_limit, session_timeout, idle_timeout, address_pool, parent_queue, price, selling_price, on_login, comment, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $sharedUsers, $rateLimit, $sessionTimeout, $idleTimeout, $addressPool, $parentQueue, $price, $sellingPrice, $onLogin, $comment, $now, $now]
    ) !== false;
}

function radiusDeleteHotspotProfileCloud($id)
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return false;
    }

    $usageCount = radiusHotspotProfileUsageCount($id);
    if ($usageCount > 0) {
        logError('Delete hotspot profile blocked: profile is still used in radusergroup. id=' . (int) $id . ', count=' . $usageCount);
        return false;
    }

    $table = radiusQualifiedTable('radius_hotspot_profiles');
    return query("DELETE FROM {$table} WHERE id = ?", [(int) $id]) !== false;
}

function radiusHotspotProfileUsageCount($id)
{
    $db = radiusDbNameValue();
    if ($db === null) {
        return 0;
    }

    $profileTable = radiusQualifiedTable('radius_hotspot_profiles');
    $profileRow = fetchOne("SELECT profile_name FROM {$profileTable} WHERE id = ? LIMIT 1", [(int) $id]);
    $profileName = trim((string) ($profileRow['profile_name'] ?? ''));
    if ($profileName === '') {
        return 0;
    }

    if (!radiusTableAvailable('radusergroup')) {
        return 0;
    }

    $userGroupTable = radiusQualifiedTable('radusergroup');
    $row = fetchOne("SELECT COUNT(*) AS total FROM {$userGroupTable} WHERE groupname = ?", [$profileName]);
    return max(0, (int) ($row['total'] ?? 0));
}

function buildHotspotVoucherPrintData($generatedVouchers, $hotspotName = 'ANS Radius', $dnsName = 'hotspot.net')
{
    if (!is_array($generatedVouchers) || empty($generatedVouchers)) {
        return [];
    }

    $profiles = function_exists('radiusGetHotspotProfilesCloud') ? radiusGetHotspotProfilesCloud() : [];
    $profileMetaMap = [];

    foreach ($profiles as $p) {
        $name = (string) ($p['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $price = isset($p['selling-price']) && is_numeric($p['selling-price']) ? (float) $p['selling-price'] : 0;
        if ($price <= 0) {
            $price = isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : 0;
        }
        $validity = trim((string) ($p['session-timeout'] ?? ''));

        if ($price <= 0) {
            $parsed = parseMikhmonOnLogin((string) ($p['on-login'] ?? ''));
            if (isset($parsed['selling_price']) && is_numeric($parsed['selling_price']) && (float) $parsed['selling_price'] > 0) {
                $price = (float) $parsed['selling_price'];
            } elseif (isset($parsed['price']) && is_numeric($parsed['price']) && (float) $parsed['price'] > 0) {
                $price = (float) $parsed['price'];
            }
            if ($validity === '' && !empty($parsed['validity']) && $parsed['validity'] !== '-') {
                $validity = (string) $parsed['validity'];
            }
        }

        $profileMetaMap[$name] = [
            'price' => $price,
            'validity' => $validity,
        ];
    }

    $result = [];
    foreach ($generatedVouchers as $v) {
        if (!is_array($v)) {
            continue;
        }

        $profile = (string) ($v['profile'] ?? '');
        $meta = $profileMetaMap[$profile] ?? ['price' => 0, 'validity' => ''];

        $sessionPrice = trim((string) ($v['price'] ?? ''));
        $resolvedPrice = $sessionPrice;
        if ($resolvedPrice === '' || $resolvedPrice === '-') {
            if (is_numeric($meta['price']) && (float) $meta['price'] > 0) {
                $resolvedPrice = 'Rp ' . number_format((float) $meta['price'], 0, ',', '.');
            } else {
                $resolvedPrice = '-';
            }
        }

        $sessionValidity = trim((string) ($v['validity'] ?? ''));
        $resolvedValidity = $sessionValidity;
        if ($resolvedValidity === '' || $resolvedValidity === '-') {
            $resolvedValidity = !empty($meta['validity']) ? (string) $meta['validity'] : '-';
        }

        $result[] = [
            'username' => (string) ($v['username'] ?? ''),
            'password' => (string) ($v['password'] ?? ''),
            'profile' => $profile,
            'price' => $resolvedPrice,
            'validity' => $resolvedValidity,
            'hotspotname' => $hotspotName,
            'dnsname' => $dnsName,
        ];
    }

    return $result;
}

// Get MikroTik Hotspot Users
function mikrotikGetHotspotUsers()
{
    return radiusGetHotspotUsers();

    // $socket = getMikrotikConnection();
    // if (!$socket) {
    //     return [];
    // }

    // // Get hotspot users
    // mikrotikWrite($socket, '/ip/hotspot/user/print');
    // mikrotikWrite($socket, '');

    // $allWords = [];
    // $done = false;
    // $timeout = time() + 10;

    // while (!$done && time() < $timeout) {
    //     $words = mikrotikReadSentence($socket);
    //     if (empty($words))
    //         break;
    //     foreach ($words as $word) {
    //         $allWords[] = $word;
    //         if ($word === '!done') {
    //             $done = true;
    //             break;
    //         }
    //     }
    // }

    // // Do NOT fclose() — this is a shared persistent connection

    // $users = [];
    // $currentUser = [];

    // foreach ($allWords as $word) {
    //     if ($word === '!re' || $word === '!done') {
    //         if (!empty($currentUser)) {
    //             // Ensure default keys
    //             $currentUser['name'] = $currentUser['name'] ?? '';
    //             $currentUser['profile'] = $currentUser['profile'] ?? 'default';
    //             $currentUser['comment'] = $currentUser['comment'] ?? '';
    //             $currentUser['limit-uptime'] = $currentUser['limit-uptime'] ?? '∞';
    //             $currentUser['limit-bytes-total'] = $currentUser['limit-bytes-total'] ?? 0;
    //             $currentUser['uptime'] = $currentUser['uptime'] ?? '0s';
    //             $currentUser['bytes-in'] = $currentUser['bytes-in'] ?? 0;
    //             $currentUser['bytes-out'] = $currentUser['bytes-out'] ?? 0;

    //             $users[] = $currentUser;
    //             $currentUser = [];
    //         }
    //     } elseif (strpos($word, '=') === 0) {
    //         $word = substr($word, 1);
    //         $parts = explode('=', $word, 2);
    //         if (count($parts) === 2) {
    //             $currentUser[$parts[0]] = $parts[1];
    //         }
    //     }
    // }

    // return $users;
}
// Update MikroTik Hotspot User
function mikrotikUpdateHotspotUser($id, $data)
{
    if (radiusUserProvisioningReady()) {
        $oldUsername = radiusResolveUsernameById($id);
        if ($oldUsername === null || $oldUsername === '') {
            return false;
        }

        $newUsername = trim((string) ($data['name'] ?? $oldUsername));
        if ($newUsername === '') {
            return false;
        }

        if ($newUsername !== $oldUsername) {
            radiusRenameUser($oldUsername, $newUsername);
        }

        $password = isset($data['password']) ? (string) $data['password'] : '';
        if ($password === '') {
            $radcheck = radiusQualifiedTable('radcheck');
            $pwd = fetchOne("SELECT value FROM {$radcheck} WHERE username = ? AND attribute IN ('Cleartext-Password','User-Password') ORDER BY id DESC LIMIT 1", [$newUsername]);
            $password = (string) ($pwd['value'] ?? '');
        }

        $profile = trim((string) ($data['profile'] ?? 'default'));
        $reply = [];
        if (isset($data['price']) && is_numeric($data['price'])) {
            $reply['Mikrotik-Price'] = (string) ((float) $data['price']);
        }
        if (isset($data['limit-uptime']) && trim((string) $data['limit-uptime']) !== '') {
            $seconds = mikrotikDurationToSeconds((string) $data['limit-uptime']);
            if ($seconds !== null && $seconds > 0) {
                $reply['Session-Timeout'] = (string) $seconds;
            }
        }
        if (isset($data['limit-bytes-total']) && trim((string) $data['limit-bytes-total']) !== '') {
            $limit = mikrotikBytesToInteger((string) $data['limit-bytes-total']);
            if ($limit !== null && $limit > 0) {
                $limitValue = (string) $limit;
                $reply['Mikrotik-Recv-Limit'] = $limitValue;
                $reply['Mikrotik-Xmit-Limit'] = $limitValue;
                $reply['Mikrotik-Total-Limit'] = $limitValue;
            }
        }
        if (isset($data['disabled'])) {
            $reply['Mikrotik-Disabled'] = ((string) $data['disabled'] === 'true') ? 'true' : 'false';
        }

        return radiusSetUser($newUsername, $password, $profile, 'Login-User', $reply);
    }

    // logError('Hotspot update blocked: Radius DB is not ready.');
    // return false;

    // $socket = getMikrotikConnection();
    // if (!$socket)
    //     return false;

    // mikrotikWrite($socket, '/ip/hotspot/user/set');
    // mikrotikWrite($socket, '=.id=' . $id);
    // if (isset($data['name']))
    //     mikrotikWrite($socket, '=name=' . $data['name']);
    // if (isset($data['password']))
    //     mikrotikWrite($socket, '=password=' . $data['password']);
    // if (isset($data['profile']))
    //     mikrotikWrite($socket, '=profile=' . $data['profile']);
    // if (isset($data['limit-uptime']))
    //     mikrotikWrite($socket, '=limit-uptime=' . $data['limit-uptime']);
    // if (isset($data['limit-bytes-total']))
    //     mikrotikWrite($socket, '=limit-bytes-total=' . $data['limit-bytes-total']);
    // if (isset($data['comment']))
    //     mikrotikWrite($socket, '=comment=' . $data['comment']);
    // if (isset($data['disabled']))
    //     mikrotikWrite($socket, '=disabled=' . $data['disabled']);
    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);
    // // Do NOT fclose() — this is a shared persistent connection
    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0)
    //         return false;
    // }
    // return true;
}

// Get Active Hotspot Users
function mikrotikGetHotspotActive()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    // Do NOT fclose() — this is a shared persistent connection

    $active = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $current['user'] = $current['user'] ?? '';
                $current['address'] = $current['address'] ?? '';
                $current['uptime'] = $current['uptime'] ?? '0s';

                $active[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $active;
}

// Update Hotspot Profile
function mikrotikUpdateHotspotProfile($id, $data)
{
    return radiusUpsertHotspotProfileCloud($id, $data);

    // $socket = getMikrotikConnection();
    // if (!$socket)
    //     return false;

    // mikrotikWrite($socket, '/ip/hotspot/user/profile/set');
    // mikrotikWrite($socket, '=.id=' . $id);
    // if (isset($data['name']))
    //     mikrotikWrite($socket, '=name=' . $data['name']);
    // if (isset($data['shared-users']))
    //     mikrotikWrite($socket, '=shared-users=' . $data['shared-users']);
    // if (isset($data['session-timeout'])) {
    //     $sessionTimeout = mikrotikNormalizeTimeoutValue($data['session-timeout']);
    //     if ($sessionTimeout !== null && $sessionTimeout !== '') {
    //         mikrotikWrite($socket, '=session-timeout=' . $sessionTimeout);
    //     }
    // }
    // if (isset($data['rate-limit']))
    //     mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    // if (isset($data['keepalive-timeout']))
    //     mikrotikWrite($socket, '=keepalive-timeout=' . $data['keepalive-timeout']);
    // if (isset($data['idle-timeout']))
    //     mikrotikWrite($socket, '=idle-timeout=' . $data['idle-timeout']);
    // if (isset($data['address-pool']))
    //     mikrotikWrite($socket, '=address-pool=' . $data['address-pool']);
    // if (isset($data['parent-queue']))
    //     mikrotikWrite($socket, '=parent-queue=' . $data['parent-queue']);
    // if (isset($data['on-login']))
    //     mikrotikWrite($socket, '=on-login=' . $data['on-login']);
    // if (isset($data['comment']))
    //     mikrotikWrite($socket, '=comment=' . $data['comment']);
    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);
    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0) {
    //         $trap = mikrotikTrapMessageFromResponse($response);
    //         if ($trap !== '') {
    //             logError('MikroTik update hotspot profile failed: ' . $trap);
    //         } else {
    //             logError('MikroTik update hotspot profile failed.');
    //         }
    //         return false;
    //     }
    // }
    // return true;
}

// Add Hotspot Profile
function mikrotikAddHotspotProfile($data)
{
    return radiusUpsertHotspotProfileCloud(null, $data);

    // $socket = getMikrotikConnection();
    // if (!$socket)
    //     return false;

    // mikrotikWrite($socket, '/ip/hotspot/user/profile/add');
    // if (isset($data['name']))
    //     mikrotikWrite($socket, '=name=' . $data['name']);
    // if (isset($data['shared-users']))
    //     mikrotikWrite($socket, '=shared-users=' . $data['shared-users']);
    // if (isset($data['session-timeout'])) {
    //     $sessionTimeout = mikrotikNormalizeTimeoutValue($data['session-timeout']);
    //     if ($sessionTimeout !== null && $sessionTimeout !== '') {
    //         mikrotikWrite($socket, '=session-timeout=' . $sessionTimeout);
    //     }
    // }
    // if (isset($data['rate-limit']))
    //     mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    // if (isset($data['keepalive-timeout']))
    //     mikrotikWrite($socket, '=keepalive-timeout=' . $data['keepalive-timeout']);
    // if (isset($data['idle-timeout']))
    //     mikrotikWrite($socket, '=idle-timeout=' . $data['idle-timeout']);
    // if (isset($data['address-pool']))
    //     mikrotikWrite($socket, '=address-pool=' . $data['address-pool']);
    // if (isset($data['parent-queue']))
    //     mikrotikWrite($socket, '=parent-queue=' . $data['parent-queue']);
    // if (isset($data['on-login']))
    //     mikrotikWrite($socket, '=on-login=' . $data['on-login']);
    // if (isset($data['comment']))
    //     mikrotikWrite($socket, '=comment=' . $data['comment']);
    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);
    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0) {
    //         $trap = mikrotikTrapMessageFromResponse($response);
    //         if ($trap !== '') {
    //             logError('MikroTik add hotspot profile failed: ' . $trap);
    //         } else {
    //             logError('MikroTik add hotspot profile failed.');
    //         }
    //         return false;
    //     }
    // }
    // return true;
}

// Delete Hotspot Profile
function mikrotikDeleteHotspotProfile($id)
{
    return radiusDeleteHotspotProfileCloud($id);

    // $socket = getMikrotikConnection();
    // if (!$socket)
    //     return false;

    // mikrotikWrite($socket, '/ip/hotspot/user/profile/remove');
    // mikrotikWrite($socket, '=.id=' . $id);
    // mikrotikWrite($socket, '');

    // $response = mikrotikReadSentence($socket);
    // fclose($socket);
    // foreach ($response as $word) {
    //     if (strpos($word, '!trap') === 0) {
    //         $trap = mikrotikTrapMessageFromResponse($response);
    //         if ($trap !== '') {
    //             logError('MikroTik delete hotspot profile failed: ' . $trap);
    //         } else {
    //             logError('MikroTik delete hotspot profile failed.');
    //         }
    //         return false;
    //     }
    // }
    // return true;
}

// Generate Mikhmon v3-style on-login script
// Mikhmon v3 format: on-login script stores comma-separated values:
// index[0]=script, [1]=script, [2]=price, [3]=validity, [4]=sellingPrice, [5]=script, [6]=lockUser
function generateHotspotExpiryScript($mode, $price = 0, $validity = '', $sellingPrice = 0, $lockUser = 'disable')
{
    // Mikhmon v3 on-login script structure (simplified)
    // The comma-separated string stores metadata at fixed positions
    $script = '';

    if ($mode === 'remove') {
        // Script that removes user after expiry
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } elseif ($mode === 'notice') {
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } elseif ($mode === 'record') {
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } else {
        // mode 'none' - only store metadata, no expiry action
        $script = ':nothing';
    }

    $price = (int) $price;
    $sellingPrice = (int) $sellingPrice;

    // Mikhmon v3 comma-separated format at fixed positions:
    // [0]=script, [1]=(unused), [2]=price, [3]=validity, [4]=sellingPrice, [5]=(unused), [6]=lockUser
    $onLoginData = $script . ',' . $mode . ',' . $price . ',' . $validity . ',' . $sellingPrice . ',0,' . $lockUser;

    return $onLoginData;
}

// Parse Mikhmon v3 on-login script to extract price, validity, selling price, lock user
// Based on Mikhmon v3 source: process/getvalidprice.php
function parseMikhmonOnLogin($onLoginScript)
{
    $data = [
        'price' => 0,
        'validity' => '-',
        'selling_price' => 0,
        'datalimit' => '',
        'timelimit' => '',
        'lock_user' => 'disable',
        'mode' => 'none',
    ];

    if (empty($onLoginScript))
        return $data;

    $parts = explode(',', $onLoginScript);

    // Mikhmon v3 indices: [1]=mode, [2]=price, [3]=validity, [4]=sellingPrice, [5]=datalimit, [6]=timelimit, [7]=lockUser
    if (isset($parts[1]) && !empty($parts[1])) {
        $data['mode'] = $parts[1];
    }
    if (isset($parts[2]) && is_numeric($parts[2])) {
        $data['price'] = (int) $parts[2];
    }
    if (isset($parts[3]) && !empty($parts[3])) {
        $data['validity'] = $parts[3];
    }
    if (isset($parts[4]) && is_numeric($parts[4])) {
        $data['selling_price'] = (int) $parts[4];
    }
    if (isset($parts[5]) && !empty($parts[5])) {
        $data['datalimit'] = $parts[5];
    }
    if (isset($parts[6]) && !empty($parts[6])) {
        $data['timelimit'] = $parts[6];
    }
    if (isset($parts[7]) && !empty($parts[7])) {
        $data['lock_user'] = $parts[7];
    }

    return $data;
}

// Get MikroTik System Resource (CPU, Memory, Uptime, Board Name, etc.)
function mikrotikGetSystemResource()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [
            'board-name' => '-',
            'cpu-load' => 0,
            'free-memory' => 0,
            'total-memory' => 0,
            'uptime' => '-',
            'version' => '-',
            'architecture-name' => '-',
        ];
    }

    mikrotikWrite($socket, '/system/resource/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $resource = [];
    foreach ($allWords as $word) {
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $resource[$parts[0]] = $parts[1];
            }
        }
    }

    return [
        'board-name' => $resource['board-name'] ?? '-',
        'cpu-load' => (int) ($resource['cpu-load'] ?? 0),
        'free-memory' => (int) ($resource['free-memory'] ?? 0),
        'total-memory' => (int) ($resource['total-memory'] ?? 0),
        'uptime' => $resource['uptime'] ?? '-',
        'version' => $resource['version'] ?? '-',
        'architecture-name' => $resource['architecture-name'] ?? '-',
    ];
}

// Get list of MikroTik interfaces
function mikrotikGetInterfaces()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/interface/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $interfaces = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $interfaces[] = $current;
            }
            $current = [];
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }
    }
    if (!empty($current)) {
        $interfaces[] = $current;
    }

    return $interfaces;
}

// Monitor traffic on a specific interface (one-shot read)
function mikrotikMonitorTraffic($interfaceName)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return ['tx' => 0, 'rx' => 0];

    mikrotikWrite($socket, '/interface/monitor-traffic');
    mikrotikWrite($socket, '=interface=' . $interfaceName);
    mikrotikWrite($socket, '=once=');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $data = [];
    foreach ($allWords as $word) {
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $data[$parts[0]] = $parts[1];
            }
        }
    }

    return [
        'tx' => (int) ($data['tx-bits-per-second'] ?? 0),
        'rx' => (int) ($data['rx-bits-per-second'] ?? 0),
    ];
}

// Get Hotspot Log entries from MikroTik
function mikrotikGetHotspotLog($limit = 20)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/log/print');
    mikrotikWrite($socket, '?topics=hotspot,info,debug');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $logs = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $logs[] = $current;
            }
            $current = [];
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }
    }
    if (!empty($current)) {
        $logs[] = $current;
    }

    // Return last N entries in reverse order (newest first)
    $logs = array_reverse($logs);
    return array_slice($logs, 0, $limit);
}

// Get MikroTik Address Pools
function mikrotikGetAddressPools()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/pool/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $pools = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $pools[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $pools;
}

// Get MikroTik Parent Queues
function mikrotikGetParentQueues()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/queue/simple/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $queues = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $queues[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $queues;
}

// Record Hotspot Sale in Database
function recordHotspotSale($username, $profile, $price, $sellingPrice, $prefix = '', $salesUserId = null)
{
    $data = [
        'username' => sanitize($username),
        'profile' => sanitize($profile),
        'price' => (float) $price,
        'selling_price' => (float) $sellingPrice,
        'prefix' => sanitize($prefix),
        'sales_user_id' => $salesUserId,
        'created_at' => date('Y-m-d H:i:s')
    ];

    try {
        return insert('hotspot_sales', $data);
    } catch (Exception $e) {
        logError("Failed to record hotspot sale: " . $e->getMessage());
        return false;
    }
}

// Kick (remove) an active hotspot user session
function mikrotikKickHotspotUser($username)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    // First find the session .id
    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '?user=' . $username);
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 5;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $sessionId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $sessionId = substr($word, 5);
            break;
        }
    }

    if (!$sessionId)
        return false;

    // Remove the session
    mikrotikWrite($socket, '/ip/hotspot/active/remove');
    mikrotikWrite($socket, '=.id=' . $sessionId);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Get MikroTik Hotspot Cookies
function mikrotikGetHotspotCookies()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/cookie/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $cookies = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $cookies[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $cookies;
}

// Delete a hotspot cookie
function mikrotikDeleteHotspotCookie($id)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/cookie/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Get MikroTik Hotspot Hosts (connected devices)
function mikrotikGetHotspotHosts()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/host/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $hosts = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $hosts[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $hosts;
}

// Get MikroTik System Schedulers
function mikrotikGetSchedulers()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/system/scheduler/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $schedulers = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $schedulers[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $schedulers;
}

// Get MikroTik Resource
function mikrotikGetResource() {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return null;
    }
    
    mikrotikWrite($socket, '/system/resource/print');
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $resource = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            continue;
        }
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $resource[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $resource;
}

// Ping from MikroTik
function mikrotikPing($target, $count = 4) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return null;
    }
    
    mikrotikWrite($socket, '/ping');
    mikrotikWrite($socket, '=address=' . $target);
    mikrotikWrite($socket, '=count=' . (int)$count);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 15;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $sent = 0;
    $received = 0;
    $lost = 0;
    $latencies = [];
    
    foreach ($allWords as $word) {
        if (strpos($word, '=sent=') === 0) {
            $sent = (int)substr($word, 6);
        } elseif (strpos($word, '=received=') === 0) {
            $received = (int)substr($word, 10);
        } elseif (strpos($word, '=packet-loss=') === 0) {
            $lost = (int)substr($word, 13);
        } elseif (strpos($word, '=time=') === 0) {
            $latencies[] = (float)substr($word, 6);
        }
    }
    
    $avg = null;
    if (!empty($latencies)) {
        $avg = array_sum($latencies) / count($latencies);
    }
    
    return [
        'sent' => $sent,
        'received' => $received,
        'loss' => $lost,
        'avg' => $avg
    ];
}

// Remove Active Session by Name
function mikrotikRemoveActiveSessionByName($username) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }
    
    mikrotikWrite($socket, '/ppp/active/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $sessionId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $sessionId = substr($word, 5);
            break;
        }
    }
    
    if (!$sessionId) {
        return false;
    }
    
    mikrotikWrite($socket, '/ppp/active/remove');
    mikrotikWrite($socket, '=.id=' . $sessionId);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

function mikrotikGetSecretByName($username) {
    $socket = getMikrotikConnection();
    if (!$socket) return null;
    
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') { $done = true; break; }
        }
    }
    
    $secrets = mikrotikParseUsers($allWords);
    return !empty($secrets) ? $secrets[0] : null;
}
