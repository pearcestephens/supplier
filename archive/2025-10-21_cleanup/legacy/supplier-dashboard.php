<?php
// IP Restriction: Only allow 125.236.217.224
if ($_SERVER['REMOTE_ADDR'] !== '125.236.217.224') {
	echo '<div style="width:100%;height:100vh;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#b00;background:#fff;">Hang tight, we will be back folks!</div>';
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard</title>
</head>
<body>
    <h1>Supplier Dashboard</h1>
    <p>Welcome to the supplier dashboard. Access granted for authorized IP.</p>
    <!-- Add your dashboard content here -->
</body>
</html>
