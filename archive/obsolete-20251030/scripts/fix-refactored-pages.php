<?php
/**
 * Fix Refactored Pages - Remove embedded PHP headers from tab content
 */

declare(strict_types=1);

$pages = ['orders', 'warranty', 'reports', 'downloads', 'account'];

foreach ($pages as $pageName) {
    $file = __DIR__ . "/{$pageName}.php";
    $content = file_get_contents($file);
    
    // Remove embedded PHP docblocks and declare statements
    $content = preg_replace('/\<\?php\s*\/\*\*[^*]*\*+(?:[^*\/][^*]*\*+)*\/\s*declare\(strict_types=1\);\s*\n/s', '', $content);
    
    // Remove TAB_FILE_INCLUDED security check
    $content = preg_replace('/\/\/ Security:.*?exit\([^\)]+\);\s*\}\s*\n/s', '', $content);
    
    // Remove database verification
    $content = preg_replace('/\/\/ CRITICAL:.*?die\([^\)]+\);\s*\}\s*\n/s', '', $content);
    
    // Remove Auth class check
    $content = preg_replace('/if \(!class_exists\(\'Auth\'\)\).*?\}\s*\n/s', '', $content);
    
    // Remove any standalone declare(strict_types=1) that's not at the top
    $lines = explode("\n", $content);
    $fixed = [];
    $seenDeclare = false;
    
    foreach ($lines as $line) {
        // Skip duplicate declare statements after the first one
        if (preg_match('/declare\(strict_types=1\);/', $line)) {
            if ($seenDeclare) {
                continue; // Skip
            }
            $seenDeclare = true;
        }
        
        // Skip TAB_FILE_INCLUDED checks
        if (str_contains($line, 'TAB_FILE_INCLUDED') || 
            str_contains($line, 'Direct access not permitted')) {
            continue;
        }
        
        $fixed[] = $line;
    }
    
    $content = implode("\n", $fixed);
    
    // Clean up excessive whitespace
    $content = preg_replace('/\n{4,}/', "\n\n\n", $content);
    
    file_put_contents($file, $content);
    echo "✓ Fixed: $file\n";
}

echo "\n✓ All pages fixed!\n";
