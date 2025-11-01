<?php
/**
 * Report Generator
 * 
 * Handles export of reports to multiple formats:
 * - CSV (simple comma-separated)
 * - Excel (with formatting) - requires PHPSpreadsheet
 * - PDF (with charts) - requires TCPDF
 * 
 * @package SupplierPortal\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

class ReportGenerator
{
    /**
     * Generate CSV export
     * 
     * @param array $data Data rows to export
     * @param array $headers Column headers
     * @param string $filename Output filename
     * @return void
     */
    public static function exportCSV(array $data, array $headers, string $filename = 'report.csv'): void
    {
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps Excel recognize UTF-8)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    /**
     * Generate Excel export (basic - without external library)
     * 
     * @param array $data Data rows to export
     * @param array $headers Column headers
     * @param string $filename Output filename
     * @return void
     */
    public static function exportExcel(array $data, array $headers, string $filename = 'report.xlsx'): void
    {
        // For now, export as Excel-compatible CSV (XLS format)
        // A full Excel implementation would require PHPSpreadsheet
        
        $filename = str_replace('.xlsx', '.xls', $filename);
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Start Excel XML format
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Report">' . "\n";
        echo '<Table>' . "\n";
        
        // Headers
        echo '<Row>' . "\n";
        foreach ($headers as $header) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
        
        // Data rows
        foreach ($data as $row) {
            echo '<Row>' . "\n";
            foreach ($row as $cell) {
                $type = is_numeric($cell) ? 'Number' : 'String';
                echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string)$cell) . '</Data></Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
    }
    
    /**
     * Generate PDF export (basic HTML to PDF)
     * 
     * @param string $html HTML content to convert
     * @param string $filename Output filename
     * @param array $options Additional options
     * @return void
     */
    public static function exportPDF(string $html, string $filename = 'report.pdf', array $options = []): void
    {
        // Basic PDF generation using HTML2PDF approach
        // A full implementation would use TCPDF or similar
        
        $title = $options['title'] ?? 'Report';
        $orientation = $options['orientation'] ?? 'portrait';
        
        // For now, export as HTML (can be printed to PDF by browser)
        // A full PDF implementation would require TCPDF library
        
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>';
        echo '<html><head>';
        echo '<meta charset="UTF-8">';
        echo '<title>' . htmlspecialchars($title) . '</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo 'table { border-collapse: collapse; width: 100%; margin: 20px 0; }';
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        echo 'th { background-color: #f2f2f2; font-weight: bold; }';
        echo 'h1 { color: #333; }';
        echo '@media print { .no-print { display: none; } }';
        echo '</style>';
        echo '</head><body>';
        echo '<div class="no-print" style="margin-bottom:20px;">';
        echo '<button onclick="window.print()">Print to PDF</button> ';
        echo '<button onclick="window.close()">Close</button>';
        echo '</div>';
        echo $html;
        echo '</body></html>';
    }
    
    /**
     * Format data for export
     * 
     * @param array $rawData Raw data from database
     * @param array $columns Column definitions [key => label]
     * @param array $formatters Optional formatters [key => callable]
     * @return array Formatted data ready for export
     */
    public static function formatData(array $rawData, array $columns, array $formatters = []): array
    {
        $formatted = [];
        
        foreach ($rawData as $row) {
            $formattedRow = [];
            
            foreach ($columns as $key => $label) {
                $value = $row[$key] ?? '';
                
                // Apply formatter if exists
                if (isset($formatters[$key]) && is_callable($formatters[$key])) {
                    $value = $formatters[$key]($value, $row);
                }
                
                $formattedRow[] = $value;
            }
            
            $formatted[] = $formattedRow;
        }
        
        return $formatted;
    }
    
    /**
     * Common formatters for different data types
     */
    public static function currencyFormatter(): callable
    {
        return function($value) {
            return '$' . number_format((float)$value, 2);
        };
    }
    
    public static function numberFormatter(int $decimals = 0): callable
    {
        return function($value) use ($decimals) {
            return number_format((float)$value, $decimals);
        };
    }
    
    public static function dateFormatter(string $format = 'Y-m-d'): callable
    {
        return function($value) use ($format) {
            return $value ? date($format, strtotime($value)) : '';
        };
    }
    
    public static function percentFormatter(int $decimals = 1): callable
    {
        return function($value) use ($decimals) {
            return number_format((float)$value, $decimals) . '%';
        };
    }
    
    /**
     * Generate summary statistics HTML for PDF reports
     * 
     * @param array $stats Statistics to display
     * @return string HTML
     */
    public static function generateSummaryHTML(array $stats): string
    {
        $html = '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
        $html .= '<h3>Summary Statistics</h3>';
        $html .= '<table style="width: 100%;">';
        
        foreach ($stats as $label => $value) {
            $html .= '<tr>';
            $html .= '<td style="font-weight: bold; width: 50%;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate table HTML for PDF reports
     * 
     * @param array $data Data rows
     * @param array $headers Column headers
     * @param string $title Table title
     * @return string HTML
     */
    public static function generateTableHTML(array $data, array $headers, string $title = ''): string
    {
        $html = '';
        
        if ($title) {
            $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        }
        
        $html .= '<table>';
        $html .= '<thead><tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }
}
