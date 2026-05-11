# ANS RADIUS - Comprehensive Codebase Analysis

**Analysis Date**: May 11, 2026  
**Scope**: Auto-isolation system, RADIUS integration, payment/invoice system, PPPoE management, session timeout logic

---

## Executive Summary

ANS RADIUS is a PHP-based ISP customer management system with dual-database architecture:
- **Main Database** (`ans_radius`): Customers, invoices, packages, billing
- **RADIUS Database** (`radius_db`): RADIUS authentication (radcheck, radusergroup, radreply, radgroupreply)

**Key Finding**: The system has a sophisticated auto-isolation framework, but **session timeout implementation is incomplete** - the timeout calculation and RADIUS integration logic are defined but never called.

---

## 1. AUTO-ISOLATION SYSTEM ARCHITECTURE

### 1.1 How Isolation Triggers

**Cron Job**: `auto_isolir` (Daily at 00:00 UTC)
- **Location**: [cron/scheduler.php](cron/scheduler.php#L165-L190)
- **Frequency**: Daily, automatically runs when cron scheduler is invoked
- **Trigger Condition**: Customers with unpaid invoices past due date

```php
// From cron/scheduler.php - runAutoIsolir()
$overdueInvoices = fetchAll("
    SELECT c.id, c.name, c.phone, c.pppoe_username, c.package_id, i.invoice_number, i.amount, i.due_date
    FROM customers c
    INNER JOIN invoices i ON c.id = i.customer_id
    WHERE i.status = 'unpaid'
    AND i.due_date < CURDATE()               // KEY: Past due date
    AND c.status = 'active'
    AND c.auto_isolate = 1                   // Must have auto_isolate enabled
    AND i.due_date = (
        SELECT MIN(i2.due_date)              // Only first overdue invoice
        FROM invoices i2
        WHERE i2.customer_id = c.id
        AND i2.status = 'unpaid'
        AND i2.due_date < CURDATE()
    )
");

foreach ($overdueInvoices as $invoice) {
    isolateCustomer($invoice['id'], ['send_whatsapp' => false]);
    sendWhatsApp($invoice['phone'], notification_message);
}
```

### 1.2 Isolation Workflow

**Function**: `isolateCustomer($customerId, $options = [])`  
**Location**: [includes/functions.php](includes/functions.php#L480-L515)

**Process Steps**:
1. **Fetch Customer Data**
   ```php
   $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
   ```

2. **Update Database Status**
   ```php
   update('customers', ['status' => 'isolated'], 'id = ?', [$customerId]);
   ```

3. **Change MikroTik Profile**
   ```php
   if ($package && !empty($customer['pppoe_username']) && !empty($package['profile_isolir'])) {
       mikrotikSetProfile($customer['pppoe_username'], $package['profile_isolir'], $customer['router_id']);
   }
   ```
   - Changes from `profile_normal` to `profile_isolir`
   - Typically limits bandwidth or blocks service

4. **Send Notification** (unless suppressed)
   ```php
   if ($sendWhatsapp) {
       $message = "Halo {$customer['name']},\n\nPembayaran internet Anda sudah melewati tanggal jatuh tempo...";
       sendWhatsApp($customer['phone'], $message);
   }
   ```

5. **Log Activity**
   ```php
   logActivity('ISOLATE_CUSTOMER', "Customer ID: {$customerId}");
   ```

### 1.3 Re-Activation (Unisolation)

**Function**: `unisolateCustomer($customerId, $options = [])`  
**Location**: [includes/functions.php](includes/functions.php#L521-L555)

**Trigger Points**:
- Payment webhook succeeds (Tripay, Midtrans) → `webhooks/tripay.php`, `webhooks/midtrans.php`
- Manual admin payment → `admin/pay_process.php`
- WhatsApp bot payment confirmation → `webhooks/whatsapp.php`
- Telegram bot payment → `webhooks/telegram.php`
- Admin manual unisolate → `admin/customers.php`

**Process**:
```php
// Step 1: Update status
update('customers', ['status' => 'active'], 'id = ?', [$customerId]);

// Step 2: Restore MikroTik profile
mikrotikSetProfile($customer['pppoe_username'], $package['profile_normal'], $customer['router_id']);

// Step 3: Optional WhatsApp notification
if ($sendWhatsapp) {
    sendWhatsApp($customer['phone'], "Layanan internet Anda sudah aktif kembali...");
}

// Step 4: Log activity
logActivity('UNISOLATE_CUSTOMER', "Customer ID: {$customerId}");
```

### 1.4 Control Flags

| Field | Type | Default | Purpose |
|-------|------|---------|---------|
| `customers.status` | ENUM('active', 'isolated') | 'active' | Current service status |
| `customers.auto_isolate` | TINYINT(1) | 1 | Enable/disable auto-isolation |
| `customers.isolation_date` | INT(1-28) | 20 | Day of month for due dates |

---

## 2. CUSTOMER TABLE SCHEMA & ISOLATION_DATE USAGE

### 2.1 Complete Schema

```sql
CREATE TABLE customers (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  phone varchar(20) DEFAULT NULL,
  pppoe_username varchar(50) NOT NULL UNIQUE,           -- PPPoE login
  package_id int(11) DEFAULT NULL,
  router_id int(11) DEFAULT '0',
  status enum('active','isolated') DEFAULT 'active',    -- Isolation flag
  auto_isolate tinyint(1) NOT NULL DEFAULT '1',         -- Enable auto-isolation
  isolation_date int(11) DEFAULT '20',                  -- Day of month (1-28)
  address text,
  lat decimal(11,8) DEFAULT NULL,
  lng decimal(11,8) DEFAULT NULL,
  portal_password varchar(255) DEFAULT NULL,
  installed_by int(11) DEFAULT NULL,
  installation_date datetime DEFAULT NULL,
  installation_photo varchar(255) DEFAULT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY pppoe_username (pppoe_username),
  KEY package_id (package_id),
  KEY installed_by (installed_by)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
```

### 2.2 isolation_date Field - Dual Purpose

The `isolation_date` field stores **day of month** (1-28, default 20) and is used for:

#### Purpose 1: Invoice Due Date Calculation
**Function**: `getCustomerDueDate($customer, $baseDate = null)`  
**Location**: [includes/functions.php](includes/functions.php#L214-L242)

```php
function getCustomerDueDate($customer, $baseDate = null)
{
    $baseTimestamp = $baseDate ? strtotime($baseDate) : time();
    $year = date('Y', $baseTimestamp);
    $month = date('m', $baseTimestamp);
    $day = isset($customer['isolation_date']) ? (int) $customer['isolation_date'] : 20;
    
    // Clamp day to valid range
    if ($day < 1) $day = 1;
    if ($day > 28) $day = 28;
    
    // Handle invalid dates (e.g., Feb 31)
    $lastDay = (int) date('t', strtotime($year . '-' . $month . '-01'));
    if ($day > $lastDay) {
        $day = $lastDay;
    }
    
    return date('Y-m-d', strtotime($year . '-' . $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
}
```

**Example Calculations**:
- Customer with `isolation_date: 20` on May 11, 2026
  - Due date for May: May 20, 2026
  - Due date for June: June 20, 2026

#### Purpose 2: RADIUS Session Timeout Calculation (⚠️ NOT INTEGRATED)
**Function**: `calculateSessionTimeoutSeconds($isolationDateInput, DateTime $referenceTime = null)`  
**Location**: [includes/radius.php](includes/radius.php#L757-L832)

```php
function calculateSessionTimeoutSeconds($isolationDateInput, DateTime $referenceTime = null)
{
    // Extract day from isolation_date (e.g., "2026-05-03" → day 3)
    $dayOfMonth = (int) $isolationDate->format('d');
    
    // Create target date: same day at 23:59:59
    $targetDateStr = $now->format('Y-m-') . str_pad($dayOfMonth, 2, '0', STR_PAD_LEFT) . ' 23:59:59';
    $targetDate = new DateTime($targetDateStr);
    
    // If target date has passed, use next month
    if ($targetDate <= $now) {
        $targetDate->add(new DateInterval('P1M'));
    }
    
    // Calculate seconds until target date
    $interval = $now->diff($targetDate);
    $totalSeconds = ($interval->days * 86400) 
                  + ($interval->h * 3600) 
                  + ($interval->i * 60) 
                  + $interval->s;
    
    return max(0, (int) $totalSeconds);
}
```

**Example Timeout Calculations**:
- Today: May 11, 2026, isolation_date: 03 → Timeout: June 3, 2026 23:59:59 = 1,641,600 seconds
- Today: May 3, 2026, isolation_date: 03 → Timeout: June 3, 2026 23:59:59 = 2,592,000 seconds
- Today: April 5, 2026, isolation_date: 31 → Timeout: May 31, 2026 23:59:59 = 4,147,200 seconds

---

## 3. PAYMENT/INVOICE SYSTEM & ISOLATION RELATIONSHIP

### 3.1 Invoice System

**Database Schema**:
```sql
CREATE TABLE invoices (
  id int(11) NOT NULL AUTO_INCREMENT,
  invoice_number varchar(50) NOT NULL UNIQUE,
  customer_id int(11) NOT NULL,
  amount decimal(10,2) NOT NULL,
  status enum('unpaid','paid','cancelled') DEFAULT 'unpaid',
  due_date date NOT NULL,
  paid_at datetime DEFAULT NULL,
  payment_method varchar(50) DEFAULT NULL,
  payment_ref varchar(100) DEFAULT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY invoice_number (invoice_number),
  KEY customer_id (customer_id),
  FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
```

### 3.2 Invoice Generation

**Cron Job**: `auto_invoice` (Monthly, 1st of month at 00:00)  
**Location**: [cron/scheduler.php](cron/scheduler.php#L207-L250)

```php
function runAutoInvoice($pdo)
{
    // Only run on the 1st of the month
    if (date('j') != '1') {
        return;
    }
    
    $currentMonth = date('Y-m');
    $generatedCount = 0;
    
    // Get all active customers
    $customers = fetchAll("SELECT * FROM customers WHERE status = 'active'");
    
    foreach ($customers as $customer) {
        // Check if invoice already exists for this month
        $existingInvoice = fetchOne("
            SELECT id FROM invoices 
            WHERE customer_id = ? 
            AND DATE_FORMAT(created_at, '%Y-%m') = ?",
            [$customer['id'], $currentMonth]
        );
        
        if (!$existingInvoice) {
            $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
            
            if ($package) {
                // Due date = isolation_date day of current month
                $dueDate = getCustomerDueDate($customer, $currentMonth . '-01');
                
                $invoiceData = [
                    'invoice_number' => generateInvoiceNumber(),
                    'customer_id' => $customer['id'],
                    'amount' => $package['price'],
                    'status' => 'unpaid',
                    'due_date' => $dueDate,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                insert('invoices', $invoiceData);
                $generatedCount++;
            }
        }
    }
}
```

### 3.3 Payment Methods & Isolation

**Payment Gateways**:
1. **Tripay** → `webhooks/tripay.php`
2. **Midtrans** → `webhooks/midtrans.php`
3. **Manual Admin** → `admin/pay_process.php`
4. **WhatsApp Bot** → `webhooks/whatsapp.php`
5. **Telegram Bot** → `webhooks/telegram.php`

**Payment Webhook Flow** (Example: Tripay):
```php
function handlePaidInvoice($invoiceNumber, $paymentData) {
    // 1. Update invoice status
    update('invoices', [
        'status' => 'paid',
        'paid_at' => date('Y-m-d H:i:s'),
        'payment_method' => $paymentData['payment_method'] ?? 'Tripay',
        'payment_ref' => $paymentData['reference'] ?? ''
    ], 'invoice_number = ?', [$invoiceNumber]);
    
    // 2. Fetch customer
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$invoice['customer_id']]);
    
    // 3. Check if customer should be unisolated
    if ($customer['status'] === 'isolated') {
        // Count remaining unpaid overdue invoices
        $unpaidCount = fetchOne("
            SELECT COUNT(*) as total 
            FROM invoices 
            WHERE customer_id = ? 
            AND status = 'unpaid' 
            AND due_date < CURDATE()
        ", [$customer['id']])['total'] ?? 0;
        
        // If no more overdue unpaid invoices, unisolate
        if ($unpaidCount === 0) {
            unisolateCustomer($invoice['customer_id']);
        }
    }
}
```

### 3.4 Packages Table

```sql
CREATE TABLE packages (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  product_type varchar(50) NOT NULL DEFAULT 'general',
  price decimal(10,2) NOT NULL,
  profile_normal varchar(50) NOT NULL,      -- Active service profile
  profile_isolir varchar(50) NOT NULL,      -- Isolation profile
  description text,
  package_services text,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
```

**Key Fields**:
- `profile_normal`: MikroTik profile for active customers
- `profile_isolir`: MikroTik profile applied when isolated (lower speed/bandwidth)

---

## 4. PPPOE USER MANAGEMENT FLOW

### 4.1 Dual Integration Architecture

ANS RADIUS supports TWO methods for PPPoE management:

#### Method 1: Direct MikroTik API
```php
// Direct MikroTik connection
function mikrotikSetProfile($username, $profile, $routerId = null)
{
    $socket = getMikrotikConnection($routerId);
    
    // Find user in /ppp/secret
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    
    // Get user ID
    $parsed = mikrotikParseUsers($allWords);
    $secretId = $parsed[0]['.id'];
    
    // Update profile
    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $secretId);
    mikrotikWrite($socket, '=profile=' . $profile);
    
    return true;
}
```

#### Method 2: RADIUS Provisioning
```php
// Query RADIUS database instead of MikroTik
function mikrotikGetPppoeUsers()
{
    if (radiusUserProvisioningReady()) {
        // Use RADIUS instead of MikroTik API
        $users = radiusGetUsersByService('Framed-User');
        return array_values(array_filter($users, function ($user) {
            return !radiusLooksLikeHotspotUser($user);
        }));
    }
    
    // Fall back to MikroTik API if RADIUS not ready
    return mikrotikGetPppoeUsersFromMikrotik();
}
```

### 4.2 User Creation Flow

**Location**: [admin/customers.php](admin/customers.php#L17-L75)

```php
// Create new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $data = [
        'name' => sanitize($_POST['name']),
        'phone' => sanitize($_POST['phone']),
        'pppoe_username' => sanitize($_POST['pppoe_username']),
        'pppoe_password' => isset($_POST['pppoe_password']) ? trim($_POST['pppoe_password']) : '',
        'package_id' => (int)$_POST['package_id'],
        'router_id' => (int)$_POST['router_id'],
        'isolation_date' => (int)$_POST['isolation_date'],  // Day of month
        'auto_isolate' => isset($_POST['auto_isolate']) ? 1 : 0,
        'status' => 'active',
        'address' => sanitize($_POST['address'] ?? ''),
    ];
    
    $customerId = insert('customers', $data);
    
    // TODO: No RADIUS provisioning here!
    // Should call: radiusSetUser($username, $password, $profile);
}
```

### 4.3 Profile Management

**Admin UI**: [admin/pppoe-profile.php](admin/pppoe-profile.php)

```php
// Get available profiles
$profilesRadius = function_exists('pppoeGetProfiles') ? pppoeGetProfiles() : mikrotikGetProfiles();

// pppoeGetProfiles() = RADIUS profiles if provisioning ready
// mikrotikGetProfiles() = MikroTik profiles from router
```

---

## 5. RADIUS INTEGRATION & SESSION TIMEOUT

### 5.1 RADIUS Database Structure

**Separate Database**: `radius_db` (defined in config.php)

```php
define('RADIUS_DB_HOST', 'localhost');
define('RADIUS_DB_NAME', 'radius_db');
define('RADIUS_DB_USER', 'root');
define('RADIUS_DB_PASS', '');
```

**Key Tables**:
```sql
-- User credentials
radcheck (
    id, username, attribute, op, value
    -- attribute: 'Cleartext-Password', 'User-Password', 'Auth-Type', 'Service-Type'
)

-- User-to-profile mapping
radusergroup (
    username, groupname, priority
    -- Assigns user to a profile/group
)

-- User-specific RADIUS attributes (reply attributes)
radreply (
    id, username, attribute, op, value
    -- attribute: 'Session-Timeout', 'Idle-Timeout', 'Rate-Limit', etc.
)

-- Profile-specific RADIUS attributes
radgroupreply (
    id, groupname, attribute, op, value
    -- Attributes applied to all users in group
)
```

### 5.2 Session Timeout Implementation

**Status**: ⚠️ **DEFINED BUT NOT INTEGRATED**

**Timeout Calculation Logic**:

```php
function radiusSetSessionTimeoutFromIsolationDate($pppoeUsername)
{
    // 1. Fetch customer isolation_date from MAIN database
    $mainDb = getMainDbConnection();
    $customer = $mainDb->prepare("SELECT isolation_date FROM customers WHERE pppoe_username = ? LIMIT 1")
                       ->execute([$pppoeUsername])
                       ->fetch();
    
    // 2. Calculate timeout in seconds until next isolation_date
    $timeoutSeconds = calculateSessionTimeoutSeconds($customer['isolation_date']);
    
    // 3. Connect to RADIUS database
    $radiusPdo = radiusDbConnection();
    
    // 4. Delete old Session-Timeout if exists
    $radiusPdo->prepare("DELETE FROM radreply WHERE username = ? AND attribute = 'Session-Timeout'")
              ->execute([$pppoeUsername]);
    
    // 5. Insert new Session-Timeout
    $radiusPdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)")
              ->execute([$pppoeUsername, (int) $timeoutSeconds]);
    
    return true;
}
```

**Timeout Examples**:
| Today | isolation_date | Target Date | Timeout (seconds) | Duration |
|-------|----------------|-------------|-------------------|----------|
| 2026-05-11 | 03 | 2026-06-03 23:59:59 | 1,641,600 | ~19 days |
| 2026-05-03 | 03 | 2026-06-03 23:59:59 | 2,592,000 | ~30 days |
| 2026-04-05 | 31 | 2026-05-31 23:59:59 | 4,147,200 | ~48 days |

### 5.3 Missing Integration Points

#### ❌ NOT CALLED IN:
1. **Customer Creation** - When PPPoE user added, no timeout set
2. **Customer Update** - When isolation_date changed, timeout not recalculated
3. **Payment Processing** - When unisolated, timeout not reset
4. **Isolation Process** - When customer isolated, no RADIUS update

#### ❌ BATCH UPDATE MISSING:
```php
// Function exists but is NEVER called
function radiusUpdateAllSessionTimeoutsFromIsolationDates($limit = 0)
{
    // Batch update all PPPoE users' Session-Timeout
    // Should be called: daily via cron, after bulk imports, etc.
}
```

### 5.4 RADIUS User Provisioning Check

```php
function radiusUserProvisioningReady()
{
    $pdo = radiusDbConnection();
    
    // Check if RADIUS tables exist
    $hasRadcheck = $pdo->query("SHOW TABLES LIKE 'radcheck'")->rowCount() > 0;
    $hasRadusergroup = $pdo->query("SHOW TABLES LIKE 'radusergroup'")->rowCount() > 0;
    
    return $hasRadcheck && $hasRadusergroup;
}
```

This check is used throughout to decide: Use RADIUS provisioning OR fall back to MikroTik API.

---

## 6. CRON SCHEDULES & AUTOMATION

### 6.1 Configured Cron Jobs

```sql
INSERT INTO cron_schedules VALUES 
(1, 'Auto Invoice', 'auto_invoice', '00:00:00', 'monthly', 1, NULL, NULL, NULL, ...),
(2, 'Auto Isolir', 'auto_isolir', '00:00:00', 'daily', 1, NULL, NULL, NULL, ...),
(3, 'Payment Reminder', 'send_reminders', '08:00:00', 'daily', 1, NULL, NULL, NULL, ...);
```

### 6.2 Execution Model

**Main Runner**: [cron/run.php](cron/run.php) or [cron/scheduler.php](cron/scheduler.php)

```php
// Must be run via CLI or web runner
if (php_sapi_name() === 'cli') {
    runScheduler();
}

// Scheduler checks: SELECT * FROM cron_schedules WHERE is_active = 1 AND next_run <= NOW()
// Updates: last_run, last_status, next_run
// Logs to: cron_logs table
```

### 6.3 Execution Flow

```php
foreach ($schedules as $schedule) {
    switch ($schedule['task_type']) {
        case 'auto_invoice':
            runAutoInvoice();  // Monthly on 1st
            break;
        case 'auto_isolir':
            runAutoIsolir();   // Daily at 00:00
            break;
        case 'send_reminders':
            sendReminders();   // Daily at 08:00
            break;
    }
    
    // Update cron_schedules table with execution status
}
```

---

## 7. CURRENT GAPS & MISSING IMPLEMENTATIONS

### 🔴 CRITICAL ISSUES

#### Issue 1: Session Timeout Logic Not Called
**Severity**: High - RADIUS integration incomplete

The entire session timeout calculation system is implemented but **never executed**:
- Function `radiusSetSessionTimeoutFromIsolationDate()` defined but orphaned
- Should be called when:
  - ✅ Invoice created/modified
  - ✅ Payment received
  - ✅ Customer status changed
  - ✅ Isolation date modified

**Impact**: RADIUS users get infinite Session-Timeout or none at all

#### Issue 2: No RADIUS Provisioning for New PPPoE Users
**Severity**: Medium - Affects RADIUS-only deployments

When customer with PPPoE created:
- ❌ Username/password NOT added to `radcheck` table
- ❌ User-to-profile NOT added to `radusergroup` table
- No integration with `radiusSetUser()` function

#### Issue 3: No Batch Session Timeout Updates
**Severity**: Medium - Manual sync required

Batch update function exists but never called:
- `radiusUpdateAllSessionTimeoutsFromIsolationDates()` orphaned
- No cron job to sync timeouts
- Useful for: bulk imports, migrations, fixes

#### Issue 4: Incomplete RADIUS-Only Deployments
**Severity**: Medium - Feature not fully baked

System supports RADIUS-only or MikroTik-only, but:
- Hotspot integration stronger in RADIUS
- PPPoE integration stronger in MikroTik
- Mixed deployments partially supported

### 🟡 DESIGN CONSIDERATIONS

1. **Dual Database Architecture**
   - Requires separate RADIUS database setup
   - Two different PDO connections needed
   - Migration/sync challenges

2. **isolation_date vs. Due Date**
   - Day of month (1-28) limits invoice dates
   - Can't do mid-month billing
   - Months with <28 days handled with last-day fallback

3. **Profile Management Complexity**
   - `profile_normal` vs. `profile_isolir` per package
   - Must exist in MikroTik or RADIUS system
   - No validation that profiles exist

4. **Auto-Isolation Limitations**
   - Only checks CURRENT overdue invoices
   - Doesn't re-isolate for multiple missed months
   - No escalation (suspend → disconnect after X days)

---

## 8. KEY FILES SUMMARY

### Core System Files

| File | Purpose | Key Functions |
|------|---------|---------------|
| [includes/config.php](includes/config.php) | Configuration | Database credentials, API keys |
| [includes/db.php](includes/db.php) | Database wrapper | `getDB()`, `query()`, `fetchOne()`, `insert()` |
| [includes/functions.php](includes/functions.php) | Shared functions | `isolateCustomer()`, `unisolateCustomer()`, `getCustomerDueDate()` |
| [includes/radius.php](includes/radius.php) | RADIUS integration | `radiusSetSessionTimeoutFromIsolationDate()`, `radiusSetUser()`, `radiusUserProvisioningReady()` |
| [includes/mikrotik_api.php](includes/mikrotik_api.php) | MikroTik API | `mikrotikSetProfile()`, `getMikrotikConnection()` |

### Isolation & Billing

| File | Purpose | Key Logic |
|------|---------|-----------|
| [cron/scheduler.php](cron/scheduler.php) | Cron runner | `runAutoIsolir()`, `runAutoInvoice()`, `sendReminders()` |
| [admin/customers.php](admin/customers.php) | Customer CRUD | Create/edit customers, manage isolation_date |
| [admin/invoices.php](admin/invoices.php) | Invoice management | Manual payment, status change, unisolation |
| [admin/pay_process.php](admin/pay_process.php) | Payment processing | Manual payment entry, invoice creation |

### Webhooks & APIs

| File | Purpose | Integration |
|------|---------|-------------|
| [webhooks/tripay.php](webhooks/tripay.php) | Payment notification | Auto-unisolate on paid |
| [webhooks/midtrans.php](webhooks/midtrans.php) | Payment notification | Auto-unisolate on paid |
| [webhooks/whatsapp.php](webhooks/whatsapp.php) | WhatsApp bot | Manual isolate/unisolate commands |
| [webhooks/telegram.php](webhooks/telegram.php) | Telegram bot | Manual isolate/unisolate commands |
| [api/payment.php](api/payment.php) | Payment gateway API | Create payment links |

---

## 9. RECOMMENDATIONS

### Priority 1: Integrate Session Timeout

```php
// Add to radiusSetSessionTimeoutFromIsolationDate() calls in:

// 1. When customer created with PPPoE
function radiusSetUser($username, $password, $profile, ...) {
    // ... existing code ...
    radiusSetSessionTimeoutFromIsolationDate($username);  // ADD THIS
}

// 2. When payment received
function handlePaidInvoice($invoiceNumber, $paymentData) {
    // ... existing code ...
    if ($customer['status'] === 'isolated' && $unpaidCount === 0) {
        unisolateCustomer($invoice['customer_id']);
        radiusSetSessionTimeoutFromIsolationDate($customer['pppoe_username']);  // ADD THIS
    }
}

// 3. When isolation_date changed
function updateCustomer(...) {
    // ... existing code ...
    if (old_isolation_date !== new_isolation_date) {
        radiusSetSessionTimeoutFromIsolationDate($customer['pppoe_username']);  // ADD THIS
    }
}

// 4. Add daily cron job
// INSERT INTO cron_schedules 
// VALUES (4, 'Sync RADIUS Timeouts', 'sync_radius_timeouts', '03:00:00', 'daily', 1, ...);
// Then in scheduler.php:
case 'sync_radius_timeouts':
    radiusUpdateAllSessionTimeoutsFromIsolationDates();
    break;
```

### Priority 2: Add RADIUS Provisioning for PPPoE

```php
// When creating customer with PPPoE:
if (!empty($data['pppoe_username']) && radiusUserProvisioningReady()) {
    $password = $data['pppoe_password'] ?? generateRandomString();
    $profile = $package['name'] ?? 'default';  // or use specific profile
    
    radiusSetUser(
        $data['pppoe_username'],
        $password,
        $profile,
        'Framed-User'  // PPPoE service type
    );
    
    radiusSetSessionTimeoutFromIsolationDate($data['pppoe_username']);
}
```

### Priority 3: Add Batch Sync Utility

```php
// Create new admin page for RADIUS management
// admin/radius-sync.php
// - Manual trigger: radiusUpdateAllSessionTimeoutsFromIsolationDates()
// - Show sync status, errors
// - Validate RADIUS database connectivity
```

---

## 10. TESTING CHECKLIST

- [ ] Auto-isolation triggers correctly when invoice past due
- [ ] Unisolation triggers when payment received via webhook
- [ ] MikroTik profile changes to `profile_isolir` on isolation
- [ ] MikroTik profile changes to `profile_normal` on unisolation
- [ ] Invoice due_date calculated from isolation_date correctly
- [ ] WhatsApp notifications sent during isolation/unisolation
- [ ] RADIUS Session-Timeout set correctly (if using RADIUS)
- [ ] Session timeout recalculates monthly
- [ ] Batch operations don't trigger multiple notifications
- [ ] Manual admin isolation/unisolation works
- [ ] Multiple invoices handled correctly (only first due isolation)
- [ ] Customers with auto_isolate=0 skip auto isolation
- [ ] MikroTik connection failures don't block invoice processing

---

## CONCLUSION

ANS RADIUS is a mature ISP billing system with comprehensive isolation and payment logic. However, **RADIUS session timeout integration is incomplete** - the calculation logic is sophisticated but disconnected from the main system flows. The system works well for MikroTik-only deployments, but RADIUS-only or hybrid deployments need additional integration work for session timeouts and user provisioning.

