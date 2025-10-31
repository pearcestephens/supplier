<?php
/**
 * Refactor All Pages - Conventional Architecture Migration
 * 
 * This script refactors all 6 page files to use the new conventional structure:
 * - html-head.php component for HTML headers
 * - html-footer.php component for closing tags
 * - Content directly in page files (no tab includes)
 * 
 * Run: php refactor-pages.php
 */

declare(strict_types=1);

$pages = [
    'orders' => [
        'file' => __DIR__ . '/orders.php',
        'tab' => __DIR__ . '/tabs/tab-orders.php',
        'title' => 'Purchase Orders',
        'js' => 'orders.js'
    ],
    'warranty' => [
        'file' => __DIR__ . '/warranty.php',
        'tab' => __DIR__ . '/tabs/tab-warranty.php',
        'title' => 'Warranty Claims',
        'js' => 'warranty.js'
    ],
    'reports' => [
        'file' => __DIR__ . '/reports.php',
        'tab' => __DIR__ . '/tabs/tab-reports.php',
        'title' => 'Reports',
        'js' => 'reports.js'
    ],
    'downloads' => [
        'file' => __DIR__ . '/downloads.php',
        'tab' => __DIR__ . '/tabs/tab-downloads.php',
        'title' => 'Downloads',
        'js' => 'downloads.js'
    ],
    'account' => [
        'file' => __DIR__ . '/account.php',
        'tab' => __DIR__ . '/tabs/tab-account.php',
        'title' => 'Account',
        'js' => 'account.js'
    ]
];

foreach ($pages as $pageName => $config) {
    echo "\n========================================\n";
    echo "Refactoring: {$pageName}.php\n";
    echo "========================================\n";
    
    if (!file_exists($config['tab'])) {
        echo "ERROR: Tab file not found: {$config['tab']}\n";
        continue;
    }
    
    // Read tab file content (skip header comments and TAB_FILE_INCLUDED check)
    $tabContent = file_get_contents($config['tab']);
    
    // Extract content after TAB_FILE_INCLUDED check
    $pattern = '/\<\?php[^?]*\?\>\s*/s';
    $tabContent = preg_replace($pattern, '', $tabContent, 1);
    
    // Remove any Chart.js includes (already in html-footer)
    $tabContent = preg_replace('/<script[^>]*chart\.js[^>]*><\/script>\s*/i', '', $tabContent);
    
    // Clean up whitespace
    $tabContent = trim($tabContent);
    
    // Create new page structure
    $newContent = <<<NEWPAGE
<?php
/**
 * {$config['title']} Page
 * Conventional architecture with reusable HTML components
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

\$supplierID = Auth::getSupplierId();
\$supplierName = Auth::getSupplierName();

\$activeTab = '{$pageName}';
\$pageTitle = '{$config['title']}';
?>
<?php include __DIR__ . '/components/html-head.php'; ?>
<body>
<div class="page">

    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <div class="page-wrapper">

        <?php include __DIR__ . '/components/header-top.php'; ?>
        <?php include __DIR__ . '/components/header-bottom.php'; ?>

        {$tabContent}

    </div><!-- /.page-wrapper -->

</div><!-- /.page -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- {$config['title']} JavaScript -->
<script src="/supplier/assets/js/{$config['js']}?v=<?php echo time(); ?>"></script>

</body>
</html>

NEWPAGE;
    
    // Backup original file
    $backupFile = $config['file'] . '.backup-refactor-' . date('Ymd-His');
    copy($config['file'], $backupFile);
    echo "✓ Backed up to: $backupFile\n";
    
    // Write new content
    file_put_contents($config['file'], $newContent);
    echo "✓ Refactored: {$config['file']}\n";
    
    // Verify file
    $lines = count(file($config['file']));
    echo "✓ New file: $lines lines\n";
}

echo "\n========================================\n";
echo "✓ All 5 pages refactored successfully!\n";
echo "========================================\n";
echo "\nNext steps:\n";
echo "1. Test all pages load correctly\n";
echo "2. Verify JavaScript works\n";
echo "3. Delete tabs/ folder\n";
echo "4. Update validation scripts\n";

