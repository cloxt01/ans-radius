<?php
/**
 * GenieACS Device Management - Elegant Dark Minimalis Theme
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'GenieACS Monitoring';

// Get devices from GenieACS with specific projection
$devices = genieacsGetDevices();
$totalDevices = count($devices);

// Get existing locations
$locations = fetchAll("SELECT * FROM onu_locations");
$locMap = [];
foreach ($locations as $loc) {
    if (!empty($loc['serial_number'])) {
        $locMap[$loc['serial_number']] = $loc;
    }
}

// Calculate stats
$onlineCount = 0;
$offlineCount = 0;
$weakSignalCount = 0;

foreach ($devices as $device) {
    $lastInform = $device['_lastInform'] ?? null;
    if ($lastInform && (time() - strtotime($lastInform)) < 300) {
        $onlineCount++;
    } else {
        $offlineCount++;
    }

    $rxPower = $device['VirtualParameters']['RXPower']['_value'] ?? $device['VirtualParameters']['RXPower'] ?? null;
    if (is_numeric($rxPower) && $rxPower < -25 && $rxPower != 0) {
        $weakSignalCount++;
    }
}

function getVal($data, $path) {
    $parts = explode('.', $path);
    $current = $data;
    
    foreach ($parts as $part) {
        if (isset($current[$part])) {
            $current = $current[$part];
        } else {
            return null;
        }
    }
    
    if (is_array($current)) {
        return $current['_value'] ?? null;
    }
    
    return $current;
}

ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <p>Total Device</p>
        </div>
        <div class="stat-icon blue">
            <i class="fas fa-satellite-dish"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $onlineCount; ?></h3>
            <p>Online</p>
        </div>
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $offlineCount; ?></h3>
            <p>Offline</p>
        </div>
        <div class="stat-icon orange">
            <i class="fas fa-circle"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $weakSignalCount; ?></h3>
            <p>Signal Lemah</p>
        </div>
        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
    </div>
</div>

<!-- Connection Status Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-plug"></i> Status Koneksi
        </h3>
        <div class="connection-status">
            <?php if (!empty(GENIEACS_URL)): ?>
                <span class="badge badge-success">
                    <i class="fas fa-circle"></i> Connected
                </span>
                <a href="<?php echo htmlspecialchars(GENIEACS_URL); ?>" target="_blank" class="connection-link">
                    <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars(parse_url(GENIEACS_URL, PHP_URL_HOST)); ?>
                </a>
            <?php else: ?>
                <span class="badge badge-danger">
                    <i class="fas fa-circle"></i> Not Connected
                </span>
                <span class="text-muted">GenieACS belum terkonfigurasi</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Devices Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-server"></i> Monitoring ONU
        </h3>
        <div class="table-controls">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchDevice" class="form-control" placeholder="Cari ID, IP, SN...">
            </div>
            <button class="btn-icon" onclick="loadDevices()" title="Refresh Data">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" id="devicesTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Lokasi</th>
                    <th>ID (PPPoE)</th>
                    <th>SSID</th>
                    <th>Active</th>
                    <th>Hotspot</th>
                    <th>RX Power</th>
                    <th>Temp</th>
                    <th>Uptime</th>
                    <th>IP PPPoE</th>
                    <th>IP WAN</th>
                    <th>SN</th>
                    <th>Last Inform</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="14" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Tidak ada device ditemukan</p>
                            <small>atau GenieACS tidak terkoneksi</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices as $device):
                        $realDeviceId = $device['_id'] ?? '';
                        $serialNumber = $device['_deviceId']['_SerialNumber'] ?? getVal($device, 'DeviceID.SerialNumber') ?? '-';
                        if (empty($realDeviceId)) $realDeviceId = $serialNumber;
                        $lastInform = $device['_lastInform'] ?? null;
                        $isOnline = $lastInform && (time() - strtotime($lastInform)) < 300;

                        $pppoeUser2 = getVal($device, 'VirtualParameters.pppoeUsername2') ?? '-';
                        $ssid = getVal($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID') ?? '-';
                        $totalAssoc = getVal($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations') ?? '0';
                        $hotspotActive = getVal($device, 'VirtualParameters.activedevices') ?? '-';
                        $rxPower = getVal($device, 'VirtualParameters.RXPower') ?? '-';
                        $temp = getVal($device, 'VirtualParameters.gettemp') ?? '-';
                        $uptime = getVal($device, 'VirtualParameters.getdeviceuptime') ?? '-';
                        $pppoeIp = getVal($device, 'VirtualParameters.pppoeIP') ?? '-';
                        $wanIp = getVal($device, 'VirtualParameters.IPTR069') ?? '-';
                        $sn = getVal($device, 'VirtualParameters.getSerialNumber') ?? $serialNumber;

                        if (is_numeric($uptime)) {
                            $days = floor($uptime / 86400);
                            $hours = floor(($uptime % 86400) / 3600);
                            $uptime = "{$days}d {$hours}h";
                        }
                        
                        $rxVal = floatval($rxPower);
                        $rxColor = ($rxVal < -25) ? 'signal-weak' : (($rxVal < -20) ? 'signal-medium' : 'signal-good');
                        $hasLoc = isset($locMap[$serialNumber]);
                        $locName = $hasLoc ? $locMap[$serialNumber]['name'] : $pppoeUser2;
                    ?>
                    <tr>
                        <td data-label="Status">
                            <?php if ($isOnline): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-circle"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-circle"></i> Offline
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Lokasi">
                            <button class="btn-icon location-btn <?php echo $hasLoc ? 'has-location' : ''; ?>" 
                                    onclick="openMapModal('<?php echo $serialNumber; ?>', '<?php echo $hasLoc ? $locMap[$serialNumber]['lat'] : ''; ?>', '<?php echo $hasLoc ? $locMap[$serialNumber]['lng'] : ''; ?>', '<?php echo htmlspecialchars($locName); ?>')"
                                    title="<?php echo $hasLoc ? 'Lihat Lokasi' : 'Set Lokasi'; ?>">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                        </td>
                        <td data-label="ID (PPPoE)">
                            <strong class="pppoe-user"><?php echo htmlspecialchars($pppoeUser2); ?></strong>
                        </td>
                        <td data-label="SSID">
                            <div class="ssid-info">
                                <span><?php echo htmlspecialchars($ssid); ?></span>
                                <button class="btn-icon" onclick="openWifiEdit('<?php echo htmlspecialchars($realDeviceId); ?>', '<?php echo htmlspecialchars($ssid); ?>')" title="Edit WiFi">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </div>
                        </td>
                        <td data-label="Active" class="text-center"><?php echo htmlspecialchars($totalAssoc); ?></td>
                        <td data-label="Hotspot" class="text-center"><?php echo htmlspecialchars($hotspotActive); ?></td>
                        <td data-label="RX Power">
                            <span class="signal-badge <?php echo $rxColor; ?>">
                                <i class="fas fa-signal"></i> <?php echo htmlspecialchars($rxPower); ?> dBm
                            </span>
                        </td>
                        <td data-label="Temp"><?php echo htmlspecialchars($temp); ?> °C</td>
                        <td data-label="Uptime">
                            <span class="uptime-badge">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($uptime); ?>
                            </span>
                        </td>
                        <td data-label="IP PPPoE">
                            <?php if ($pppoeIp !== '-'): ?>
                                <a href="http://<?php echo htmlspecialchars($pppoeIp); ?>" target="_blank" class="ip-link pppoe">
                                    <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($pppoeIp); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="IP WAN">
                            <?php if ($wanIp !== '-'): ?>
                                <a href="http://<?php echo htmlspecialchars($wanIp); ?>" target="_blank" class="ip-link wan">
                                    <i class="fas fa-globe"></i> <?php echo htmlspecialchars($wanIp); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="SN">
                            <code class="sn-code"><?php echo htmlspecialchars($sn); ?></code>
                        </td>
                        <td data-label="Last Inform">
                            <span class="last-inform">
                                <i class="fas fa-clock"></i> <?php echo $lastInform ? date('d/m/Y H:i', strtotime($lastInform)) : '-'; ?>
                            </span>
                        </td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="rebootDevice('<?php echo htmlspecialchars($realDeviceId); ?>')" title="Reboot Device">
                                    <i class="fas fa-power-off"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- WiFi Edit Modal -->
<div id="wifiModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-wifi"></i> Edit WiFi</h3>
            <button class="close" onclick="closeWifiModal()">&times;</button>
        </div>
        <input type="hidden" id="editSerial">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">SSID</label>
                <div class="input-with-button">
                    <input type="text" id="editSsid" class="form-control" placeholder="Nama WiFi">
                    <button class="btn btn-primary" onclick="saveSsid()">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-with-button">
                    <div class="password-wrapper">
                        <input type="password" id="editPassword" class="form-control" placeholder="Password WiFi">
                        <i class="fas fa-eye" id="togglePass" onclick="togglePasswordVisibility()"></i>
                    </div>
                    <button class="btn btn-primary" onclick="savePassword()">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
                <small class="form-hint">Minimal 8 karakter</small>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeWifiModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- Map Modal -->
<div id="mapModal" class="modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fas fa-map-marked-alt"></i> Lokasi ONU</h3>
            <button class="close" onclick="closeMapModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mapSerial">
            <div class="form-group">
                <label class="form-label">Nama Lokasi</label>
                <input type="text" id="mapName" class="form-control" placeholder="Nama Pelanggan / Alamat">
            </div>
            <div id="map" class="map-container"></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input type="text" id="mapLat" class="form-control" placeholder="-6.252471" onchange="updateMarkerFromInput()">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input type="text" id="mapLng" class="form-control" placeholder="107.920660" onchange="updateMarkerFromInput()">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeMapModal()">Batal</button>
            <button class="btn btn-primary" onclick="saveLocation()">
                <i class="fas fa-save"></i> Simpan Lokasi
            </button>
        </div>
    </div>
</div>

<style>
/* Additional styles for GenieACS page */
.connection-status {
    display: flex;
    align-items: center;
    gap: 12px;
}

