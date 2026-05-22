<?php
/**
 * Bottom Navigation Component for Technician Panel
 * Include this file at the bottom of all technician pages (before closing body tag)
 * 
 * Variables required (auto-detected from current page):
 * - $techBase: Base path to technician folder (auto-calculated)
 * - Active states are auto-detected based on current directory and filename
 */

// Get current page context
$current_path = $_SERVER['PHP_SELF'];
$path_parts = explode('/', str_replace('\\', '/', dirname($current_path)));
$current_dir = end($path_parts);

// Determine relative path to technician root
$rel_path = '';
$normalized = array_values(array_filter($path_parts, function($p) { return $p !== ''; }));
$techPos = array_search('technician', $normalized, true);

if ($techPos !== false) {
    $depthAfterTechnician = count($normalized) - ($techPos + 1);
    if ($depthAfterTechnician > 0) {
        $rel_path = str_repeat('../', $depthAfterTechnician);
    }
}

// Build absolute technician base path (e.g., /technician/)
$techBase = '/';
if ($techPos !== false) {
    $techBase = '/' . implode('/', array_slice($normalized, 0, $techPos + 1)) . '/';
}

// Normalize active state check
$current_file = basename($current_path);
$is_home = ($current_file == 'dashboard.php');
$is_tasks = ($current_dir == 'tasks' || strpos($current_file, 'task') !== false);
$is_customers = ($current_dir == 'customers' || strpos($current_file, 'check') !== false);
$is_map = ($current_dir == 'map' || strpos($current_file, 'map') !== false);
$is_devices = ($current_dir == 'devices' || strpos($current_file, 'search') !== false || strpos($current_file, 'manage') !== false);
$is_profile = ($current_file == 'profile.php');
?>

<style>
    /* ==================== BOTTOM NAVIGATION - GITHUB DARK THEME ==================== */
    :root {
        --primary: #2f81f7;
        --primary-strong: #58a6ff;
        --nav-bg: #161b22;
        --nav-text: #7d8590;
        --nav-hover-bg: rgba(47, 129, 247, 0.1);
        --nav-active: #2f81f7;
        --nav-height: 64px;
        --nav-radius: 12px;
        --nav-shadow: 0 -6px 24px rgba(0, 0, 0, 0.6);
        --transition-fast: 150ms;
        --border-nav: #30363d;
    }

    /* Bottom Navigation */
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: var(--nav-bg);
        display: flex;
        justify-content: space-around;
        align-items: center;
        height: var(--nav-height);
        padding: 8px 0;
        border-top: 1px solid var(--border-nav);
        box-shadow: var(--nav-shadow);
        z-index: 9999;
        backdrop-filter: blur(10px);
    }

    .nav-item {
        color: var(--nav-text);
        text-decoration: none;
        text-align: center;
        font-size: 0.75rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        transition: all var(--transition-fast) ease;
        padding: 6px 8px;
        border-radius: var(--nav-radius);
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    .nav-item:hover {
        color: var(--primary);
        background: var(--nav-hover-bg);
        transform: translateY(-2px);
    }

    .nav-item.active {
        color: var(--nav-active);
        background: var(--nav-hover-bg);
    }

    .nav-item i {
        font-size: 1.2rem;
        margin-bottom: 2px;
    }

    .nav-item .label {
        font-size: 0.7rem;
        line-height: 1;
        font-weight: 500;
    }

    /* Ensure content not hidden behind nav */
    body {
        padding-bottom: calc(var(--nav-height) + 12px) !important;
    }

    /* Responsive */
    @media (max-width: 480px) {
        .nav-item .label { 
            font-size: 0.6rem; 
        }
        .nav-item i { 
            font-size: 1rem; 
        }
        .bottom-nav { 
            height: 56px; 
        }
    }

    @media (min-width: 769px) {
        .bottom-nav {
            display: flex !important;
        }
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {
        .nav-item {
            transition: none;
        }
        .nav-item:hover {
            transform: none;
        }
    }
</style>

<div class="bottom-nav">
    <a href="<?php echo $techBase; ?>dashboard.php" class="nav-item <?php echo $is_home ? 'active' : ''; ?>" <?php echo $is_home ? 'aria-current="page"' : ''; ?> title="Dashboard">
        <i class="fas fa-home" aria-hidden="true"></i>
        <span class="label">Beranda</span>
    </a>
    
    <a href="<?php echo $techBase; ?>tasks/index.php" class="nav-item <?php echo $is_tasks ? 'active' : ''; ?>" <?php echo $is_tasks ? 'aria-current="page"' : ''; ?> title="Daftar Tugas">
        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
        <span class="label">Tugas</span>
    </a>
    
    <a href="<?php echo $techBase; ?>devices/search.php" class="nav-item <?php echo $is_devices ? 'active' : ''; ?>" <?php echo $is_devices ? 'aria-current="page"' : ''; ?> title="Cek Perangkat">
        <i class="fas fa-microchip" aria-hidden="true"></i>
        <span class="label">Perangkat</span>
    </a>
    
    <a href="<?php echo $techBase; ?>customers/check.php" class="nav-item <?php echo $is_customers ? 'active' : ''; ?>" <?php echo $is_customers ? 'aria-current="page"' : ''; ?> title="Cek Pelanggan">
        <i class="fas fa-users" aria-hidden="true"></i>
        <span class="label">Pelanggan</span>
    </a>
    
    <a href="<?php echo $techBase; ?>map/index.php" class="nav-item <?php echo $is_map ? 'active' : ''; ?>" <?php echo $is_map ? 'aria-current="page"' : ''; ?> title="Peta Lokasi">
        <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
        <span class="label">Peta</span>
    </a>
    
    <a href="<?php echo $techBase; ?>profile.php" class="nav-item <?php echo $is_profile ? 'active' : ''; ?>" <?php echo $is_profile ? 'aria-current="page"' : ''; ?> title="Profil Saya">
        <i class="fas fa-user-circle" aria-hidden="true"></i>
        <span class="label">Profil</span>
    </a>
</div>