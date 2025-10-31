<?php
/**
 * Complete PDO Conversion - All Remaining Files
 * 
 * This script completes the PDO conversion for all remaining files
 * Run this after Phase A to finish Phase B
 * 
 * @version 1.0.0
 */

declare(strict_types=1);

echo "==========================================\n";
echo "   Final PDO Conversion\n";
echo "==========================================\n\n";

$baseDir = dirname(__DIR__);
$converted = 0;
$errors = [];

// Files to convert
$apiFiles = [
    'api/request-info.php',
    'api/update-po-status.php',
    'api/update-tracking.php',
    'api/update-warranty-claim.php',
    'api/warranty-action.php',
    'api/export-orders.php',
    'api/download-order.php',
    'api/download-media.php',
];

foreach ($apiFiles as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    echo "Converting: $file... ";
    
    $content = file_get_contents($fullPath);
    $backup = $content;
    
    // Step 1: Change helper function calls
    $content = preg_replace('/\$db = db\(\);/', '$pdo = pdo();', $content);
    $content = preg_replace('/\$conn = db\(\);/', '$pdo = pdo();', $content);
    
    // Step 2: Change variable names in method calls
    $content = preg_replace('/\$db->prepare\(/', '$pdo->prepare(', $content);
    $content = preg_replace('/\$conn->prepare\(/', '$pdo->prepare(', $content);
    $content = preg_replace('/\$db->query\(/', '$pdo->query(', $content);
    
    // Step 3: Remove bind_param and convert to execute with array
    // Pattern: $stmt->bind_param('types', $var1, $var2); $stmt->execute();
    // Match multi-line patterns
    $content = preg_replace_callback(
        '/\$stmt->bind_param\([\'"]([^\'"]*)[\'"]\s*,\s*([^)]+)\);\s*\$stmt->execute\(\);/s',
        function($matches) {
            $params = $matches[2];
            // Split parameters and trim
            $paramList = array_map('trim', explode(',', $params));
            $paramArray = '[' . implode(', ', $paramList) . ']';
            return "\$stmt->execute($paramArray);";
        },
        $content
    );
    
    // Step 4: Convert get_result()->fetch_assoc()
    $content = preg_replace(
        '/\$stmt->get_result\(\)->fetch_assoc\(\)/',
        '$stmt->fetch(PDO::FETCH_ASSOC)',
        $content
    );
    
    // Step 5: Convert get_result()->fetch_all(MYSQLI_ASSOC)
    $content = preg_replace(
        '/\$stmt->get_result\(\)->fetch_all\(MYSQLI_ASSOC\)/',
        '$stmt->fetchAll(PDO::FETCH_ASSOC)',
        $content
    );
    
    // Step 6: Remove MYSQLI_ASSOC constants
    $content = str_replace('MYSQLI_ASSOC', 'PDO::FETCH_ASSOC', $content);
    
    // Step 7: Remove $stmt->close()
    $content = preg_replace('/\s*\$stmt->close\(\);/', '', $content);
    $content = preg_replace('/\s*\$logStmt->close\(\);/', '', $content);
    
    // Step 8: Convert Database:: static calls to DatabasePDO::
    $content = str_replace('Database::queryOne(', 'DatabasePDO::fetchOne(', $content);
    $content = str_replace('Database::queryAll(', 'DatabasePDO::fetchAll(', $content);
    $content = str_replace('Database::execute(', 'DatabasePDO::execute(', $content);
    
    // Step 9: Fix any remaining db()->prepare that was changed to pdo()->prepare but variable still $db
    // This handles cases where variable name doesn't match
    $content = preg_replace('/\$db = pdo\(\);/', '$pdo = pdo();', $content);
    
    if ($content !== $backup) {
        if (file_put_contents($fullPath, $content)) {
            echo "✅\n";
            $converted++;
        } else {
            echo "❌ Write failed\n";
            $errors[] = $file;
        }
    } else {
        echo "⚠️  No changes\n";
    }
}

// Now convert tab files
echo "\n Converting tab files...\n";

$tabFiles = [
    'tabs/tab-orders.php',
    'tabs/tab-warranty.php',
    'tabs/tab-reports.php',
    'tabs/tab-dashboard.php',
    'tabs/tab-downloads.php',
    'tabs/tab-account.php',
];

foreach ($tabFiles as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    echo "Converting: $file... ";
    
    $content = file_get_contents($fullPath);
    $backup = $content;
    
    // Tabs use $GLOBALS['db'] - need to change to $GLOBALS['pdo']
    $content = str_replace('$GLOBALS[\'db\']', '$GLOBALS[\'pdo\']', $content);
    $content = str_replace('$db = $GLOBALS[\'pdo\'];', '$pdo = $GLOBALS[\'pdo\'];', $content);
    
    // Fix variable name if needed
    $content = preg_replace('/\$db = \$GLOBALS/', '$pdo = $GLOBALS', $content);
    
    // Change all $db-> to $pdo->
    $content = preg_replace('/\$db->/', '$pdo->', $content);
    
    // Convert MySQLi methods
    $content = preg_replace_callback(
        '/\$stmt->bind_param\([\'"]([^\'"]*)[\'"]\s*,\s*([^)]+)\);\s*\$stmt->execute\(\);/s',
        function($matches) {
            $params = $matches[2];
            $paramList = array_map('trim', explode(',', $params));
            $paramArray = '[' . implode(', ', $paramList) . ']';
            return "\$stmt->execute($paramArray);";
        },
        $content
    );
    
    $content = preg_replace(
        '/\$stmt->get_result\(\)->fetch_all\(MYSQLI_ASSOC\)/',
        '$stmt->fetchAll(PDO::FETCH_ASSOC)',
        $content
    );
    
    $content = preg_replace(
        '/\$stmt->get_result\(\)->fetch_assoc\(\)/',
        '$stmt->fetch(PDO::FETCH_ASSOC)',
        $content
    );
    
    $content = str_replace('MYSQLI_ASSOC', 'PDO::FETCH_ASSOC', $content);
    $content = preg_replace('/\s*\$stmt->close\(\);/', '', $content);
    
    if ($content !== $backup) {
        if (file_put_contents($fullPath, $content)) {
            echo "✅\n";
            $converted++;
        } else {
            echo "❌ Write failed\n";
            $errors[] = $file;
        }
    } else {
        echo "⚠️  No changes\n";
    }
}

// Update index.php
echo "\nConverting: index.php... ";
$indexPath = $baseDir . '/index.php';
if (file_exists($indexPath)) {
    $content = file_get_contents($indexPath);
    $backup = $content;
    
    $content = preg_replace('/\$db = db\(\);/', '$pdo = pdo();', $content);
    $content = preg_replace('/\$db->/', '$pdo->', $content);
    $content = str_replace('$GLOBALS[\'db\']', '$GLOBALS[\'pdo\']', $content);
    
    if ($content !== $backup) {
        if (file_put_contents($indexPath, $content)) {
            echo "✅\n";
            $converted++;
        } else {
            echo "❌\n";
            $errors[] = 'index.php';
        }
    } else {
        echo "⚠️  No changes\n";
    }
}

echo "\n==========================================\n";
echo "   Conversion Complete\n";
echo "==========================================\n";
echo "Converted: $converted files\n";
if (!empty($errors)) {
    echo "Errors: " . count($errors) . " files\n";
    foreach ($errors as $err) {
        echo "  ❌ $err\n";
    }
} else {
    echo "✅ All files converted successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Run: php tests/quick-pdo-test.php\n";
    echo "2. Test login and navigation\n";
    echo "3. Check logs for any errors\n";
}