.connection-link {
    font-size: 12px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color var(--transition-fast);
}

.connection-link:hover {
    color: var(--accent-blue);
    text-decoration: underline;
}

.table-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-wrapper i {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    font-size: 14px;
}

.search-wrapper .form-control {
    padding-left: 36px;
    width: 250px;
}

.pppoe-user {
    color: var(--accent-blue);
    font-family: monospace;
    font-size: 13px;
}

.ssid-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ssid-info span {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.signal-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.signal-good {
    background: rgba(63, 185, 80, 0.15);
    color: var(--accent-green);
}

.signal-medium {
    background: rgba(210, 153, 34, 0.15);
    color: var(--accent-orange);
}

.signal-weak {
    background: rgba(248, 81, 73, 0.15);
    color: var(--accent-red);
}

.uptime-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-secondary);
}

.ip-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    text-decoration: none;
    font-family: monospace;
}

.ip-link.pppoe {
    color: var(--accent-blue);
}

.ip-link.wan {
    color: var(--accent-purple);
}

.ip-link:hover {
    text-decoration: underline;
}

.sn-code {
    font-family: monospace;
    font-size: 11px;
    background: var(--bg-tertiary);
    padding: 4px 6px;
    border-radius: 4px;
}

.last-inform {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--text-secondary);
}

