<?php
/**
 * Webhook Handler - Midtrans Payment Gateway
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get raw POST data
    $json = file_get_contents('php://input');
    
    logActivity('MIDTRANS_WEBHOOK', "Received webhook");
    
    // Parse JSON data
    $data = json_decode($json, true);
    
    if (!$data) {
        logError('Midtrans webhook: Invalid JSON');
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    $orderId = $data['order_id'] ?? '';
    $transactionStatus = $data['transaction_status'] ?? '';
    $paymentType = $data['payment_type'] ?? '';
    $transactionTime = $data['transaction_time'] ?? '';
    $grossAmount = $data['gross_amount'] ?? '';
    $signatureKey = $data['signature_key'] ?? '';
    $statusCode = $data['status_code'] ?? '';

    // Verify signature
    $midtransApiKey = trim((string) getSetting('MIDTRANS_API_KEY', ''));
    if ($midtransApiKey === '') {
        logError('Midtrans webhook: API Key not configured');
        echo json_encode(['success' => false, 'message' => 'Configuration error']);
        exit;
    }

    $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $midtransApiKey);
    
    if ($signatureKey !== $expectedSignature) {
        logError('Midtrans webhook: Invalid signature');
        echo json_encode(['success' => false, 'message' => 'Invalid signature']);
        exit;
    }
    
    // Log webhook
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (source, payload, status_code, response, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['midtrans', $json, 200, 'Received']);
    
    // Handle payment status
    if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
        handlePaidInvoice($orderId, $data);
    } elseif ($transactionStatus === 'expire' || $transactionStatus === 'cancel' || $transactionStatus === 'deny') {
        handleFailedInvoice($orderId, $transactionStatus);
    }
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logError("Midtrans webhook error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handlePaidInvoice($invoiceNumber, $paymentData) {
    $invoice = fetchOne("SELECT * FROM invoices WHERE invoice_number = ?", [$invoiceNumber]);

    if (!$invoice) {
        if (markPublicVoucherOrderPaid($invoiceNumber, 'midtrans', $paymentData)) {
            logActivity('PUBLIC_VOUCHER_PAID', "Order: {$invoiceNumber}");
            return;
        }

        logError("Invoice/order not found: {$invoiceNumber}");
        return;
    }

    // Get paid time
    $paidAt = date('Y-m-d H:i:s');

    // Get customer billing day
    $customer = fetchOne("
        SELECT billing_day
        FROM customers
        WHERE id = ?
    ", [$invoice['customer_id']]);

    // Calculate next isolation date
    $isolationDate = null;

    if ($customer && isset($customer['billing_day'])) {
        $isolationDate = buildIsolationDate((int) $customer['billing_day']);
    }

    // Update invoice status
    update('invoices', [
        'status' => 'paid',
        'paid_at' => $paidAt,
        'payment_method' => $paymentData['payment_type'] ?? 'Midtrans',
        'payment_ref' => $paymentData['transaction_id'] ?? ''
    ], 'invoice_number = ?', [$invoiceNumber]);

    logActivity('INVOICE_PAID', "Invoice: {$invoiceNumber}");

    sendInvoicePaidWhatsapp($invoiceNumber, 'midtrans', $paymentData);

    // Check customer
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$invoice['customer_id']]);

    if ($customer) {
        // Unisolate customer if isolated
        if($customer['status'] === 'isolated'){
            if (unisolateCustomer($invoice['customer_id'])) {
                logActivity(
                    'AUTO_UNISOLATE',
                    "Customer ID: {$invoice['customer_id']}"
                );
            }
        }
        // Update isolation date
        if ($isolationDate !== null) {
            if (updateIsolationDate($invoice['customer_id'], $isolationDate)) {
                logActivity(
                    'AUTO_UPDATE_ISOLATIONDATE',
                    "Customer ID: {$invoice['customer_id']}"
                );
            }
        }
    }
}
function handleFailedInvoice($invoiceNumber, $status) {
    if (markPublicVoucherOrderFailed($invoiceNumber, $status, ['transaction_status' => $status])) {
        logActivity('PUBLIC_VOUCHER_FAILED', "Order: {$invoiceNumber}, Status: {$status}");
        return;
    }
    logActivity('INVOICE_FAILED', "Invoice: {$invoiceNumber}, Status: {$status}");
}
