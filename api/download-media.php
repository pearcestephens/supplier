<?php
/**
 * Supplier Portal - Download Media API
 * 
 * Securely serves warranty claim media files
 * Supports single file download and ZIP archives
 * 
 * @package CIS\Supplier\API
 * @version 2.0.0 - Uses unified bootstrap
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
requireAuth();

$supplierID = getSupplierID();
$conn = db(); // MySQLi connection for binary operations

// Get parameters
$mediaID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$faultID = isset($_GET['fault_id']) ? (int)$_GET['fault_id'] : 0;
$downloadType = $_GET['type'] ?? 'single'; // 'single' or 'zip'

// Media upload directory (from database schema)
$uploadBaseDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/faulty_products/';

try {
    if ($downloadType === 'zip' && $faultID > 0) {
        // Download all media for a fault as ZIP
        
        // Verify supplier owns this fault
        $verifyQuery = "
            SELECT fp.id
            FROM faulty_products fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE fp.id = ? AND p.supplier_id = ?
        ";
        
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param('is', $faultID, $supplierID);
        $verifyStmt->execute();
        $fault = $verifyStmt->get_result()->fetch_assoc();
        $verifyStmt->close();
        
        if (!$fault) {
            http_response_code(404);
            die('Warranty claim not found or access denied');
        }
        
        // Get all media files for this fault
        $mediaQuery = "
            SELECT id, fileName, tempFileName
            FROM faulty_product_media_uploads
            WHERE fault_id = ?
            ORDER BY upload_time DESC
        ";
        
        $mediaStmt = $conn->prepare($mediaQuery);
        $mediaStmt->bind_param('i', $faultID);
        $mediaStmt->execute();
        $mediaFiles = $mediaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mediaStmt->close();
        
        if (empty($mediaFiles)) {
            http_response_code(404);
            die('No media files found for this warranty claim');
        }
        
        // Create ZIP archive
        $zipFilename = 'warranty_claim_' . $faultID . '_media.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            die('Failed to create ZIP archive');
        }
        
        foreach ($mediaFiles as $file) {
            $filePath = $uploadBaseDir . $file['tempFileName'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file['fileName']);
            }
        }
        
        $zip->close();
        
        if (!file_exists($zipPath)) {
            http_response_code(500);
            die('ZIP archive creation failed');
        }
        
        // Send ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($zipPath);
        unlink($zipPath); // Clean up temp file
        exit;
        
    } else {
        // Download single file
        
        if ($mediaID <= 0) {
            http_response_code(400);
            die('Invalid media ID');
        }
        
        // Get media file with supplier verification
        $mediaQuery = "
            SELECT fpm.id, fpm.fileName, fpm.tempFileName, fp.id as fault_id
            FROM faulty_product_media_uploads fpm
            LEFT JOIN faulty_products fp ON fpm.fault_id = fp.id
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE fpm.id = ? 
              AND p.supplier_id = ?
        ";
        
        $mediaStmt = $conn->prepare($mediaQuery);
        $mediaStmt->bind_param('is', $mediaID, $supplierID);
        $mediaStmt->execute();
        $media = $mediaStmt->get_result()->fetch_assoc();
        $mediaStmt->close();
        
        if (!$media) {
            http_response_code(404);
            die('Media file not found or access denied');
        }
        
        // Build file path
        $filePath = $uploadBaseDir . $media['tempFileName'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('File not found on server: ' . htmlspecialchars($media['fileName']));
        }
        
        // Determine MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Send file
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($media['fileName']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($filePath);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

$conn->close();
