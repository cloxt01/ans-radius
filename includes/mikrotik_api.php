<?php

// Ensure config is loaded
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (file_exists(__DIR__ . '/radius.php')) {
    require_once __DIR__ . '/radius.php';
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
    if (radiusUserProvisioningReady()) {
        return radiusGetPppoeProfilesCloud();
    }

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
    if (radiusUserProvisioningReady()) {
        return radiusUpsertPppoeProfileCloud(null, $data);
    }

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
    if (radiusUserProvisioningReady()) {
        return radiusUpsertPppoeProfileCloud($id, $data);
    }

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
    if (radiusUserProvisioningReady()) {
        return radiusDeletePppoeProfileCloud($id);
    }

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

// PPPoE Profile helpers (service-level wrappers)
function pppoeNormalizeProfileData($data)
{
    if (!is_array($data)) {
        return [];
    }

    $normalized = [];

    if (isset($data['name'])) {
        $name = trim((string) $data['name']);
        if ($name !== '') {
            $normalized['name'] = $name;
        }
    }

    $map = [
        'rate-limit' => ['rate-limit', 'rate_limit'],
        'local-address' => ['local-address', 'local_address'],
        'remote-address' => ['remote-address', 'remote_address', 'remote_pool'],
        'dns-server' => ['dns-server', 'dns_server'],
        'comment' => ['comment'],
    ];

    foreach ($map as $target => $keys) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);
            if ($value === '' || strtolower($value) === 'none') {
                continue;
            }

            $normalized[$target] = $value;
            break;
        }
    }

    return $normalized;
}

function pppoeGetProfiles()
{
    $profiles = mikrotikGetProfiles();
    if (!is_array($profiles) || empty($profiles)) {
        return [];
    }

    return array_values(array_filter($profiles, function ($profile) {
        $serviceType = strtolower(trim((string) ($profile['service-type'] ?? $profile['service_type'] ?? '')));
        $service = strtolower(trim((string) ($profile['service'] ?? '')));

        if ($serviceType === 'framed-user') {
            return true;
        }

        return $service === 'pppoe';
    }));
}

function pppoeCreateProfile($data)
{
    $payload = pppoeNormalizeProfileData($data);
    if (empty($payload['name'])) {
        return false;
    }

    return mikrotikAddPppoeProfile($payload);
}

function pppoeUpdateProfile($id, $data)
{
    $id = trim((string) $id);
    if ($id === '') {
        return false;
    }

    $payload = pppoeNormalizeProfileData($data);
    if (empty($payload)) {
        return false;
    }

    return mikrotikUpdatePppoeProfile($id, $payload);
}

function pppoeDeleteProfile($id)
{
    $id = trim((string) $id);
    if ($id === '') {
        return false;
    }

    return mikrotikDeletePppoeProfile($id);
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

// ============================================
// RADIUS DATABASE FUNCTIONS (Procedural Style)
// ============================================

// ============================================
// HOTSPOT USER MANAGEMENT
// ============================================

/**
 * Create Hotspot User (Flow: RADIUS → MikroTik fallback)
 */
function createHotspotUser($username, $password, $profile) {
    if (radiusUserProvisioningReady()) {
        return createHotspotUserRadius($username, $password, $profile);
    }
    
    // Fallback to direct MikroTik API
    return createHotspotUserMikrotik($username, $password, $profile);
}

/**
 * Create Hotspot User via RADIUS Database
 */
function createHotspotUserRadius($username, $password, $profile) {
    radiusBeginTransaction();
    
    try {
        // 1. Insert AUTH (Cleartext-Password)
        insertRadcheck($username, 'Cleartext-Password', ':=', $password);
        
        // 2. Insert SERVICE TYPE
        insertRadcheck($username, 'Service-Type', ':=', 'Login-User');
        
        // 3. Insert GROUP
        insertRadusergroup($username, $profile, 1);
        
        radiusCommit();
        return true;
        
    } catch (Exception $e) {
        radiusRollback();
        logError("Failed to create hotspot user: " . $e->getMessage());
        return false;
    }
}

/**
 * Create Hotspot User via Direct MikroTik API (Fallback)
 */
function createHotspotUserMikrotik($username, $password, $profile) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }
    
    mikrotikWrite($socket, '/ip/hotspot/user/add');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Insert radcheck record
 */
function insertRadcheck($username, $attribute, $op, $value) {
    $pdo = radiusDbConnection();
    $sql = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$username, $attribute, $op, $value]);
}

/**
 * Insert radusergroup record
 */
function insertRadusergroup($username, $groupname, $priority = 1) {
    $pdo = radiusDbConnection();
    $sql = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$username, $groupname, $priority]);
}

