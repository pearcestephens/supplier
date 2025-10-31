<?php
/**
 * Header Bottom Component - Breadcrumb & Actions
 * 
 * @package SupplierPortal
 */

// Set default breadcrumb if not provided
if (!isset($breadcrumb)) {
    $breadcrumb = [
        ['text' => 'Dashboard', 'href' => '/supplier/index.php']
    ];
}

// Set default action buttons if not provided
if (!isset($actionButtons)) {
    $actionButtons = [];
}
?>
<!-- Header Bottom -->
<div class="header-bottom">
    <div class="header-content">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/supplier/index.php"><i class="fas fa-home"></i></a></li>
                <?php foreach ($breadcrumb as $index => $item): ?>
                    <?php if ($index === count($breadcrumb) - 1): ?>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($item['text']); ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['text']); ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        
        <!-- Action Buttons -->
        <?php if (!empty($actionButtons)): ?>
        <div class="d-flex gap-2">
            <?php foreach ($actionButtons as $button): ?>
                <a href="<?php echo htmlspecialchars($button['href'] ?? '#'); ?>" 
                   class="btn btn-sm <?php echo htmlspecialchars($button['class'] ?? 'btn-primary'); ?>"
                   <?php if (isset($button['onclick'])): ?>onclick="<?php echo htmlspecialchars($button['onclick']); ?>; return false;"<?php endif; ?>>
                    <?php if (isset($button['icon'])): ?>
                        <i class="<?php echo htmlspecialchars($button['icon']); ?> me-1"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($button['text']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
