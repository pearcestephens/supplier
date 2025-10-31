#!/bin/bash
# Debug Mode Toggle Script
# Quick way to enable/disable DEBUG MODE without manual editing

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

CONFIG_FILE="/home/master/applications/jcepnzzkmj/public_html/supplier/config.php"

# Check if we can write to config
if [ ! -w "$CONFIG_FILE" ]; then
    echo -e "${RED}❌ Error: Cannot write to $CONFIG_FILE${NC}"
    echo "Run with sudo or fix file permissions"
    exit 1
fi

echo "================================================"
echo "    DEBUG MODE Toggle for Supplier Portal"
echo "================================================"
echo ""

# Show current status
echo "Current Configuration:"
grep "define('DEBUG_MODE_ENABLED'" "$CONFIG_FILE"
grep "define('DEBUG_MODE_SUPPLIER_ID'" "$CONFIG_FILE"
echo ""

# Menu
echo "What would you like to do?"
echo "1) Enable DEBUG MODE"
echo "2) Disable DEBUG MODE"
echo "3) Change Supplier ID (with DEBUG MODE on)"
echo "4) View access logs"
echo "5) Exit"
echo ""
read -p "Choose option (1-5): " choice

case $choice in
    1)
        echo -e "${YELLOW}Enabling DEBUG MODE...${NC}"
        sed -i "s/define('DEBUG_MODE_ENABLED', false)/define('DEBUG_MODE_ENABLED', true)/" "$CONFIG_FILE"
        echo -e "${GREEN}✅ DEBUG MODE ENABLED${NC}"
        echo "You can now browse pages without logging in!"
        echo ""
        grep "define('DEBUG_MODE" "$CONFIG_FILE"
        ;;

    2)
        echo -e "${YELLOW}Disabling DEBUG MODE...${NC}"
        sed -i "s/define('DEBUG_MODE_ENABLED', true)/define('DEBUG_MODE_ENABLED', false)/" "$CONFIG_FILE"
        echo -e "${GREEN}✅ DEBUG MODE DISABLED${NC}"
        echo "Normal authentication is now required"
        echo ""
        grep "define('DEBUG_MODE" "$CONFIG_FILE"
        ;;

    3)
        read -p "Enter Supplier ID to test with: " supplier_id

        # Validate it's a number
        if ! [[ "$supplier_id" =~ ^[0-9]+$ ]]; then
            echo -e "${RED}❌ Error: Supplier ID must be a number${NC}"
            exit 1
        fi

        # Check if supplier exists
        echo "Checking if Supplier ID $supplier_id exists..."
        result=$(mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -N -e "SELECT COUNT(*) FROM vend_suppliers WHERE id = $supplier_id LIMIT 1;" 2>/dev/null)

        if [ "$result" -eq 0 ]; then
            echo -e "${RED}❌ Supplier ID $supplier_id not found in database${NC}"
            exit 1
        fi

        echo -e "${YELLOW}Updating Supplier ID to $supplier_id...${NC}"
        sed -i "s/define('DEBUG_MODE_SUPPLIER_ID', [0-9]*)/define('DEBUG_MODE_SUPPLIER_ID', $supplier_id)/" "$CONFIG_FILE"

        # Also enable debug mode if not already
        sed -i "s/define('DEBUG_MODE_ENABLED', false)/define('DEBUG_MODE_ENABLED', true)/" "$CONFIG_FILE"

        echo -e "${GREEN}✅ Updated!${NC}"
        echo ""
        grep "define('DEBUG_MODE" "$CONFIG_FILE"
        echo ""
        echo "Now testing with Supplier ID: $supplier_id"
        ;;

    4)
        log_file="/home/master/applications/jcepnzzkmj/public_html/supplier/logs/debug-mode.log"

        if [ ! -f "$log_file" ]; then
            echo -e "${YELLOW}No access log yet${NC}"
            echo "Enable DEBUG MODE and browse some pages to generate log entries"
        else
            echo "Last 20 DEBUG MODE access entries:"
            echo "================================================"
            tail -20 "$log_file"
            echo "================================================"
            echo ""
            echo "Full log location: $log_file"
            echo "To watch in real-time: tail -f $log_file"
        fi
        ;;

    5)
        echo "Goodbye!"
        exit 0
        ;;

    *)
        echo -e "${RED}Invalid option${NC}"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}Done!${NC}"
echo ""
echo "Debug Mode Control Panel: https://staff.vapeshed.co.nz/supplier/debug-mode.php"