// ============================================
// HOTSPOT PROFILE MANAGEMENT
// ============================================

/**
 * Create or Update Hotspot Profile
 */
function createHotspotProfile($data) {
    if (!radiusUserProvisioningReady()) {
        logError('Hotspot profile creation blocked: Radius DB is not ready.');
        return false;
    }
    
    radiusBeginTransaction();
    
    try {
        // 1. Convert data formats
        $data = convertProfileData($data);
        
        // 2. Save to local hotspot_profiles table
        saveHotspotProfileLocal($data);
        
        // 3. Sync to RADIUS tables (radgroupreply & radgroupcheck)
        syncRadiusProfileAttributes($data);
        
        radiusCommit();
        return true;
        
    } catch (Exception $e) {
        radiusRollback();
        logError("Failed to save hotspot profile: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert profile data formats (bytes, duration)
 */
function convertProfileData($data) {
    // Convert bytes limits to integer
    if (!empty($data['recv_limit']) && !empty($data['xmit_limit'])) {
        $data['recv_limit'] = mikrotikBytesToInteger($data['recv_limit']);
        $data['xmit_limit'] = mikrotikBytesToInteger($data['xmit_limit']);
    }
    if (!empty($data['total_limit'])) {
        $data['total_limit'] = mikrotikBytesToInteger($data['total_limit']);
    }
    
    // Convert validity/duration to seconds
    if (!empty($data['validity'])) {
        $data['validity'] = mikrotikDurationToSeconds($data['validity']);
    }
    
    return $data;
}

/**
 * Save profile to local hotspot_profiles table
 */
function saveHotspotProfileLocal($data) {
    $pdo = radiusDbConnection();
    
    $payload = [
        'profile_name' => $data['name'],
        'shared_users' => $data['shared_users'] ?? 1,
        'rate_limit' => $data['rate_limit'] ?? '',
        'session_timeout' => $data['validity'] ?? '',
        'idle_timeout' => $data['idle_timeout'] ?? '',
        'address_pool' => $data['address_pool'] ?? '',
        'price' => $data['price'] ?? 0,
        'selling_price' => $data['selling_price'] ?? 0,
        'on_login' => $data['on_login'] ?? null,
        'comment' => $data['comment'] ?? null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    if (!empty($data['id'])) {
        // UPDATE
        $sql = "UPDATE hotspot_profiles SET 
                profile_name = ?, shared_users = ?, rate_limit = ?, 
                session_timeout = ?, idle_timeout = ?, address_pool = ?,
                price = ?, selling_price = ?, on_login = ?, comment = ?, updated_at = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $payload['profile_name'], $payload['shared_users'], $payload['rate_limit'],
            $payload['session_timeout'], $payload['idle_timeout'], $payload['address_pool'],
            $payload['price'], $payload['selling_price'], $payload['on_login'],
            $payload['comment'], $payload['updated_at'], $data['id']
        ]);
    } else {
        // INSERT
        $payload['created_at'] = date('Y-m-d H:i:s');
        $sql = "INSERT INTO hotspot_profiles (
                    profile_name, shared_users, rate_limit, session_timeout, 
                    idle_timeout, address_pool, price, selling_price, 
                    on_login, comment, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $payload['profile_name'], $payload['shared_users'], $payload['rate_limit'],
            $payload['session_timeout'], $payload['idle_timeout'], $payload['address_pool'],
            $payload['price'], $payload['selling_price'], $payload['on_login'],
            $payload['comment'], $payload['created_at'], $payload['updated_at']
        ]);
    }
}

/**
 * Sync profile attributes to RADIUS tables
 */
function syncRadiusProfileAttributes($data) {
    $attributeMap = [
        'rate_limit' => 'Mikrotik-Rate-Limit',
        'validity' => 'Session-Timeout',
        'idle_timeout' => 'Idle-Timeout',
        'address_pool' => 'Framed-Pool',
        'recv_limit' => 'Mikrotik-Recv-Limit',
        'xmit_limit' => 'Mikrotik-Xmit-Limit',
        'total_limit' => 'Mikrotik-Total-Limit',
    ];
    
    $pdo = radiusDbConnection();
    $groupname = $data['name'];
    
    foreach ($attributeMap as $field => $attribute) {
        $value = $data[$field] ?? null;
        
        if (empty($value)) {
            // Delete if exists
            $sql = "DELETE FROM radgroupreply WHERE groupname = ? AND attribute = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$groupname, $attribute]);
            continue;
        }
        
        // Update or Insert
        $sql = "INSERT INTO radgroupreply (groupname, attribute, op, value) 
                VALUES (?, ?, ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$groupname, $attribute, $value, $value]);
    }
    
    // Simultaneous-Use (radgroupcheck)
    $sql = "INSERT INTO radgroupcheck (groupname, attribute, op, value) 
            VALUES (?, 'Simultaneous-Use', ':=', ?)
            ON DUPLICATE KEY UPDATE value = ?";
    $stmt = $pdo->prepare($sql);
    $sharedUsers = $data['shared_users'] ?? 1;
    $stmt->execute([$groupname, $sharedUsers, $sharedUsers]);
}

