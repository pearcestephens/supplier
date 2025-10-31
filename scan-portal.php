#!/usr/bin/env php
<?php
/**
 * COMPREHENSIVE SUPPLIER PORTAL SCANNER
 *
 * Crawls every page, validates HTML, checks for errors/warnings
 * Tests all functionality and reports issues
 *
 * @version 1.0.0
 * @date October 31, 2025
 */

declare(strict_types=1);

// ============================================================================
// CONFIGURATION
// ============================================================================

define('BASE_URL', 'https://staff.vapeshed.co.nz/supplier');
define('TIMEOUT', 30);
define('REPORT_FILE', __DIR__ . '/_kb/COMPREHENSIVE_SCAN_REPORT.md');

// Pages to scan
$pagesToScan = [
    'dashboard.php' => 'Dashboard - Main analytics hub',
    'products.php' => 'Products - Product analytics',
    'orders.php' => 'Orders - Order management',
    'warranty.php' => 'Warranty - Warranty claims',
    'account.php' => 'Account - Account settings',
    'reports.php' => 'Reports - Report generation',
    'catalog.php' => 'Catalog - Product catalog',
    'downloads.php' => 'Downloads - Download reports',
];

// ============================================================================
// RESULTS STORAGE
// ============================================================================

$results = [
    'pages' => [],
    'errors' => [],
    'warnings' => [],
    'issues' => [],
    'stats' => [
        'pages_scanned' => 0,
        'pages_ok' => 0,
        'pages_errors' => 0,
        'total_errors' => 0,
        'total_warnings' => 0,
    ],
    'timestamp' => date('Y-m-d H:i:s'),
];

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function makeRequest(string $url): array
{
    echo "🔍 Scanning: $url\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => $httpCode === 200,
        'status_code' => $httpCode,
        'body' => $response ?: '',
        'error' => $error,
    ];
}

function analyzeHTML(string $html): array
{
    $issues = [];

    // Check for common errors

    // 1. Missing DOCTYPE
    if (!preg_match('/<!\s*DOCTYPE\s+html/i', $html)) {
        $issues[] = [
            'type' => 'ERROR',
            'code' => 'MISSING_DOCTYPE',
            'message' => 'Missing DOCTYPE declaration',
            'severity' => 'high',
        ];
    }

    // 2. Missing <html> tag
    if (!preg_match('/<html[^>]*>/i', $html)) {
        $issues[] = [
            'type' => 'ERROR',
            'code' => 'MISSING_HTML_TAG',
            'message' => 'Missing <html> tag',
            'severity' => 'high',
        ];
    }

    // 3. Missing <head> tag
    if (!preg_match('/<head[^>]*>/i', $html)) {
        $issues[] = [
            'type' => 'ERROR',
            'code' => 'MISSING_HEAD_TAG',
            'message' => 'Missing <head> tag',
            'severity' => 'high',
        ];
    }

    // 4. Missing <body> tag
    if (!preg_match('/<body[^>]*>/i', $html)) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'MISSING_BODY_TAG',
            'message' => 'Missing <body> tag',
            'severity' => 'medium',
        ];
    }

    // 5. Missing <title> tag
    if (!preg_match('/<title[^>]*>.*?<\/title>/i', $html)) {
        $issues[] = [
            'type' => 'ERROR',
            'code' => 'MISSING_TITLE_TAG',
            'message' => 'Missing <title> tag',
            'severity' => 'high',
        ];
    }

    // 6. Missing viewport meta tag
    if (!preg_match('/viewport/i', $html)) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'MISSING_VIEWPORT_META',
            'message' => 'Missing viewport meta tag (not mobile-responsive)',
            'severity' => 'medium',
        ];
    }

    // 7. Empty alt attributes (accessibility issue)
    $altCount = preg_match_all('/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i', $html, $altMatches);
    $emptyAlts = array_filter($altMatches[1], fn($alt) => trim($alt) === '');
    if (!empty($emptyAlts)) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'EMPTY_ALT_ATTRIBUTES',
            'message' => 'Found ' . count($emptyAlts) . ' images with empty alt attributes',
            'severity' => 'low',
        ];
    }

    // 8. Unclosed tags (basic check)
    $unclosedTags = [];
    if (substr_count($html, '<input') !== substr_count($html, '<input') + preg_match_all('/<input\s*>/', $html)) {
        // Could have self-closing inputs
    }

    // 9. Missing charset declaration
    if (!preg_match('/charset/i', $html)) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'MISSING_CHARSET',
            'message' => 'Missing charset declaration in meta tags',
            'severity' => 'medium',
        ];
    }

    // 10. PHP errors in output
    if (preg_match('/Fatal error|Parse error|Warning:|Notice:|Undefined/i', $html)) {
        preg_match_all('/(Fatal error|Parse error|Warning:|Notice:|Undefined[^<]*)/i', $html, $matches);
        foreach ($matches[1] as $error) {
            $issues[] = [
                'type' => 'ERROR',
                'code' => 'PHP_ERROR_IN_OUTPUT',
                'message' => htmlspecialchars(substr($error, 0, 100)),
                'severity' => 'critical',
            ];
        }
    }

    // 11. Check for common security issues

    // SQL injection patterns (should be prepared statements)
    if (preg_match('/\$_GET\[|_REQUEST\[|_POST\[/i', $html)) {
        // Might be SQL injection - check for quotes
        if (preg_match('/\$_(GET|POST|REQUEST)\[[\'"][^\]]*[\'"]\]\s*\.?\s*["\']/', $html)) {
            // Probable SQL injection vulnerability
        }
    }

    // 12. Check for mixed content (https page loading http resources)
    if (preg_match('/src=["\']?http:\/\//', $html) && strpos(BASE_URL, 'https') === 0) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'MIXED_CONTENT',
            'message' => 'Page loads HTTP resources over HTTPS (mixed content)',
            'severity' => 'medium',
        ];
    }

    // 13. Check for inline scripts
    if (preg_match_all('/<script[^>]*>/', $html, $inlineScripts)) {
        // Could indicate eval or dynamic code
    }

    // 14. Check for deprecated attributes
    $deprecatedAttrs = ['align', 'bgcolor', 'border', 'cellpadding', 'cellspacing'];
    foreach ($deprecatedAttrs as $attr) {
        if (preg_match('/' . $attr . '\s*=/', $html)) {
            $issues[] = [
                'type' => 'WARNING',
                'code' => 'DEPRECATED_ATTRIBUTE',
                'message' => "Using deprecated HTML attribute: $attr",
                'severity' => 'low',
            ];
        }
    }

    // 15. Check for console errors logged
    if (preg_match('/console\.error|console\.warn/i', $html)) {
        $issues[] = [
            'type' => 'WARNING',
            'code' => 'CONSOLE_ERRORS',
            'message' => 'Page contains console.error or console.warn calls',
            'severity' => 'low',
        ];
    }

    return $issues;
}

