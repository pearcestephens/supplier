<?php
/**
 * Asset Loader - Auto-loads CSS and JS files in sorted order
 *
 * Naming convention: NN-description.css or NN-description.js
 * where NN is a two-digit sort number (01, 02, 03, etc.)
 *
 * Files are loaded in numerical order automatically.
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

/**
 * Load all CSS files from a directory in sorted order
 *
 * @param string $directory Directory path relative to /supplier/
 * @param bool $return Whether to return HTML or echo it
 * @return string|void HTML link tags
 */
function loadCSS($directory = 'assets/css', $return = false) {
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/supplier/' . $directory;
    $baseUrl = '/supplier/' . $directory;

    if (!is_dir($basePath)) {
        return $return ? '' : null;
    }

    // Get all CSS files
    $files = glob($basePath . '/*.css');

    if (empty($files)) {
        return $return ? '' : null;
    }

    // Sort files by name (numeric prefix ensures order)
    sort($files);

    $html = "\n<!-- Auto-loaded CSS files (sorted) -->\n";

    foreach ($files as $file) {
        $filename = basename($file);
        $fileUrl = $baseUrl . '/' . $filename;
        $timestamp = filemtime($file); // Cache busting

        $html .= sprintf(
            '<link rel="stylesheet" href="%s?v=%s">%s',
            htmlspecialchars($fileUrl),
            $timestamp,
            "\n"
        );
    }

    if ($return) {
        return $html;
    }

    echo $html;
}

/**
 * Load all JS files from a directory in sorted order
 *
 * @param string $directory Directory path relative to /supplier/
 * @param bool $return Whether to return HTML or echo it
 * @param bool $defer Add defer attribute
 * @return string|void HTML script tags
 */
function loadJS($directory = 'assets/js', $return = false, $defer = false) {
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/supplier/' . $directory;
    $baseUrl = '/supplier/' . $directory;

    if (!is_dir($basePath)) {
        return $return ? '' : null;
    }

    // Get all JS files
    $files = glob($basePath . '/*.js');

    if (empty($files)) {
        return $return ? '' : null;
    }

    // Sort files by name (numeric prefix ensures order)
    sort($files);

    $html = "\n<!-- Auto-loaded JS files (sorted) -->\n";
    $deferAttr = $defer ? ' defer' : '';

    foreach ($files as $file) {
        $filename = basename($file);
        $fileUrl = $baseUrl . '/' . $filename;
        $timestamp = filemtime($file); // Cache busting

        $html .= sprintf(
            '<script src="%s?v=%s"%s></script>%s',
            htmlspecialchars($fileUrl),
            $timestamp,
            $deferAttr,
            "\n"
        );
    }

    if ($return) {
        return $html;
    }

    echo $html;
}

/**
 * Load specific CSS/JS modules
 * Useful for page-specific assets
 *
 * @param array $modules Array of module names (without extension)
 * @param string $type 'css' or 'js'
 * @param string $directory Base directory
 * @return void
 */
function loadModules($modules, $type = 'css', $directory = 'assets') {
    $baseUrl = '/supplier/' . $directory . '/' . $type;
    $basePath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl;

    foreach ($modules as $module) {
        $file = $basePath . '/' . $module . '.' . $type;

        if (file_exists($file)) {
            $timestamp = filemtime($file);
            $fileUrl = $baseUrl . '/' . $module . '.' . $type;

            if ($type === 'css') {
                echo sprintf(
                    '<link rel="stylesheet" href="%s?v=%s">' . "\n",
                    htmlspecialchars($fileUrl),
                    $timestamp
                );
            } else {
                echo sprintf(
                    '<script src="%s?v=%s"></script>' . "\n",
                    htmlspecialchars($fileUrl),
                    $timestamp
                );
            }
        }
    }
}

/**
 * Get list of loaded assets (for debugging)
 *
 * @param string $directory Directory to scan
 * @param string $type 'css' or 'js'
 * @return array List of loaded files
 */
function getLoadedAssets($directory = 'assets/css', $type = 'css') {
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/supplier/' . $directory;
    $files = glob($basePath . '/*.' . $type);

    if (empty($files)) {
        return [];
    }

    sort($files);

    return array_map('basename', $files);
}

/**
 * Asset loader index generator
 * Creates an index file showing all assets and their load order
 *
 * @return string HTML table of assets
 */
function generateAssetIndex() {
    $cssFiles = getLoadedAssets('assets/css', 'css');
    $jsFiles = getLoadedAssets('assets/js', 'js');

    $html = '<h3>CSS Files (Load Order)</h3><ol>';
    foreach ($cssFiles as $file) {
        $html .= '<li><code>' . htmlspecialchars($file) . '</code></li>';
    }
    $html .= '</ol>';

    $html .= '<h3>JS Files (Load Order)</h3><ol>';
    foreach ($jsFiles as $file) {
        $html .= '<li><code>' . htmlspecialchars($file) . '</code></li>';
    }
    $html .= '</ol>';

    return $html;
}

/**
 * Check if asset naming convention is followed
 * Returns warnings for files that don't follow NN-name.ext pattern
 *
 * @param string $directory Directory to check
 * @param string $type File type
 * @return array Array of warnings
 */
function validateAssetNaming($directory = 'assets/css', $type = 'css') {
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/supplier/' . $directory;
    $files = glob($basePath . '/*.' . $type);
    $warnings = [];

    foreach ($files as $file) {
        $filename = basename($file);

        // Check if filename starts with NN- pattern
        if (!preg_match('/^[0-9]{2}-/', $filename)) {
            $warnings[] = $filename . ' - Should start with two-digit number (e.g., 01-base.css)';
        }
    }

    return $warnings;
}
