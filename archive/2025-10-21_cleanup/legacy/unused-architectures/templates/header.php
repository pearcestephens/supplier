<?php
/**
 * Supplier Portal - Header Template
 * 
 * @package CIS\Supplier\Templates
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

$supplier = get_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <title><?= htmlspecialchars($currentPage['title']) ?> - <?= PORTAL_NAME ?></title>
    <meta name="description" content="<?= htmlspecialchars($currentPage['description']) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= IMAGES_URL ?>favicon.ico">
    
    <!-- CSRF Token -->
    <?= csrf_meta() ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="<?= CSS_URL ?>portal.css" rel="stylesheet">
    <link href="<?= CSS_URL ?>components.css" rel="stylesheet">
    
    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <?php if (SHOW_DEBUG_INFO): ?>
    <!-- Debug Mode Active -->
    <style>
        body::before {
            content: "DEBUG MODE";
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #e74a3b;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 9999;
        }
    </style>
    <?php endif; ?>
</head>
<body id="page-top" class="supplier-portal">

<!-- Page Wrapper -->
<div id="wrapper">
