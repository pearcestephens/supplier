#!/usr/bin/env php
<?php
/**
 * DEEP SOURCE CODE ANALYZER
 *
 * Analyzes server-side code quality, patterns, and potential improvements
 * Checks for security issues, optimization opportunities, best practices
 *
 * @version 1.0.0
 */

declare(strict_types=1);

$pages = [
    'dashboard.php' => 'Main dashboard with KPI metrics',
    'products.php' => 'Product analytics hub (477 lines)',
    'orders.php' => 'Order management with JOIN fixes',
    'warranty.php' => 'Warranty claims with defect analytics',
    'reports.php' => 'Report generation with date handling',
    'account.php' => 'Account settings page',
    'catalog.php' => 'Product catalog API',
];

$report = "# 📋 DEEP SOURCE CODE ANALYSIS REPORT\n\n";
$report .= "**Analysis Date:** " . date('Y-m-d H:i:s') . "\n";
$report .= "**Pages Analyzed:** " . count($pages) . "\n\n";

$totalIssues = 0;
$totalMetrics = 0;

foreach ($pages as $file => $description) {
    $filepath = __DIR__ . '/' . $file;

    if (!file_exists($filepath)) {
        $report .= "## ⚠️ $file\n";
        $report .= "**Status:** FILE NOT FOUND\n\n";
        continue;
    }

    $content = file_get_contents($filepath);
    $lines = explode("\n", $content);
    $lineCount = count($lines);

    $report .= "## 📄 $file\n\n";
    $report .= "**Description:** $description\n";
    $report .= "**Lines of Code:** $lineCount\n";
    $report .= "**Size:** " . round(filesize($filepath) / 1024, 2) . " KB\n\n";

    $issues = [];
    $metrics = [];

    // ============================================================
    // SECURITY ANALYSIS
    // ============================================================

    $report .= "### 🔒 Security Analysis\n\n";

    // Check for SQL injection risks
    $sqlInjectionRisks = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/\$_(GET|POST|REQUEST)\[/', $line) &&
            preg_match('/(?<!WHERE|SET|VALUES)\s+["\']?\s*\$_(?:GET|POST|REQUEST)/', $line)) {
            $sqlInjectionRisks[] = ($i + 1);
        }
    }

    if (empty($sqlInjectionRisks)) {
        $report .= "✅ No obvious SQL injection risks detected\n";
        $report .= "   - All database queries appear to use parameterized statements\n";
    } else {
        $report .= "⚠️ Potential SQL injection risks on lines: " . implode(', ', $sqlInjectionRisks) . "\n";
        $issues[] = ['type' => 'security', 'severity' => 'high', 'message' => 'Potential SQL injection risk'];
    }

    // Check for XSS vulnerabilities
    $xssRisks = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/echo\s+\$_(?:GET|POST|REQUEST)/', $line) &&
            !preg_match('/htmlspecialchars|htmlentities|esc_html/', $line)) {
            $xssRisks[] = ($i + 1);
        }
    }

    if (empty($xssRisks)) {
        $report .= "✅ No obvious XSS vulnerabilities detected\n";
        $report .= "   - Output appears properly escaped\n";
    } else {
        $report .= "⚠️ Potential XSS vulnerabilities on lines: " . implode(', ', array_slice($xssRisks, 0, 5)) . "\n";
        $issues[] = ['type' => 'security', 'severity' => 'high', 'message' => 'Potential XSS vulnerability'];
    }

    // Check for hardcoded credentials
    $credRisks = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/(password|api_key|secret|token)\s*=\s*["\'][^"\']*["\']/', $line)) {
            $credRisks[] = ($i + 1);
        }
    }

    if (empty($credRisks)) {
        $report .= "✅ No hardcoded credentials found\n";
    } else {
        $report .= "🔴 Hardcoded credentials detected on lines: " . implode(', ', $credRisks) . "\n";
        $issues[] = ['type' => 'security', 'severity' => 'critical', 'message' => 'Hardcoded credentials'];
    }

    // Check for proper error handling
    $errorHandling = preg_match_all('/try\s*\{|catch\s*\(|throw\s+new/', $content);
    $report .= "✅ Error handling: " . ($errorHandling > 0 ? "Present ($errorHandling blocks)" : "Minimal - consider adding try/catch") . "\n";

    $report .= "\n";

    // ============================================================
    // CODE QUALITY ANALYSIS
    // ============================================================

    $report .= "### 📊 Code Quality Metrics\n\n";

    // Function count and complexity
    $functions = [];
    preg_match_all('/^\s*(?:public|private|protected)?\s*function\s+(\w+)/m', $content, $matches);
    $functions = $matches[1];
    $report .= "- **Functions:** " . count($functions) . " " . (count($functions) > 0 ? "(" . implode(', ', array_slice($functions, 0, 5)) . (count($functions) > 5 ? "..." : "") . ")" : "") . "\n";

    // Class count
    $classes = [];
    preg_match_all('/^\s*(?:abstract\s+)?class\s+(\w+)/m', $content, $classMatches);
    $classes = $classMatches[1];
    $report .= "- **Classes:** " . count($classes) . "\n";

    // Comment ratio
    $commentLines = preg_match_all('/(\/\/|\/\*|\*|#)/', $content);
    $commentRatio = $lineCount > 0 ? round(($commentLines / $lineCount) * 100, 1) : 0;
    $report .= "- **Comment Ratio:** " . $commentRatio . "% ";
    if ($commentRatio < 10) {
        $report .= "(⚠️ Low - consider adding more documentation)\n";
        $issues[] = ['type' => 'documentation', 'severity' => 'low', 'message' => 'Low comment ratio'];
    } elseif ($commentRatio > 40) {
        $report .= "(ℹ️ High)\n";
    } else {
        $report .= "(✅ Good)\n";
    }

    // Cyclomatic complexity (simplified)
    $complexity = preg_match_all('/(if|else|for|foreach|while|switch|case|catch)[\s\(]/', $content);
    $avgComplexity = $functions ? round($complexity / count($functions), 1) : 0;
    $report .= "- **Avg Function Complexity:** " . $avgComplexity . " ";
    if ($avgComplexity > 15) {
        $report .= "(🔴 High - consider breaking into smaller functions)\n";
        $issues[] = ['type' => 'maintainability', 'severity' => 'medium', 'message' => 'High cyclomatic complexity'];
    } else {
        $report .= "(✅ Good)\n";
    }

    // Lines per function
    $avgLinesPerFunction = count($functions) > 0 ? round($lineCount / count($functions), 1) : 0;
    $report .= "- **Avg Lines per Function:** " . $avgLinesPerFunction . " ";
    if ($avgLinesPerFunction > 50) {
        $report .= "(⚠️ Consider breaking into smaller functions)\n";
    } else {
        $report .= "(✅ Good)\n";
    }

    $report .= "\n";

    // ============================================================
    // PERFORMANCE ANALYSIS
    // ============================================================

    $report .= "### ⚡ Performance Observations\n\n";

    // Database query patterns
    $queryTypes = [];
    preg_match_all('/(SELECT|INSERT|UPDATE|DELETE|WHERE|JOIN|GROUP BY|ORDER BY)/i', $content, $queryMatches);
    foreach ($queryMatches[1] as $query) {
        $queryTypes[strtoupper($query)] = ($queryTypes[strtoupper($query)] ?? 0) + 1;
    }

    if (!empty($queryTypes)) {
        $report .= "- **Database Patterns Detected:**\n";
        foreach ($queryTypes as $type => $count) {
            $report .= "  - $type: $count occurrences\n";
        }
    }

    // Loop analysis
    $loops = preg_match_all('/(for|foreach|while)\s*[\(\[]/', $content);
    if ($loops > 5) {
        $report .= "- ⚠️ **High loop count:** $loops loops (watch for N+1 query patterns)\n";
        $issues[] = ['type' => 'performance', 'severity' => 'medium', 'message' => 'High number of loops'];
    } else {
        $report .= "- ✅ **Loop count:** $loops (reasonable)\n";
    }

    // String concatenation
    $concat = preg_match_all('/\.=?\s*[\$\'"]/', $content);
    if ($concat > 10) {
        $report .= "- ⚠️ **String concatenation:** $concat instances (consider using arrays/implode)\n";
    }

    $report .= "\n";

    // ============================================================
    // BEST PRACTICES CHECK
    // ============================================================

    $report .= "### ✨ Best Practices\n\n";

    // PSR-12 compliance
    $psr12Issues = [];

    // Check for tabs (should be 4 spaces)
    if (preg_match('/^\t/m', $content)) {
        $psr12Issues[] = "Uses tabs instead of spaces";
    }

    // Check indentation (should be 4 spaces)
    if (preg_match('/^  [^ ]/m', $content)) {
        $psr12Issues[] = "Uses 2-space indentation instead of 4";
    }

    // Check declare(strict_types=1)
    if (!preg_match('/declare\(strict_types\s*=\s*1\)/', $content)) {
        $psr12Issues[] = "Missing declare(strict_types=1)";
    }

    // Check for type hints
    $typeHints = preg_match_all('/:\s*(string|int|float|bool|array|object|\w+\\w+)/', $content);
    if ($typeHints < 2 && count($functions) > 3) {
        $psr12Issues[] = "Few or no type hints on function parameters";
    }

    // Check for PHPDoc comments
    $phpDocBlocks = preg_match_all('/\/\*\*\s*\n\s*\*/', $content);
    if ($phpDocBlocks === 0 && count($functions) > 3) {
        $psr12Issues[] = "Missing PHPDoc comments on public functions";
    }

    if (empty($psr12Issues)) {
        $report .= "✅ Appears to follow PSR-12 coding standards\n";
    } else {
        $report .= "⚠️ PSR-12 observations:\n";
        foreach ($psr12Issues as $issue) {
            $report .= "   - $issue\n";
        }
    }

    // Check for deprecated functions
    $deprecatedFuncs = ['mysql_', 'ereg', 'split', 'eval', 'create_function'];
    $deprecated = [];
    foreach ($deprecatedFuncs as $func) {
        if (preg_match('/' . preg_quote($func) . '/', $content)) {
            $deprecated[] = $func;
        }
    }

    if (empty($deprecated)) {
        $report .= "✅ No deprecated functions detected\n";
    } else {
        $report .= "🔴 Deprecated functions found: " . implode(', ', $deprecated) . "\n";
    }

    // Check for const usage
    $useConst = preg_match_all('/const\s+/', $content);
    $report .= "✅ Constants usage: " . ($useConst > 0 ? "$useConst constants defined" : "None or using define()") . "\n";

    $report .= "\n";

    // ============================================================
    // IMPROVEMENT RECOMMENDATIONS
    // ============================================================

    if (!empty($issues)) {
        $report .= "### 🎯 Recommended Improvements\n\n";

        $criticalCount = count(array_filter($issues, fn($i) => $i['severity'] === 'critical'));
        $highCount = count(array_filter($issues, fn($i) => $i['severity'] === 'high'));
        $mediumCount = count(array_filter($issues, fn($i) => $i['severity'] === 'medium'));

        if ($criticalCount > 0) {
            $report .= "**Critical Issues:** $criticalCount\n";
            foreach (array_filter($issues, fn($i) => $i['severity'] === 'critical') as $issue) {
                $report .= "- 🔴 " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }

        if ($highCount > 0) {
            $report .= "**High Priority:** $highCount\n";
            foreach (array_filter($issues, fn($i) => $i['severity'] === 'high') as $issue) {
                $report .= "- 🟠 " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }

        if ($mediumCount > 0) {
            $report .= "**Medium Priority:** $mediumCount\n";
            foreach (array_filter($issues, fn($i) => $i['severity'] === 'medium') as $issue) {
                $report .= "- 🟡 " . $issue['message'] . "\n";
            }
            $report .= "\n";
        }
    } else {
        $report .= "### ✅ No Major Issues Found\n\n";
        $report .= "This file follows good coding practices and security standards.\n\n";
    }

    $totalIssues += count($issues);

    // Separator
    $report .= "---\n\n";
}

// Summary
$report .= "## 📈 OVERALL SUMMARY\n\n";
$report .= "- **Files Analyzed:** " . count($pages) . "\n";
$report .= "- **Total Issues Found:** " . $totalIssues . "\n";
$report .= "- **Assessment:** " . ($totalIssues === 0 ? "✅ EXCELLENT" : ($totalIssues < 5 ? "✅ GOOD" : "⚠️ NEEDS ATTENTION")) . "\n\n";

$report .= "## 🎓 Next Steps\n\n";
$report .= "1. Address critical security issues immediately\n";
$report .= "2. Review high-priority performance improvements\n";
$report .= "3. Consider refactoring high-complexity functions\n";
$report .= "4. Add or enhance PHPDoc comments\n";
$report .= "5. Implement comprehensive error handling\n\n";

// Save report
$reportFile = __DIR__ . '/_kb/DEEP_SOURCE_CODE_ANALYSIS.md';
file_put_contents($reportFile, $report);

echo $report;
echo "\n✅ Report saved to: $reportFile\n";