.location-btn {
    background: transparent;
    border: 1px solid var(--border-light);
    padding: 6px 10px;
}

.location-btn.has-location {
    background: rgba(88, 166, 255, 0.15);
    border-color: var(--accent-blue);
    color: var(--accent-blue);
}

.input-with-button {
    display: flex;
    gap: 10px;
}

.input-with-button .form-control {
    flex: 1;
}

.password-wrapper {
    position: relative;
    flex: 1;
}

.password-wrapper .form-control {
    padding-right: 35px;
}

.password-wrapper i {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-muted);
    transition: color var(--transition-fast);
}

.password-wrapper i:hover {
    color: var(--text-primary);
}

.map-container {
    height: 350px;
    width: 100%;
    border-radius: var(--radius-md);
    margin-bottom: 16px;
    background: var(--bg-tertiary);
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--text-muted);
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.btn-icon {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--accent-blue);
}

.empty-state {
    text-align: center;
    padding: 60px 20px !important;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.empty-state small {
    font-size: 12px;
}

@media (max-width: 768px) {
    .table-controls {
        flex-direction: column;
        width: 100%;
    }
    
    .search-wrapper {
        width: 100%;
    }
    
    .search-wrapper .form-control {
        width: 100%;
    }
    
    .ssid-info {
        flex-wrap: wrap;
    }
    
    .input-with-button {
        flex-direction: column;
    }
    
    .connection-status {
        flex-wrap: wrap;
    }
    
    .map-container {
        height: 250px;
    }
}
</style>

<script>
let map, marker;

function initMap() {
    if (map) return;
    
    map = L.map('map').setView([-6.252471, 107.920660], 16);
    
    const googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });
    
    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    });
    
    googleSat.addTo(map);
    
    L.control.layers({ "Satelit": googleSat, "OpenStreetMap": osm }).addTo(map);
    
    map.on('click', function(e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });
}