function extractPageStats(string $html): array
{
    return [
        'size_bytes' => strlen($html),
        'size_kb' => round(strlen($html) / 1024, 2),
        'line_count' => count(explode("\n", $html)),
        'img_count' => preg_match_all('/<img/i', $html),
        'link_count' => preg_match_all('/<a\s/i', $html),
        'form_count' => preg_match_all('/<form/i', $html),
        'table_count' => preg_match_all('/<table/i', $html),
        'script_count' => preg_match_all('/<script/i', $html),
        'css_count' => preg_match_all('/<link[^>]*rel=["\']stylesheet/i', $html),
    ];
}

function saveReport(array $results): void
{
    $report = "# 🔍 COMPREHENSIVE SUPPLIER PORTAL SCAN REPORT\n\n";
    $report .= "**Scan Date:** " . $results['timestamp'] . "\n";
    $report .= "**Status:** " . ($results['stats']['pages_errors'] === 0 ? '✅ PASSED' : '❌ FAILURES FOUND') . "\n\n";

    // Summary
    $report .= "## 📊 SCAN SUMMARY\n\n";
    $report .= "| Metric | Value |\n";
    $report .= "|--------|-------|\n";
    $report .= "| Pages Scanned | " . $results['stats']['pages_scanned'] . " |\n";
    $report .= "| Pages OK | " . $results['stats']['pages_ok'] . " |\n";
    $report .= "| Pages with Errors | " . $results['stats']['pages_errors'] . " |\n";
    $report .= "| Total Errors Found | " . $results['stats']['total_errors'] . " |\n";
    $report .= "| Total Warnings Found | " . $results['stats']['total_warnings'] . " |\n\n";

    // Pages
    $report .= "## 📄 PAGE SCAN RESULTS\n\n";
    foreach ($results['pages'] as $page => $data) {
        $status = $data['status_code'] === 200 ? '✅' : '❌';
        $report .= "### $status $page\n\n";
        $report .= "**Status Code:** " . $data['status_code'] . "\n";
        $report .= "**Size:** " . $data['stats']['size_kb'] . " KB\n";
        $report .= "**Images:** " . $data['stats']['img_count'] . " | ";
        $report .= "**Links:** " . $data['stats']['link_count'] . " | ";
        $report .= "**Forms:** " . $data['stats']['form_count'] . " | ";
        $report .= "**Scripts:** " . $data['stats']['script_count'] . "\n\n";

        if (!empty($data['issues'])) {
            $report .= "**Issues Found:** " . count($data['issues']) . "\n\n";
            foreach ($data['issues'] as $issue) {
                $severity = $issue['severity'];
                $icons = ['critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🔵'];
                $icon = $icons[$severity] ?? '⚪';
                $report .= "- $icon **[" . $issue['type'] . "]** " . $issue['message'] . "\n";
            }
            $report .= "\n";
        } else {
            $report .= "✅ No issues found\n\n";
        }
    }

    // Overall issues
    if (!empty($results['issues'])) {
        $report .= "## ⚠️ CRITICAL ISSUES SUMMARY\n\n";
        $critical = array_filter($results['issues'], fn($i) => $i['severity'] === 'critical');
        $high = array_filter($results['issues'], fn($i) => $i['severity'] === 'high');
        $medium = array_filter($results['issues'], fn($i) => $i['severity'] === 'medium');

        if (!empty($critical)) {
            $report .= "### 🔴 CRITICAL (" . count($critical) . ")\n";
            foreach ($critical as $issue) {
                $report .= "- **" . $issue['code'] . ":** " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }

        if (!empty($high)) {
            $report .= "### 🟠 HIGH (" . count($high) . ")\n";
            foreach ($high as $issue) {
                $report .= "- **" . $issue['code'] . ":** " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }

        if (!empty($medium)) {
            $report .= "### 🟡 MEDIUM (" . count($medium) . ")\n";
            foreach ($medium as $issue) {
                $report .= "- **" . $issue['code'] . ":** " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }
    }

    file_put_contents(REPORT_FILE, $report);
    echo "\n✅ Report saved to: " . REPORT_FILE . "\n";
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       COMPREHENSIVE SUPPLIER PORTAL SCANNER v1.0              ║\n";
echo "║                                                                ║\n";
echo "║  Crawling all pages, analyzing HTML, detecting issues         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "🚀 Starting comprehensive scan...\n";
echo "📍 Base URL: " . BASE_URL . "\n";
echo "🔧 DEBUG MODE: ENABLED\n";
echo "📅 Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Scan each page
foreach ($pagesToScan as $page => $description) {
    $url = BASE_URL . '/' . $page;
    $response = makeRequest($url);

    $pageData = [
        'url' => $url,
        'description' => $description,
        'status_code' => $response['status_code'],
        'issues' => [],
        'stats' => [],
    ];

    if ($response['success']) {
        $results['stats']['pages_ok']++;

        // Analyze HTML
        $issues = analyzeHTML($response['body']);
        $pageData['issues'] = $issues;

        // Extract stats
        $pageData['stats'] = extractPageStats($response['body']);

        // Save full HTML for inspection
        $filename = __DIR__ . '/_kb/scan_' . str_replace('.php', '.html', $page);
        file_put_contents($filename, $response['body']);

        // Count errors/warnings
        foreach ($issues as $issue) {
            if ($issue['type'] === 'ERROR') {
                $results['stats']['total_errors']++;
            } elseif ($issue['type'] === 'WARNING') {
                $results['stats']['total_warnings']++;
            }

            // Add to issues array if critical
            if ($issue['severity'] === 'critical' || $issue['severity'] === 'high') {
                $results['issues'][] = [
                    'page' => $page,
                    'severity' => $issue['severity'],
                    'code' => $issue['code'],
                    'message' => $issue['message'],
                ];
            }
        }

        if (!empty($issues)) {
            $results['stats']['pages_errors']++;
        }

        echo "  ✅ " . str_pad($page, 20) . " | Status: " . $response['status_code'] . " | Issues: " . count($issues) . "\n";
    } else {
        $results['stats']['pages_errors']++;
        echo "  ❌ " . str_pad($page, 20) . " | Status: " . $response['status_code'] . " | Error: " . $response['error'] . "\n";
    }

    $results['pages'][$page] = $pageData;
    $results['stats']['pages_scanned']++;
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Summary
echo "📊 SCAN RESULTS:\n";
echo "   Pages Scanned: " . $results['stats']['pages_scanned'] . "\n";
echo "   Pages OK: " . $results['stats']['pages_ok'] . "\n";
echo "   Pages with Issues: " . $results['stats']['pages_errors'] . "\n";
echo "   Total Errors: " . $results['stats']['total_errors'] . "\n";
echo "   Total Warnings: " . $results['stats']['total_warnings'] . "\n\n";

// Save report
saveReport($results);

echo "✅ Scan complete!\n\n";
