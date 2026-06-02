<?php

$t1 = microtime(true);
require_once 'admin/customers.php';
echo "Customers page load: " . round((microtime(true)-$t1)*1000, 2) . "ms<br>";

echo "Total: " . round((microtime(true)-$start)*1000, 2) . "ms";