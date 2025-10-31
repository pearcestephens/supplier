#!/usr/bin/env php
<?php
/**
 * Batch Convert API Files from MySQLi to PDO
 * 
 * This script converts all remaining API files from db() (MySQLi)
 * to pdo() (PDO) for consistency and performance
 * 
 * @version 1.0.0
 */

declare(strict_types=1);

$files = [
    'api/add-warranty-note.php',
    'api/request-info.php',
    'api/update-tracking.php',
    'api/update-po-status.php',
    'api/update-warranty-claim.php',
    'api/warranty-action.php',
    'api/export-orders.php',
    'api/download-order.php',
    'api/download-media.php',
];

$baseDir = __DIR__ . '/..';
$converted = 0;
$failed = 0;

foreach ($files as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "❌ File not found: $file\n";
        $failed++;
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $original = $content;
    
    // Conversion patterns
    $conversions = [
        // Change helper function
        '/\$db = db\(\);/' => '$pdo = pdo();',
        '/\$conn = db\(\);/' => '$pdo = pdo();',
        
        // Change variable name in usage
        '/\$db->prepare\(/' => '$pdo->prepare(',
        '/\$conn->prepare\(/' => '$pdo->prepare(',
        
        // Convert bind_param + execute + get_result pattern
        '/\$stmt->bind_param\([^)]+\);[\s\n]*\$stmt->execute\(\);[\s\n]*(\$\w+)\s*=\s*\$stmt->get_result\(\)->fetch_assoc\(\);[\s\n]*\$stmt->close\(\);/' 
            => '$stmt->execute($params); $1 = $stmt->fetch(PDO::FETCH_ASSOC);',
        
        // Convert simple execute + get_result
        '/\$stmt->execute\(\);[\s\n]*(\$\w+)\s*=\s*\$stmt->get_result\(\)->fetch_all\(MYSQLI_ASSOC\);[\s\n]*\$stmt->close\(\);/'
            => '$stmt->execute(); $1 = $stmt->fetchAll(PDO::FETCH_ASSOC);',
        
        '/\$stmt->execute\(\);[\s\n]*(\$\w+)\s*=\s*\$stmt->get_result\(\)->fetch_assoc\(\);[\s\n]*\$stmt->close\(\);/'
            => '$stmt->execute(); $1 = $stmt->fetch(PDO::FETCH_ASSOC);',
        
        // Remove standalone close
        '/\$stmt->close\(\);/' => '',
        
        // Convert MYSQLI_ASSOC to PDO::FETCH_ASSOC
        '/MYSQLI_ASSOC/' => 'PDO::FETCH_ASSOC',
    ];
    
    foreach ($conversions as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    if ($content === $original) {
        echo "⚠️  No changes needed: $file\n";
    } else {
        if (file_put_contents($fullPath, $content)) {
            echo "✅ Converted: $file\n";
            $converted++;
        } else {
            echo "❌ Failed to write: $file\n";
            $failed++;
        }
    }
}

echo "\n";
echo "==========================================\n";
echo "Conversion Summary:\n";
echo "✅ Converted: $converted files\n";
echo "❌ Failed: $failed files\n";
echo "==========================================\n";
