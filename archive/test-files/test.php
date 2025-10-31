<?php
// Simple test file to debug paths and DB connection
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "dirname(__DIR__): " . dirname(__DIR__) . "\n";
echo "\n";

// Try app.php (should include bootstrap + establish DB)
echo "App.php path: " . dirname(__DIR__) . '/app.php' . "\n";
echo "App.php exists: " . (file_exists(dirname(__DIR__) . '/app.php') ? 'YES' : 'NO') . "\n";
echo "\n";

try {
    require_once dirname(__DIR__) . '/app.php';
    echo "App.php loaded successfully!\n";
    echo "DB variable isset: " . (isset($db) ? 'YES' : 'NO') . "\n";
    echo "DB variable type: " . (isset($db) ? gettype($db) : 'NOT SET') . "\n";
    if (isset($db) && $db instanceof mysqli) {
        echo "DB connection: Connected!\n";
        echo "DB host: " . $db->host_info . "\n";
        // Try a simple query
        $result = $db->query("SELECT COUNT(*) as count FROM suppliers");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Suppliers count: " . $row['count'] . "\n";
        }
    } else {
        echo "DB connection: Not a mysqli object\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
