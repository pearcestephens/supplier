<?php
/**
 * API: Get Stores
 *
 * Returns list of all active stores/outlets
 */

declare(strict_types=1);

try {
    $stmt = $db->prepare("
        SELECT
            id,
            name,
            contact_name,
            contact_phone,
            contact_email,
            physical_address_line_1,
            physical_address_city
        FROM vend_outlets
        WHERE deleted_at IS NULL
        ORDER BY name ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $stores = [];
    while ($row = $result->fetch_assoc()) {
        $stores[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'contact_name' => $row['contact_name'],
            'contact_phone' => $row['contact_phone'],
            'contact_email' => $row['contact_email'],
            'address' => trim(($row['physical_address_line_1'] ?? '') . ' ' . ($row['physical_address_city'] ?? ''))
        ];
    }

    sendApiResponse(true, [
        'stores' => $stores,
        'total' => count($stores)
    ]);

} catch (Exception $e) {
    error_log('Get stores error: ' . $e->getMessage());
    sendApiResponse(false, null, 'Failed to retrieve stores', [
        'code' => 'QUERY_ERROR'
    ], 500);
}
