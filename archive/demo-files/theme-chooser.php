<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Options - The Vape Shed Supplier Portal</title>
    
    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .theme-selector {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .theme-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .theme-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a1a1a;
        }
        
        .theme-header p {
            color: #666;
            font-size: 16px;
        }
        
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .theme-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .theme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        
        .theme-preview {
            padding: 20px;
            min-height: 400px;
        }
        
        .theme-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        
        .theme-description {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .theme-features {
            margin: 20px 0;
        }
        
        .theme-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .theme-features li {
            padding: 8px 0;
            color: #444;
            font-size: 13px;
        }
        
        .theme-features li i {
            margin-right: 8px;
            color: #10B981;
        }
        
        .theme-colors {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .color-swatch {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            position: relative;
            cursor: help;
        }
        
        .color-swatch:hover::after {
            content: attr(data-name);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a1a;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 10;
        }
        
        .theme-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .apply-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
        }
        
        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .sample-elements {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .sample-sidebar {
            background: #000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .sample-nav-item {
            color: #c2c7d0;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            font-size: 13px;
            display: flex;
            align-items: center;
        }
        
        .sample-nav-item i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sample-nav-item.active {
            border-left: 3px solid;
        }
        
        .btn-sample {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-sample:hover {
            transform: translateY(-2px);
        }
        
        /* Theme 1: Gold Standard */
        .theme1-primary { background: linear-gradient(135deg, #F7C948 0%, #E5B839 100%); color: #1a1a1a; box-shadow: 0 4px 12px rgba(247, 201, 72, 0.4); }
        .theme1-success { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        .theme1-info { background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); color: white; box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4); }
        .theme1-active { background-color: rgba(247, 201, 72, 0.15); color: white; border-left-color: #F7C948; }
        
        /* Theme 2: Professional Blue */
        .theme2-primary { background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); color: white; box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4); }
        .theme2-success { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        .theme2-info { background: linear-gradient(135deg, #06B6D4 0%, #0891B2 100%); color: white; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4); }
        .theme2-active { background-color: rgba(74, 144, 226, 0.15); color: white; border-left-color: #4A90E2; }
        
        /* Theme 3: Modern Minimal */
        .theme3-primary { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4); }
        .theme3-success { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        .theme3-info { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }
        .theme3-active { background-color: rgba(59, 130, 246, 0.15); color: white; border-left-color: #3B82F6; }
        
        @media (max-width: 768px) {
            .theme-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="theme-selector">
    <div class="theme-header">
        <h1>🎨 Choose Your Perfect Theme</h1>
        <p>Pick the color scheme that best represents The Vape Shed supplier portal</p>
    </div>
    
    <div class="theme-grid">
        
        <!-- THEME 1: GOLD STANDARD -->
        <div class="theme-card">
            <div class="theme-preview">
                <div class="theme-title">1️⃣ Gold Standard</div>
                <div class="theme-description">Bold gold accents with black sidebar - matches your retail brand</div>
                
                <div class="theme-colors">
                    <div class="color-swatch" style="background: #F7C948;" data-name="Gold Primary"></div>
                    <div class="color-swatch" style="background: #10B981;" data-name="Green Success"></div>
                    <div class="color-swatch" style="background: #4A90E2;" data-name="Blue Info"></div>
                    <div class="color-swatch" style="background: #000000;" data-name="Black Sidebar"></div>
                </div>
                
                <div class="sample-elements">
                    <div class="sample-sidebar">
                        <div class="sample-nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
                        <div class="sample-nav-item theme1-active active"><i class="fas fa-shopping-cart"></i> Orders</div>
                        <div class="sample-nav-item"><i class="fas fa-tools"></i> Warranty</div>
                    </div>
                    
                    <div>
                        <button class="btn-sample theme1-primary">Download Orders</button>
                        <button class="btn-sample theme1-success">Submit Claim</button>
                        <button class="btn-sample theme1-info">View Reports</button>
                    </div>
                </div>
                
                <div class="theme-features">
                    <strong>Best for:</strong>
                    <ul>
                        <li><i class="fas fa-check"></i> Matching retail brand (yellow/gold)</li>
                        <li><i class="fas fa-check"></i> Bold, attention-grabbing</li>
                        <li><i class="fas fa-check"></i> Energetic and modern</li>
                        <li><i class="fas fa-check"></i> Pure black sidebar</li>
                    </ul>
                </div>
                
                <div class="theme-buttons">
                    <a href="?theme=gold" class="apply-btn theme1-primary">✨ Apply Gold Standard</a>
                </div>
            </div>
        </div>
        
        <!-- THEME 2: PROFESSIONAL BLUE -->
        <div class="theme-card">
            <div class="theme-preview">
                <div class="theme-title">2️⃣ Professional Blue</div>
                <div class="theme-description">Clean blue primary with black sidebar - B2B professional</div>
                
                <div class="theme-colors">
                    <div class="color-swatch" style="background: #4A90E2;" data-name="Blue Primary"></div>
                    <div class="color-swatch" style="background: #10B981;" data-name="Green Success"></div>
                    <div class="color-swatch" style="background: #06B6D4;" data-name="Cyan Info"></div>
                    <div class="color-swatch" style="background: #000000;" data-name="Black Sidebar"></div>
                </div>
                
                <div class="sample-elements">
                    <div class="sample-sidebar">
                        <div class="sample-nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
                        <div class="sample-nav-item theme2-active active"><i class="fas fa-shopping-cart"></i> Orders</div>
                        <div class="sample-nav-item"><i class="fas fa-tools"></i> Warranty</div>
                    </div>
                    
                    <div>
                        <button class="btn-sample theme2-primary">Download Orders</button>
                        <button class="btn-sample theme2-success">Submit Claim</button>
                        <button class="btn-sample theme2-info">View Reports</button>
                    </div>
                </div>
                
                <div class="theme-features">
                    <strong>Best for:</strong>
                    <ul>
                        <li><i class="fas fa-check"></i> B2B professional feel</li>
                        <li><i class="fas fa-check"></i> Trustworthy and stable</li>
                        <li><i class="fas fa-check"></i> Easy on the eyes</li>
                        <li><i class="fas fa-check"></i> Universal acceptance</li>
                    </ul>
                </div>
                
                <div class="theme-buttons">
                    <a href="?theme=blue" class="apply-btn theme2-primary">🔷 Apply Professional Blue</a>
                </div>
            </div>
        </div>
        
        <!-- THEME 3: MODERN MINIMAL -->
        <div class="theme-card">
            <div class="theme-preview">
                <div class="theme-title">3️⃣ Modern Minimal</div>
                <div class="theme-description">Bright modern colors with black sidebar - tech-forward</div>
                
                <div class="theme-colors">
                    <div class="color-swatch" style="background: #3B82F6;" data-name="Modern Blue"></div>
                    <div class="color-swatch" style="background: #10B981;" data-name="Green Success"></div>
                    <div class="color-swatch" style="background: #8B5CF6;" data-name="Purple Info"></div>
                    <div class="color-swatch" style="background: #000000;" data-name="Black Sidebar"></div>
                </div>
                
                <div class="sample-elements">
                    <div class="sample-sidebar">
                        <div class="sample-nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
                        <div class="sample-nav-item theme3-active active"><i class="fas fa-shopping-cart"></i> Orders</div>
                        <div class="sample-nav-item"><i class="fas fa-tools"></i> Warranty</div>
                    </div>
                    
                    <div>
                        <button class="btn-sample theme3-primary">Download Orders</button>
                        <button class="btn-sample theme3-success">Submit Claim</button>
                        <button class="btn-sample theme3-info">View Reports</button>
                    </div>
                </div>
                
                <div class="theme-features">
                    <strong>Best for:</strong>
                    <ul>
                        <li><i class="fas fa-check"></i> Tech-forward look</li>
                        <li><i class="fas fa-check"></i> Modern and fresh</li>
                        <li><i class="fas fa-check"></i> Unique color palette</li>
                        <li><i class="fas fa-check"></i> SaaS-style design</li>
                    </ul>
                </div>
                
                <div class="theme-buttons">
                    <a href="?theme=modern" class="apply-btn theme3-primary">🚀 Apply Modern Minimal</a>
                </div>
            </div>
        </div>
        
    </div>
    
    <div class="theme-header">
        <h3 style="margin-bottom: 15px;">👀 Can't Decide?</h3>
        <p style="margin-bottom: 20px;">Click any "Apply" button to see it live on your portal. You can always come back and try another!</p>
        <p style="font-size: 14px; color: #666;">
            <strong>My recommendation:</strong> 
            <span style="color: #4A90E2; font-weight: 600;">Professional Blue</span> for B2B trust, or 
            <span style="color: #F7C948; font-weight: 600;">Gold Standard</span> if you want to match retail branding
        </p>
        <div style="margin-top: 20px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Portal
            </a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<?php
// Handle theme selection
if (isset($_GET['theme'])) {
    $theme = $_GET['theme'];
    echo "<script>alert('Theme \"" . htmlspecialchars($theme) . "\" selected! Tell me which theme you picked and I\\'ll apply it to your portal in 5 seconds!');</script>";
}
?>

</body>
</html>
