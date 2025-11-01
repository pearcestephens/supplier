#!/bin/bash
# Supplier Debug Bridge Rollout Script
# Retrofits all supplier PHP files to use unified debug mode

set -e

SUPPLIER_DIR="/home/master/applications/jcepnzzkmj/public_html/supplier"
BRIDGE_FILE="_bot_debug_bridge.php"
LOG_FILE="/tmp/supplier_debug_retrofit_$(date +%Y%m%d_%H%M%S).log"

echo "========================================" | tee -a "$LOG_FILE"
echo "Supplier Debug Bridge Rollout" | tee -a "$LOG_FILE"
echo "Started: $(date)" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

cd "$SUPPLIER_DIR"

# Counters
total_files=0
modified_files=0
skipped_files=0
error_files=0

# Function to add bridge include to a file
add_bridge_include() {
    local file="$1"
    local relative_path="$2"

    # Check if bridge already included
    if grep -q "_bot_debug_bridge.php" "$file" 2>/dev/null; then
        echo "  [SKIP] Already includes bridge: $file" | tee -a "$LOG_FILE"
        ((skipped_files++))
        return 0
    fi

    # Create backup
    cp "$file" "${file}.bak.$(date +%Y%m%d_%H%M%S)"

    # Add require_once after opening <?php tag
    # Use perl for in-place editing to handle multiline properly
    perl -i -pe '
        BEGIN { $done = 0; }
        if (!$done && /^<\?php/) {
            $_ .= "\nrequire_once __DIR__ . '"'"'/$relative_path/_bot_debug_bridge.php'"'"';\n";
            $done = 1;
        }
    ' "$file"

    echo "  [OK] Added bridge include: $file" | tee -a "$LOG_FILE"
    ((modified_files++))
}

# Function to replace Auth::getSupplierId() calls
replace_auth_calls() {
    local file="$1"

    # Check if file contains Auth::getSupplierId()
    if ! grep -q "Auth::getSupplierId()" "$file" 2>/dev/null; then
        return 0
    fi

    # Replace with bridge function
    sed -i 's/Auth::getSupplierId()/supplier_current_id_bridge()/g' "$file"

    echo "  [OK] Replaced Auth::getSupplierId() calls: $file" | tee -a "$LOG_FILE"
}

# Function to replace direct session reads
replace_session_reads() {
    local file="$1"

    # Check if file contains $_SESSION['supplier_id']
    if ! grep -q "\$_SESSION\['supplier_id'\]" "$file" 2>/dev/null; then
        return 0
    fi

    # Replace simple reads (not assignments)
    # This is a conservative approach - only replace obvious reads
    sed -i "s/\$_SESSION\['supplier_id'\]/supplier_current_id_bridge()/g" "$file"

    echo "  [OK] Replaced \$_SESSION['supplier_id'] reads: $file" | tee -a "$LOG_FILE"
}

echo "Phase 1: Retrofitting API files..." | tee -a "$LOG_FILE"
echo "-----------------------------------" | tee -a "$LOG_FILE"

# Process all API files
for file in api/*.php; do
    if [ -f "$file" ]; then
        ((total_files++))
        echo "Processing: $file" | tee -a "$LOG_FILE"

        add_bridge_include "$file" ".."
        replace_auth_calls "$file"
        replace_session_reads "$file"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "Phase 2: Retrofitting tab files..." | tee -a "$LOG_FILE"
echo "-----------------------------------" | tee -a "$LOG_FILE"

# Process all tab files
for file in tabs/*.php; do
    if [ -f "$file" ]; then
        ((total_files++))
        echo "Processing: $file" | tee -a "$LOG_FILE"

        add_bridge_include "$file" ".."
        replace_auth_calls "$file"
        replace_session_reads "$file"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "Phase 3: Retrofitting root page files..." | tee -a "$LOG_FILE"
echo "-----------------------------------" | tee -a "$LOG_FILE"

# Process root PHP pages (excluding config, bootstrap, bridge itself)
for file in *.php; do
    if [ -f "$file" ] && [ "$file" != "config.php" ] && [ "$file" != "bootstrap.php" ] && [ "$file" != "$BRIDGE_FILE" ]; then
        ((total_files++))
        echo "Processing: $file" | tee -a "$LOG_FILE"

        add_bridge_include "$file" "."
        replace_auth_calls "$file"
        replace_session_reads "$file"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
echo "Rollout Summary" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
echo "Total files processed: $total_files" | tee -a "$LOG_FILE"
echo "Files modified: $modified_files" | tee -a "$LOG_FILE"
echo "Files skipped: $skipped_files" | tee -a "$LOG_FILE"
echo "Files with errors: $error_files" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Completed: $(date)" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"

# Verify PHP syntax on modified files
echo "" | tee -a "$LOG_FILE"
echo "Verifying PHP syntax..." | tee -a "$LOG_FILE"
syntax_errors=0

for file in api/*.php tabs/*.php *.php; do
    if [ -f "$file" ] && [ -f "${file}.bak."* 2>/dev/null ]; then
        if ! php -l "$file" > /dev/null 2>&1; then
            echo "  [ERROR] Syntax error in: $file" | tee -a "$LOG_FILE"
            ((syntax_errors++))
        fi
    fi
done

if [ $syntax_errors -eq 0 ]; then
    echo "✓ All files passed syntax check" | tee -a "$LOG_FILE"
else
    echo "✗ $syntax_errors files have syntax errors" | tee -a "$LOG_FILE"
    echo "  Review log file: $LOG_FILE" | tee -a "$LOG_FILE"
fi

echo "" | tee -a "$LOG_FILE"
echo "Done! Bridge rollout complete." | tee -a "$LOG_FILE"