// ============================================
// DELETE OPERATIONS
// ============================================

/**
 * Delete Hotspot User
 */
function deleteHotspotUser($username) {
    if (radiusUserProvisioningReady()) {
        return deleteUserRadius($username);
    }
    
    return deleteUserMikrotik($username);
}

function mikrotikDeleteHotspotUser($username)
{
    return deleteHotspotUser($username);
}

/**
 * Delete user from RADIUS tables
 */
function deleteUserRadius($username) {
    radiusBeginTransaction();
    
    try {
        $pdo = radiusDbConnection();
        
        // Delete from radcheck
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
        $stmt->execute([$username]);
        
        
        // Delete from radusergroup
        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
        $stmt->execute([$username]);
        
        radiusCommit();
        return true;
        
    } catch (Exception $e) {
        radiusRollback();
        logError("Failed to delete user: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete user from MikroTik directly
 */
function deleteUserMikrotik($username) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }
    
    // Find user .id first
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $userId = null;
    $done = false;
    $timeout = time() + 5;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            if (strpos($word, '=.id=') === 0) {
                $userId = substr($word, 5);
                $done = true;
                break;
            }
        }
    }
    
    if (!$userId) {
        return false;
    }
    
    // Delete user
    mikrotikWrite($socket, '/ip/hotspot/user/remove');
    mikrotikWrite($socket, '=.id=' . $userId);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Delete Hotspot Profile
 */
