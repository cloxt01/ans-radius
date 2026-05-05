
# ANS Radius

Aplikasi manajemen pelanggan ISP/RT-RW Net berbasis PHP, dengan dukungan modul MikroTik, hotspot/voucher, billing, payment gateway, WhatsApp/Telegram, dan integrasi GenieACS.

## Instalasi

### Prasyarat
- PHP 7.4+
- MySQL/MariaDB 5.7+
- Web server: Apache atau Nginx
- MikroTik Router (opsional)
- GenieACS server (opsional)
- FreeRADIUS 3.x (opsional, fitur BETA)

### Langkah Cepat
1. Clone atau download source code.
```bash
git clone https://github.com/Adzka-Team/ans-radius.git
```
2. Upload ke folder web server.
- aaPanel: `www/wwwroot/nama-domain/`
- cPanel/public_html: `public_html/`
3. Jalankan installer web.
```text
http://domain-anda/install.php
```
4. Ikuti wizard instalasi.
- Server Check
- Database Setup
- Admin Account
- MikroTik Config (opsional)
- Integrations (opsional)
5. Selesai.
- Admin: `http://domain-anda/admin/login.php`
- Portal pelanggan: `http://domain-anda/portal/login.php`
- Portal sales: `http://domain-anda/sales/login.php`

## Konfigurasi

### File Konfigurasi Utama
Konfigurasi disimpan di `includes/config.php`.

Contoh nilai penting:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ans_radius');
define('DB_USER', 'root');
define('DB_PASS', '');

define('RADIUS_DB_NAME', 'radius_db');

define('MIKROTIK_HOST', '');
define('MIKROTIK_USER', '');
define('MIKROTIK_PASS', '');
define('MIKROTIK_PORT', 8728);

define('APP_NAME', 'ANS Radius');
define('APP_VERSION', '2.0.6');

define('ENCRYPTION_KEY', 'ganti-dengan-random-key-anda');
```

### WireGuard - Linux only (Opsional)

1. Install paket WireGuard di server:
```bash
sudo apt update
sudo apt install wireguard -y
```

2. Sesuaikan konfigurasi server yang ada di bagian atas file `setup/wireguard-provision.sh`
```sh
#!/bin/bash

WG_IF="" # nama interface mis (wg0)
SUBNET="10.7.0" # subnet mis (10.7.0)
DNS="8.8.8.8" # dns
ENDPOINT="x.x.x.x" # endpoint / ip server
INTERFACE_PORT= 16315 # interface port mis (16315)

## -- LANJUTAN -- ##
```

3. Salin file `setup/wireguard-provision.sh` ke folder `root/`

4. Lalu masukan perintah `sudo visudo`, lalu tambahkan teks berikut di paling bawah: 

```
bash
www ALL=(ALL) NOPASSWD: /root/wg-provision.sh
www ALL=(ALL) NOPASSWD: /usr/bin/wg
www ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart freeradius
```

### FreeRADIUS (Opsional, BETA)
```bash
sudo apt install freeradius freeradius-mysql freeradius-utils mariadb-server -y
```
Import schema MySQL FreeRADIUS dari:
```text
/etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql
```

## API Endpoints

| Endpoint | Fungsi | Method |
|---|---|---|
| `/api/dashboard.php` | Statistik dashboard | GET |
| `/api/customers.php` | Manajemen pelanggan | GET, POST, PUT, DELETE |
| `/api/invoices.php` | Operasi invoice | GET, POST, PUT, DELETE |
| `/api/mikrotik.php` | Operasi MikroTik | GET, POST |
| `/api/genieacs.php` | Operasi GenieACS | GET, POST |
| `/api/onu_locations.php` | Lokasi ONU | GET, POST |
| `/api/onu_wifi.php` | Kontrol WiFi ONU | POST |
| `/api/portal_password.php` | Reset password portal | POST |

## Cron Scheduler

Untuk fitur otomatis (isolir, reminder, billing task), jalankan scheduler berkala.

### Linux/Panel
```bash
*/5 * * * * /usr/bin/php /path/to/ans-radius/cron/scheduler.php
```

### Windows Task Scheduler
1. Buat task baru.
2. Program: `php.exe`
3. Argument: `C:\path\to\ans-radius\cron\scheduler.php`
4. Interval: setiap 5 menit.

## Catatan Keamanan
- Ganti nilai `ENCRYPTION_KEY` sebelum produksi.
- Jangan commit kredensial production ke repository.
- Batasi akses file sensitif (`includes/config.php`, backup SQL, logs).
- Gunakan HTTPS untuk panel admin dan endpoint webhook.

