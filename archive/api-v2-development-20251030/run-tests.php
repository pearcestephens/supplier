<?php
/**
 * Quick Test Runner
 * Execute API validation and display results
 */

echo "🔧 Phase 1 API Testing\n";
echo "=====================\n\n";

// Change to API directory
chdir(__DIR__);

// Execute validation
ob_start();
include 'validate-api.php';
$output = ob_get_clean();

echo $output;

// Check if report was generated
if (file_exists(__DIR__ . '/test-report.md')) {
    $report_size = filesize(__DIR__ . '/test-report.md');
    echo "\n📊 Report generated successfully ({$report_size} bytes)\n";
    
    // Show first few lines of report
    $report_content = file_get_contents(__DIR__ . '/test-report.md');
    $lines = explode("\n", $report_content);
    
    echo "\n📋 Report Preview:\n";
    echo str_repeat("-", 40) . "\n";
    for ($i = 0; $i < min(15, count($lines)); $i++) {
        echo $lines[$i] . "\n";
    }
    if (count($lines) > 15) {
        echo "... (truncated, " . (count($lines) - 15) . " more lines)\n";
    }
} else {
    echo "\n❌ Report generation failed\n";
}