function deleteHotspotProfile($name) {
    if (!radiusUserProvisioningReady()) {
        logError('Hotspot profile deletion blocked: Radius DB is not ready.');
        return false;
    }
    
    radiusBeginTransaction();
    
    try {
        $pdo = radiusDbConnection();
        
        // Check if profile is used by any user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM radusergroup WHERE groupname = ?");
        $stmt->execute([$name]);
        $usageCount = $stmt->fetchColumn();
        
        if ($usageCount > 0) {
            logError("Cannot delete profile '{$name}' - used by {$usageCount} users");
            radiusRollback();
            return false;
        }
        
        // Delete from hotspot_profiles
        $stmt = $pdo->prepare("DELETE FROM hotspot_profiles WHERE profile_name = ?");
        $stmt->execute([$name]);
        
        // Delete from radgroupreply
        $stmt = $pdo->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
        $stmt->execute([$name]);
        
        // Delete from radusergroup
        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE groupname = ?");
        $stmt->execute([$name]);
        
        // Delete from radgroupcheck
        $stmt = $pdo->prepare("DELETE FROM radgroupcheck WHERE groupname = ?");
        $stmt->execute([$name]);
        
        radiusCommit();
        return true;
        
    } catch (Exception $e) {
        radiusRollback();
        logError("Failed to delete profile: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk Delete Users
 */
function bulkDeleteUsers($usernames) {
    if (empty($usernames)) {
        return true;
    }
    
    if (!radiusUserProvisioningReady()) {
        // Fallback to MikroTik (loop deletion)
        $success = true;
        foreach ($usernames as $username) {
            if (!deleteUserMikrotik($username)) {
                $success = false;
            }
        }
        return $success;
    }
    
    radiusBeginTransaction();
    
    try {
        $pdo = radiusDbConnection();
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username IN ({$placeholders})");
        $stmt->execute($usernames);
        
        
        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username IN ({$placeholders})");
        $stmt->execute($usernames);
        
        radiusCommit();
        return true;
        
    } catch (Exception $e) {
        radiusRollback();
        logError("Failed to bulk delete users: " . $e->getMessage());
        return false;
    }
}

// ============================================
// NAS MANAGEMENT
// ============================================

/**
 * Add or Update NAS
 */
function addNas($name, $ip, $secret) {
    if (!radiusUserProvisioningReady()) {
        return false;
    }
    
    $pdo = radiusDbConnection();
    $sql = "INSERT INTO nas (nasname, shortname, secret, type) 
            VALUES (?, ?, ?, 'other')
            ON DUPLICATE KEY UPDATE shortname = ?, secret = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$ip, $name, $secret, $name, $secret]);
}

/**
 * Delete NAS by ID
 */
function deleteNas($id) {
    if (!radiusUserProvisioningReady()) {
        return false;
    }
    
    $pdo = radiusDbConnection();
    $stmt = $pdo->prepare("DELETE FROM nas WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get All NAS
 */
function getNasList() {
    if (!radiusUserProvisioningReady()) {
        return [];
    }
    
    $pdo = radiusDbConnection();
    $stmt = $pdo->query("SELECT id, shortname, nasname, secret FROM nas ORDER BY id");
    return $stmt->fetchAll();
}

// ============================================
// QUERY/GETTER FUNCTIONS
// ============================================

/**
 * Get Hotspot User by Username
 */
function getHotspotUser($username) {
    if (!radiusUserProvisioningReady()) {
        // Fallback to MikroTik
        $users = mikrotikGetHotspotUsers();
        foreach ($users as $user) {
            if ($user['name'] === $username) {
                return $user;
            }
        }
        return null;
    }
    
    $pdo = radiusDbConnection();
    $sql = "SELECT c.id, c.username, c.value as password, ug.groupname as profile
            FROM radcheck c
            LEFT JOIN radusergroup ug ON ug.username = c.username
            WHERE c.username = ? AND c.attribute IN ('Cleartext-Password', 'User-Password')
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    return $stmt->fetch();
}

/**
 * Get All Hotspot Users
 */
function getAllHotspotUsers() {
    if (!radiusUserProvisioningReady()) {
        return mikrotikGetHotspotUsers();
    }
    
    $pdo = radiusDbConnection();
    $sql = "SELECT c.id, c.username, c.value as password, ug.groupname as profile,
                    rgr.value as rate_limit, rgr2.value as session_timeout
            FROM radcheck c
            LEFT JOIN radusergroup ug ON ug.username = c.username
            LEFT JOIN radgroupreply rgr ON rgr.groupname = ug.groupname AND rgr.attribute = 'Mikrotik-Rate-Limit'
            LEFT JOIN radgroupreply rgr2 ON rgr2.groupname = ug.groupname AND rgr2.attribute = 'Session-Timeout'
            WHERE c.attribute IN ('Cleartext-Password', 'User-Password')
            ORDER BY c.id DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get Profile Names List
 */
function getProfileNames() {
    if (!radiusUserProvisioningReady()) {
        $profiles = mikrotikGetHotspotProfiles();
        return array_column($profiles, 'name');
    }
    
    $pdo = radiusDbConnection();
    $stmt = $pdo->query("SELECT profile_name FROM hotspot_profiles ORDER BY profile_name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get Profile by Username
 */
function getProfileByUser($username) {
    if (!radiusUserProvisioningReady()) {
        $users = mikrotikGetHotspotUsers();
        foreach ($users as $user) {
            if ($user['name'] === $username) {
                return $user['profile'] ?? null;
            }
        }
        return null;
    }
    
    $pdo = radiusDbConnection();
    $stmt = $pdo->prepare("SELECT groupname FROM radusergroup WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $result = $stmt->fetch();
    return $result['groupname'] ?? null;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Duration to seconds (reuse existing function)
 */
function durationToSeconds($duration) {
    return mikrotikDurationToSeconds($duration);
}

/**
 * Bytes to integer (reuse existing function)
 */
function bytesToInt($bytes) {
    return mikrotikBytesToInteger($bytes);
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
}
// Update MikroTik Hotspot User
function mikrotikUpdateHotspotUser($id, $data)
{
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

        $ok = radiusSetUser($newUsername, $password, $profile, 'Login-User', []);
        return [
            'success' => $ok,
            'message' => $ok ? 'User updated in Radius DB' : 'Failed to update user in Radius DB',
        ];
    }

    logError('Hotspot update blocked: Radius DB is not ready.');
    return ['success' => false, 'message' => 'Radius DB is not ready'];
}

// Get Active Hotspot Users
function mikrotikGetHotspotActive()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 30;

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

    $active = [];
    $current = [];

    foreach ($allWords as $word) {
        if ($word === '!done') {
            if (!empty($current)) {
                $active[] = $current;
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($current)) {
                $active[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }
    }

    return $active;
}

// Update Hotspot Profile
function mikrotikUpdateHotspotProfile($id, $data)
{
    return radiusUpsertHotspotProfileCloud($id, $data);

}

// Add Hotspot Profile
function mikrotikAddHotspotProfile($data)
{
    return radiusUpsertHotspotProfileCloud(null, $data);

}

// Delete Hotspot Profile
function mikrotikDeleteHotspotProfile($id)
{
    return radiusDeleteHotspotProfileCloud($id);

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
