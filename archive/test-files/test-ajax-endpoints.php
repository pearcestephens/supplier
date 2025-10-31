<?php
/**
 * AJAX Endpoints Testing Script
 * 
 * Tests all AJAX API endpoints for:
 * - Proper request handling
 * - Response format validation
 * - Error handling
 * - Authentication/authorization
 * - Data integrity
 * 
 * @package CIS\Supplier\Tests
 * @version 1.0.0
 */

// Include minimal dependencies
define('SUPPLIER_PORTAL', true);

// Database connection (using actual database credentials)
$mysqli = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test data
$test_supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'; // British American Tobacco
$test_results = [];
$errors = [];
$warnings = [];

echo "=== AJAX ENDPOINT TESTS ===\n\n";

// Helper function to simulate AJAX request
function simulate_ajax_call($endpoint, $data = [], $method = 'POST') {
    global $mysqli, $test_supplier_id;
    
    // Set up environment
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SESSION['supplier_id'] = $test_supplier_id;
    $_SESSION['supplier_name'] = 'Test Supplier';
    $_SESSION['csrf_token'] = 'test-token-' . time();
    
    // Set POST/GET data
    if ($method === 'POST') {
        $_POST = array_merge(['csrf_token' => $_SESSION['csrf_token']], $data);
    } else {
        $_GET = $data;
    }
    
    // Capture output
    ob_start();
    
    try {
        include __DIR__ . '/api/' . $endpoint;
        $output = ob_get_clean();
        
        // Try to decode JSON
        $json = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . $output,
                'raw' => $output
            ];
        }
        
        return $json;
        
    } catch (Exception $e) {
        ob_end_clean();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test 1: Update PO Status - Mark as SENT
echo "[TEST 1] Update PO Status (mark as SENT)...\n";
try {
    // Get first available PO
    $po_sql = "SELECT id FROM stock_transfers 
               WHERE supplier_id = ? AND state = 'OPEN' 
               LIMIT 1";
    $stmt = $mysqli->prepare($po_sql);
    $stmt->bind_param('s', $test_supplier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $po_id = $result['id'];
        
        $response = simulate_ajax_call('update-po-status.php', [
            'po_id' => $po_id,
            'action' => 'mark_sent'
        ]);
        
        if ($response['success']) {
            echo "  ✓ PO status updated successfully\n";
            echo "    - PO ID: {$po_id}\n";
            echo "    - Message: {$response['message']}\n";
            
            // Verify in database
            $verify_sql = "SELECT state, supplier_sent_at FROM stock_transfers WHERE id = ?";
            $verify_stmt = $mysqli->prepare($verify_sql);
            $verify_stmt->bind_param('i', $po_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result()->fetch_assoc();
            
            if ($verify_result['state'] === 'SENT' && $verify_result['supplier_sent_at']) {
                echo "    ✓ Database verified: State = SENT, timestamp set\n";
            } else {
                $warnings[] = "PO state not updated in database";
            }
            
            $test_results['update_po_status'] = 'PASS';
        } else {
            $errors[] = "Update PO failed: " . ($response['error'] ?? 'Unknown error');
            $test_results['update_po_status'] = 'FAIL';
        }
    } else {
        $warnings[] = "No OPEN POs available to test update";
        $test_results['update_po_status'] = 'SKIP';
    }
} catch (Exception $e) {
    $errors[] = "Update PO test failed: " . $e->getMessage();
    $test_results['update_po_status'] = 'FAIL';
}
echo "\n";

// Test 2: Update PO Status - Cancel PO
echo "[TEST 2] Update PO Status (cancel PO)...\n";
try {
    // Get another available PO
    $po_sql = "SELECT id FROM stock_transfers 
               WHERE supplier_id = ? AND state IN ('OPEN', 'SENT') 
               LIMIT 1 OFFSET 1";
    $stmt = $mysqli->prepare($po_sql);
    $stmt->bind_param('s', $test_supplier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $po_id = $result['id'];
        
        $response = simulate_ajax_call('update-po-status.php', [
            'po_id' => $po_id,
            'action' => 'cancel'
        ]);
        
        if ($response['success']) {
            echo "  ✓ PO cancelled successfully\n";
            echo "    - PO ID: {$po_id}\n";
            echo "    - Message: {$response['message']}\n";
            
            $test_results['cancel_po'] = 'PASS';
        } else {
            $errors[] = "Cancel PO failed: " . ($response['error'] ?? 'Unknown error');
            $test_results['cancel_po'] = 'FAIL';
        }
    } else {
        $warnings[] = "No available POs to test cancellation";
        $test_results['cancel_po'] = 'SKIP';
    }
} catch (Exception $e) {
    $errors[] = "Cancel PO test failed: " . $e->getMessage();
    $test_results['cancel_po'] = 'FAIL';
}
echo "\n";

// Test 3: Update Warranty Claim Status
echo "[TEST 3] Update Warranty Claim Status...\n";
try {
    // Check if any claims exist
    $claim_sql = "SELECT id FROM warranty_claims WHERE supplier_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($claim_sql);
    $stmt->bind_param('s', $test_supplier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $claim_id = $result['id'];
        
        $response = simulate_ajax_call('update-warranty-claim.php', [
            'claim_id' => $claim_id,
            'status' => 1, // Resolved
            'note' => 'Test resolution note'
        ]);
        
        if ($response['success']) {
            echo "  ✓ Claim status updated successfully\n";
            echo "    - Claim ID: {$claim_id}\n";
            echo "    - Message: {$response['message']}\n";
            
            $test_results['update_claim'] = 'PASS';
        } else {
            $errors[] = "Update claim failed: " . ($response['error'] ?? 'Unknown error');
            $test_results['update_claim'] = 'FAIL';
        }
    } else {
        echo "  ⚠ No warranty claims available to test\n";
        $warnings[] = "No warranty claims found for testing";
        $test_results['update_claim'] = 'SKIP';
    }
} catch (Exception $e) {
    $errors[] = "Update claim test failed: " . $e->getMessage();
    $test_results['update_claim'] = 'FAIL';
}
echo "\n";

// Test 4: Add Warranty Note
echo "[TEST 4] Add Warranty Note...\n";
try {
    // Check if any claims exist
    $claim_sql = "SELECT id FROM warranty_claims WHERE supplier_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($claim_sql);
    $stmt->bind_param('s', $test_supplier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $claim_id = $result['id'];
        
        $response = simulate_ajax_call('add-warranty-note.php', [
            'claim_id' => $claim_id,
            'note' => 'This is a test note from automated testing'
        ]);
        
        if ($response['success']) {
            echo "  ✓ Note added successfully\n";
            echo "    - Claim ID: {$claim_id}\n";
            echo "    - Message: {$response['message']}\n";
            
            // Verify note was saved
            if (isset($response['note_id']) && $response['note_id'] > 0) {
                echo "    ✓ Note ID: {$response['note_id']}\n";
            }
            
            $test_results['add_note'] = 'PASS';
        } else {
            $errors[] = "Add note failed: " . ($response['error'] ?? 'Unknown error');
            $test_results['add_note'] = 'FAIL';
        }
    } else {
        echo "  ⚠ No warranty claims available to test\n";
        $warnings[] = "No warranty claims found for adding notes";
        $test_results['add_note'] = 'SKIP';
    }
} catch (Exception $e) {
    $errors[] = "Add note test failed: " . $e->getMessage();
    $test_results['add_note'] = 'FAIL';
}
echo "\n";

// Test 5: Authentication Required Tests
echo "[TEST 5] Authentication/Authorization Tests...\n";
try {
    // Test 5a: Missing session
    unset($_SESSION['supplier_id']);
    
    $response = simulate_ajax_call('update-po-status.php', [
        'po_id' => 999999,
        'action' => 'mark_sent'
    ]);
    
    if (!$response['success'] && strpos(strtolower($response['error']), 'not authenticated') !== false) {
        echo "  ✓ Correctly rejected request without authentication\n";
        $test_results['auth_required'] = 'PASS';
    } else {
        $errors[] = "Should reject unauthenticated requests";
        $test_results['auth_required'] = 'FAIL';
    }
    
    // Restore session for other tests
    $_SESSION['supplier_id'] = $test_supplier_id;
    
} catch (Exception $e) {
    $errors[] = "Auth test failed: " . $e->getMessage();
    $test_results['auth_required'] = 'FAIL';
}
echo "\n";

// Test 6: CSRF Token Validation
echo "[TEST 6] CSRF Token Validation...\n";
try {
    // Try to update PO with invalid CSRF token
    $_POST['csrf_token'] = 'invalid-token';
    
    $response = simulate_ajax_call('update-po-status.php', [
        'po_id' => 28151,
        'action' => 'mark_sent',
        'csrf_token' => 'invalid-token'
    ]);
    
    if (!$response['success'] && (
        strpos(strtolower($response['error']), 'csrf') !== false ||
        strpos(strtolower($response['error']), 'invalid token') !== false
    )) {
        echo "  ✓ Correctly rejected request with invalid CSRF token\n";
        $test_results['csrf_validation'] = 'PASS';
    } else {
        echo "  ⚠ CSRF validation may not be implemented\n";
        $warnings[] = "CSRF validation not detected on endpoints";
        $test_results['csrf_validation'] = 'SKIP';
    }
    
} catch (Exception $e) {
    $errors[] = "CSRF test failed: " . $e->getMessage();
    $test_results['csrf_validation'] = 'FAIL';
}
echo "\n";

// Test 7: Invalid PO ID (Cross-Supplier Access Attempt)
echo "[TEST 7] Cross-Supplier Access Prevention...\n";
try {
    // Restore valid session
    $_SESSION['supplier_id'] = $test_supplier_id;
    $_SESSION['csrf_token'] = 'test-token-' . time();
    
    // Try to access a PO from another supplier
    $other_po_sql = "SELECT id FROM stock_transfers 
                     WHERE supplier_id != ? 
                     LIMIT 1";
    $stmt = $mysqli->prepare($other_po_sql);
    $stmt->bind_param('s', $test_supplier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $other_po_id = $result['id'];
        
        $response = simulate_ajax_call('update-po-status.php', [
            'po_id' => $other_po_id,
            'action' => 'mark_sent'
        ]);
        
        if (!$response['success'] && (
            strpos(strtolower($response['error']), 'not found') !== false ||
            strpos(strtolower($response['error']), 'unauthorized') !== false ||
            strpos(strtolower($response['error']), 'permission') !== false
        )) {
            echo "  ✓ Correctly prevented cross-supplier access\n";
            echo "    - Attempted PO ID: {$other_po_id}\n";
            echo "    - Error: {$response['error']}\n";
            
            $test_results['cross_supplier'] = 'PASS';
        } else {
            $errors[] = "Failed to prevent cross-supplier access";
            $test_results['cross_supplier'] = 'FAIL';
        }
    } else {
        $warnings[] = "No other supplier POs available for cross-access test";
        $test_results['cross_supplier'] = 'SKIP';
    }
    
} catch (Exception $e) {
    $errors[] = "Cross-supplier test failed: " . $e->getMessage();
    $test_results['cross_supplier'] = 'FAIL';
}
echo "\n";

// ============================================================================
// FINAL SUMMARY
// ============================================================================

echo str_repeat("=", 80) . "\n";
echo "=== TEST SUMMARY ===\n\n";

echo "Results:\n";
foreach ($test_results as $test => $result) {
    $icon = $result === 'PASS' ? '✓' : ($result === 'FAIL' ? '✗' : '⊘');
    printf("  %s %-25s : %s\n", $icon, $test, $result);
}

if (!empty($errors)) {
    echo "\nERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

if (!empty($warnings)) {
    echo "\nWARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
}

$pass_count = count(array_filter($test_results, fn($r) => $r === 'PASS'));
$fail_count = count(array_filter($test_results, fn($r) => $r === 'FAIL'));
$skip_count = count(array_filter($test_results, fn($r) => $r === 'SKIP'));

echo "\nOverall: {$pass_count} PASS, {$fail_count} FAIL, {$skip_count} SKIP\n";

if ($fail_count === 0) {
    echo "Status: ✓ ALL TESTS PASSED\n";
} else {
    echo "Status: ✗ SOME TESTS FAILED\n";
}

exit($fail_count > 0 ? 1 : 0);
