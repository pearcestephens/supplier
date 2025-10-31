<?php
// Quick database test script
$mysqli = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Connected successfully!\n\n";

$result = $mysqli->query("SELECT id, name, contact_name FROM vend_suppliers WHERE deleted_at = '' LIMIT 5");

if ($result) {
    echo "Active Suppliers:\n";
    echo "================\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['id'] . " | " . $row['name'] . " | " . ($row['contact_name'] ?: 'N/A') . "\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}

$mysqli->close();
