<?php
/**
 * Page Title Component
 * Displays breadcrumb and page title inside main content area
 *
 * Required variables:
 * - $pageTitle (string): Title of the current page
 * - $pageIcon (string): FontAwesome icon class (optional)
 * - $pageDescription (string): Subtitle/description (optional)
 * - $breadcrumb (array): Breadcrumb items (optional)
 * - $actionButtons (array): Action buttons for the page (optional)
 */

// Set defaults
$pageIcon = $pageIcon ?? 'fa-solid fa-chart-line';
$pageDescription = $pageDescription ?? '';
$breadcrumb = $breadcrumb ?? [];
$actionButtons = $actionButtons ?? [];
?>

<!-- Page Title Section - Scrolls with content -->
<div class="page-title-section mb-4">
    <div class="row align-items-end">
        <!-- Left: Breadcrumb + Title -->
        <div class="col-md-8">
            <!-- Breadcrumb (small, above title) -->
            <?php if (!empty($breadcrumb)): ?>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="/supplier/dashboard.php"><i class="fa-solid fa-home"></i> Home</a>
                        </li>
                        <?php foreach ($breadcrumb as $index => $item): ?>
                            <?php if ($index === count($breadcrumb) - 1): ?>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo htmlspecialchars($item['text']); ?>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item">
                                    <a href="<?php echo htmlspecialchars($item['href']); ?>">
                                        <?php echo htmlspecialchars($item['text']); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php else: ?>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="/supplier/dashboard.php"><i class="fa-solid fa-home"></i> Home</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($pageTitle); ?>
                        </li>
                    </ol>
                </nav>
            <?php endif; ?>

            <!-- Page Title (BIG) -->
            <h1 class="page-title mb-0">
                <i class="<?php echo $pageIcon; ?> me-2"></i>
                <?php echo htmlspecialchars($pageTitle); ?>
            </h1>

            <!-- Optional Description -->
            <?php if ($pageDescription): ?>
                <p class="page-description text-muted mb-0 mt-1">
                    <?php echo htmlspecialchars($pageDescription); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Right: Action Buttons -->
        <?php if (!empty($actionButtons)): ?>
            <div class="col-md-4 text-end">
                <?php if (is_string($actionButtons)): ?>
                    <!-- Raw HTML buttons -->
                    <?php echo $actionButtons; ?>
                <?php else: ?>
                    <!-- Array of button definitions -->
                    <div class="btn-group" role="group">
                        <?php foreach ($actionButtons as $button): ?>
                            <a href="<?php echo htmlspecialchars($button['href'] ?? '#'); ?>"
                               class="btn <?php echo htmlspecialchars($button['class'] ?? 'btn-primary'); ?>"
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
        <?php endif; ?>
    </div>
</div>
