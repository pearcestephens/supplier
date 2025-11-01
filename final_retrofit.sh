#!/bin/bash
# Final Comprehensive Supplier Debug Bridge Retrofit
# Handles all edge cases correctly

set -e

cd /home/master/applications/jcepnzzkmj/public_html/supplier

echo "================================================"
echo "Supplier Debug Bridge - Comprehensive Retrofit"
echo "================================================"
echo ""

SUCCESS=0
FAIL=0
SKIP=0

retrofit_file() {
    local file="$1"
    local relpath="$2"

    # Skip if already has bridge
    if grep -q "/_bot_debug_bridge.php" "$file"; then
        echo "  [SKIP] Already retrofitted: $file"
        ((SKIP++))
        return 0
    fi

    # Backup
    cp "$file" "${file}.pre_debug_retrofit"

    # Python script for precise insertion
    python3 << 'PYPYTHON' "$file" "$relpath"
import sys
import re

file_path = sys.argv[1]
rel_path = sys.argv[2]

with open(file_path, 'r') as f:
    content = f.read()

# Find position after declare(strict_types=1); or after docblock
bridge_line = f"require_once dirname(__DIR__) . '{rel_path}/_bot_debug_bridge.php';\n"

# Pattern 1: After declare(strict_types=1);
if 'declare(strict_types=1);' in content:
    content = content.replace(
        'declare(strict_types=1);',
        f'declare(strict_types=1);\n{bridge_line}',
        1
    )
else:
    # Pattern 2: After opening <?php and potential docblock
    # Find first require/include or class/function definition
    lines = content.split('\n')
    inserted = False
    new_lines = []

    for i, line in enumerate(lines):
        new_lines.append(line)
        # Insert after <?php and before first require/class/function
        if not inserted and i > 0 and line.strip() and not line.strip().startswith('*') and not line.strip().startswith('/*') and not line.strip().startswith('//') and (line.strip().startswith('require') or line.strip().startswith('include') or line.strip().startswith('class') or line.strip().startswith('function')):
            new_lines.insert(-1, bridge_line.rstrip())
            inserted = True
            break

    if inserted:
        content = '\n'.join(new_lines)

# Replace Auth::getSupplierId() calls
content = re.sub(r'Auth::getSupplierId\(\)', 'supplier_current_id_bridge()', content)

# Replace requireAuth() calls
content = re.sub(r'requireAuth\(\)', 'supplier_require_auth_bridge(true)', content)

# Write back
with open(file_path, 'w') as f:
    f.write(content)

PYPYTHON

    # Verify syntax
    if php -l "$file" > /dev/null 2>&1; then
        echo "  [✓] Success: $file"
        rm "${file}.pre_debug_retrofit"
        ((SUCCESS++))
        return 0
    else
        echo "  [✗] Syntax error, restoring: $file"
        mv "${file}.pre_debug_retrofit" "$file"
        ((FAIL++))
        return 1
    fi
}

# Retrofit API files
echo "Retrofitting API files..."
for f in api/*.php; do
    [ -f "$f" ] && retrofit_file "$f" ".."
done

echo ""
echo "================================================"
echo "Results:"
echo "  Success: $SUCCESS"
echo "  Failed: $FAIL"
echo "  Skipped: $SKIP"
echo "================================================"