function setMarker(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateInputs(pos.lat, pos.lng);
        });
    }
    updateInputs(lat, lng);
    map.setView([lat, lng]);
}

function updateInputs(lat, lng) {
    document.getElementById('mapLat').value = lat.toFixed(6);
    document.getElementById('mapLng').value = lng.toFixed(6);
}

function updateMarkerFromInput() {
    const lat = parseFloat(document.getElementById('mapLat').value);
    const lng = parseFloat(document.getElementById('mapLng').value);
    
    if (!isNaN(lat) && !isNaN(lng)) {
        setMarker(lat, lng);
        map.setView([lat, lng], 16);
    }
}

function openMapModal(serial, lat, lng, name) {
    document.getElementById('mapModal').style.display = 'flex';
    document.getElementById('mapSerial').value = serial;
    document.getElementById('mapName').value = name || serial;
    
    setTimeout(() => {
        if (map) {
            map.remove();
            map = null;
            marker = null;
        }
        
        initMap();
        map.invalidateSize();
        
        if (lat && lng && lat != 0 && lng != 0) {
            setMarker(parseFloat(lat), parseFloat(lng));
        } else if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                map.setView([pos.coords.latitude, pos.coords.longitude], 16);
            });
        }
    }, 200);
}

function closeMapModal() {
    document.getElementById('mapModal').style.display = 'none';
    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
}

function saveLocation() {
    const serial = document.getElementById('mapSerial').value;
    const name = document.getElementById('mapName').value;
    const lat = document.getElementById('mapLat').value;
    const lng = document.getElementById('mapLng').value;

    if (!lat || !lng) {
        alert('Silakan tentukan lokasi pada peta');
        return;
    }

    fetch('<?php echo APP_URL; ?>/api/onu_locations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial, name, lat, lng })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Lokasi berhasil disimpan');
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(error => alert('Error: ' + error));
}

function loadDevices() {
    location.reload();
}

function rebootDevice(serial) {
    if (!confirm('Reboot device ' + serial + '?\n\nProses ini akan memakan waktu sekitar 2-3 menit.')) {
        return;
    }

    fetch('<?php echo APP_URL; ?>/api/genieacs.php?action=reboot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.success ? 'Reboot berhasil dijalankan' : 'Gagal: ' + data.message);
        if (data.success) setTimeout(() => loadDevices(), 30000);
    })
    .catch(error => alert('Error: ' + error.message));
}

function openWifiEdit(serial, ssid) {
    document.getElementById('editSerial').value = serial;
    document.getElementById('editSsid').value = ssid;
    document.getElementById('editPassword').value = '';
    document.getElementById('wifiModal').style.display = 'flex';
}

function closeWifiModal() {
    document.getElementById('wifiModal').style.display = 'none';
}

function togglePasswordVisibility() {
    const input = document.getElementById('editPassword');
    const icon = document.getElementById('togglePass');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function saveSsid() {
    const serial = document.getElementById('editSerial').value;
    const ssid = document.getElementById('editSsid').value;

    if (ssid.length < 3) {
        alert('SSID minimal 3 karakter');
        return;
    }

    if (!confirm('Simpan perubahan SSID?')) return;

    fetch('<?php echo APP_URL; ?>/api/onu_wifi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial, ssid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SSID berhasil diperbarui');
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(error => alert('Error: ' + error));
}

function savePassword() {
    const serial = document.getElementById('editSerial').value;
    const password = document.getElementById('editPassword').value;

    if (password.length < 8) {
        alert('Password minimal 8 karakter');
        return;
    }

    if (!confirm('Simpan perubahan Password?')) return;

    fetch('<?php echo APP_URL; ?>/api/onu_wifi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial, password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password berhasil diperbarui');
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(error => alert('Error: ' + error));
}

// Search functionality
document.getElementById('searchDevice')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#devicesTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.empty-state')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';