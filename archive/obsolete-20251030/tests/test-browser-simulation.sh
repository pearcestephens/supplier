#!/bin/bash

###############################################################################
# BROWSER SIMULATION TEST SCRIPT
# Simulates clicking through every link and testing every page
###############################################################################

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

BASE_URL="https://staff.vapeshed.co.nz/supplier"
SESSION_TOKEN="YOUR_SESSION_TOKEN_HERE"
SUPPLIER_ID="YOUR_SUPPLIER_ID_HERE"
OUTPUT_DIR="./test_results"

# Create output directory
mkdir -p "$OUTPUT_DIR"

###############################################################################
# Helper Functions
###############################################################################

log_test() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅${NC} $1"
}

log_error() {
    echo -e "${RED}❌${NC} $1"
}

log_info() {
    echo -e "${BLUE}ℹ️${NC}  $1"
}

# Simulate page visit and extract all links
visit_page() {
    local url=$1
    local page_name=$2
    local output_file="$OUTPUT_DIR/${page_name}_response.html"
    
    log_test "Visiting: $page_name ($url)"
    
    # Download page
    curl -s \
        -b "session_token=$SESSION_TOKEN" \
        -o "$output_file" \
        --max-time 15 \
        "$url"
    
    # Check HTTP status
    local status=$(curl -s -o /dev/null -w "%{http_code}" \
        -b "session_token=$SESSION_TOKEN" \
        "$url")
    
    if [ "$status" = "200" ]; then
        log_success "HTTP $status - Page loaded successfully"
    else
        log_error "HTTP $status - Page load failed"
        return 1
    fi
    
    # Extract and count key elements
    local title=$(grep -oP '(?<=<title>)[^<]+' "$output_file" | head -n 1)
    local css_count=$(grep -o '<link.*\.css' "$output_file" | wc -l)
    local js_count=$(grep -o '<script.*\.js' "$output_file" | wc -l)
    local api_calls=$(grep -oP 'fetch\(['"'"'"]([^'"'"'"]+)['"'"'"]' "$output_file" | wc -l)
    local links=$(grep -oP 'href=['"'"'"]([^'"'"'"#]+)['"'"'"]' "$output_file" | wc -l)
    
    log_info "Title: $title"
    log_info "CSS files: $css_count"
    log_info "JS files: $js_count"
    log_info "API calls detected: $api_calls"
    log_info "Links found: $links"
    
    # Check for common errors
    if grep -q "Fatal error" "$output_file"; then
        log_error "PHP Fatal error detected!"
        grep "Fatal error" "$output_file"
    fi
    
    if grep -q "Warning:" "$output_file"; then
        log_error "PHP Warning detected!"
        grep "Warning:" "$output_file" | head -n 3
    fi
    
    if grep -q "Notice:" "$output_file"; then
        log_error "PHP Notice detected!"
        grep "Notice:" "$output_file" | head -n 3
    fi
    
    if grep -q "undefined" "$output_file"; then
        log_error "JavaScript 'undefined' detected!"
    fi
    
    # Extract API endpoints from page
    grep -oP 'fetch\(['"'"'"]([^'"'"'"]+)['"'"'"]' "$output_file" | \
        sed 's/fetch(['"'"'"]//' | sed 's/['"'"'"].*//' > "$OUTPUT_DIR/${page_name}_api_calls.txt"
    
    # Extract internal links
    grep -oP 'href=['"'"'"]([^'"'"'"#]+)['"'"'"]' "$output_file" | \
        sed 's/href=['"'"'"]//' | sed 's/['"'"'"].*//' | \
        grep -v '^http' | grep -v '^//' > "$OUTPUT_DIR/${page_name}_links.txt"
    
    echo ""
    return 0
}

# Test an API endpoint
test_api() {
    local endpoint=$1
    local description=$2
    
    log_test "Testing API: $description"
    
    local full_url="$BASE_URL$endpoint"
    local response=$(curl -s \
        -b "session_token=$SESSION_TOKEN" \
        --max-time 10 \
        "$full_url")
    
    # Check if valid JSON
    if echo "$response" | jq empty 2>/dev/null; then
        local success=$(echo "$response" | jq -r '.success // "null"')
        
        if [ "$success" = "true" ]; then
            log_success "API returned success=true"
            
            # Show data summary
            local data_type=$(echo "$response" | jq -r 'if .data | type == "array" then "array[\(.data | length)]" elif .data | type == "object" then "object" else .data | type end')
            log_info "Data type: $data_type"
            
            # Save response
            echo "$response" | jq . > "$OUTPUT_DIR/api_$(echo $endpoint | sed 's/[\/\?=&]/_/g').json"
        else
            log_error "API returned success=false"
            echo "$response" | jq .
        fi
    else
        log_error "Invalid JSON response"
        echo "$response" | head -n 5
    fi
    
    echo ""
}

###############################################################################
# MAIN TESTING SEQUENCE
###############################################################################

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     SUPPLIER PORTAL - COMPREHENSIVE BROWSER SIMULATION   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

log_info "Base URL: $BASE_URL"
log_info "Output directory: $OUTPUT_DIR"
log_info "Test started: $(date)"
echo ""

###############################################################################
# STEP 1: Visit All Main Pages
###############################################################################

echo -e "${YELLOW}═══ STEP 1: Testing Main Pages ═══${NC}"
echo ""

visit_page "$BASE_URL/dashboard.php" "dashboard"
visit_page "$BASE_URL/orders.php" "orders"
visit_page "$BASE_URL/products.php" "products"
visit_page "$BASE_URL/warranty.php" "warranty"
visit_page "$BASE_URL/downloads.php" "downloads"
visit_page "$BASE_URL/reports.php" "reports"
visit_page "$BASE_URL/account.php" "account"

###############################################################################
# STEP 2: Test All Dashboard APIs
###############################################################################

echo -e "${YELLOW}═══ STEP 2: Testing Dashboard APIs ═══${NC}"
echo ""

test_api "/api/dashboard-stats.php" "Dashboard statistics"
test_api "/api/dashboard-orders-table.php?limit=10" "Dashboard orders table"
test_api "/api/dashboard-stock-alerts.php" "Dashboard stock alerts"
test_api "/api/dashboard-charts.php" "Dashboard charts data"

###############################################################################
# STEP 3: Test Purchase Order APIs
###############################################################################

echo -e "${YELLOW}═══ STEP 3: Testing Purchase Order APIs ═══${NC}"
echo ""

test_api "/api/po-list.php?page=1&limit=25" "Purchase orders list"
test_api "/api/po-stats.php" "Purchase order statistics"
test_api "/api/po-outlets.php" "Outlet list for filters"

# Get a sample order ID
log_test "Fetching sample order for detail test..."
ORDER_ID=$(curl -s -b "session_token=$SESSION_TOKEN" "$BASE_URL/api/po-list.php?limit=1" | jq -r '.data.orders[0].id // empty')

if [ -n "$ORDER_ID" ]; then
    log_info "Found order ID: $ORDER_ID"
    test_api "/api/po-detail.php?id=$ORDER_ID" "Purchase order detail"
else
    log_error "No orders found to test detail endpoint"
fi
echo ""

###############################################################################
# STEP 4: Test Product APIs
###############################################################################

echo -e "${YELLOW}═══ STEP 4: Testing Product APIs ═══${NC}"
echo ""

test_api "/api/products-list.php?page=1&limit=25" "Products list"
test_api "/api/products-stats.php" "Product statistics"

###############################################################################
# STEP 5: Test Warranty APIs
###############################################################################

echo -e "${YELLOW}═══ STEP 5: Testing Warranty APIs ═══${NC}"
echo ""

test_api "/api/warranty-list.php?page=1&limit=25" "Warranty claims list"
test_api "/api/warranty-stats.php" "Warranty statistics"

###############################################################################
# STEP 6: Test Static Assets
###############################################################################

echo -e "${YELLOW}═══ STEP 6: Testing Static Assets ═══${NC}"
echo ""

test_asset() {
    local url=$1
    local name=$2
    
    log_test "Testing: $name"
    
    local status=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    
    if [ "$status" = "200" ]; then
        log_success "HTTP $status - Asset loaded"
    else
        log_error "HTTP $status - Asset failed to load"
    fi
    echo ""
}

test_asset "$BASE_URL/assets/css/professional-black.css" "Main CSS"
test_asset "$BASE_URL/assets/css/dashboard-widgets.css" "Dashboard CSS"
test_asset "$BASE_URL/assets/js/supplier-portal.js" "Main JavaScript"

###############################################################################
# STEP 7: Generate Report
###############################################################################

echo -e "${YELLOW}═══ STEP 7: Generating Analysis Report ═══${NC}"
echo ""

REPORT_FILE="$OUTPUT_DIR/ANALYSIS_REPORT.md"

cat > "$REPORT_FILE" << 'REPORT_HEADER'
# Supplier Portal - Comprehensive Analysis Report

**Generated:** $(date)

---

## 📊 Summary

REPORT_HEADER

# Count total files
TOTAL_HTML=$(ls "$OUTPUT_DIR"/*_response.html 2>/dev/null | wc -l)
TOTAL_JSON=$(ls "$OUTPUT_DIR"/api_*.json 2>/dev/null | wc -l)

cat >> "$REPORT_FILE" << REPORT_SUMMARY

- **Pages Tested:** $TOTAL_HTML
- **API Endpoints Tested:** $TOTAL_JSON
- **Test Date:** $(date)
- **Output Directory:** $OUTPUT_DIR

---

## 🌐 Pages Analyzed

REPORT_SUMMARY

# List all tested pages
for html_file in "$OUTPUT_DIR"/*_response.html; do
    if [ -f "$html_file" ]; then
        page_name=$(basename "$html_file" _response.html)
        title=$(grep -oP '(?<=<title>)[^<]+' "$html_file" | head -n 1)
        size=$(du -h "$html_file" | cut -f1)
        
        echo "### $page_name" >> "$REPORT_FILE"
        echo "- **Title:** $title" >> "$REPORT_FILE"
        echo "- **Size:** $size" >> "$REPORT_FILE"
        echo "" >> "$REPORT_FILE"
    fi
done

cat >> "$REPORT_FILE" << 'REPORT_APIS'

---

## 🔌 API Endpoints Found

REPORT_APIS

# Aggregate all API calls
find "$OUTPUT_DIR" -name "*_api_calls.txt" -exec cat {} \; | sort -u >> "$REPORT_FILE"

cat >> "$REPORT_FILE" << 'REPORT_LINKS'

---

## 🔗 Internal Links Found

REPORT_LINKS

# Aggregate all links
find "$OUTPUT_DIR" -name "*_links.txt" -exec cat {} \; | sort -u >> "$REPORT_FILE"

log_success "Analysis report generated: $REPORT_FILE"

###############################################################################
# FINAL SUMMARY
###############################################################################

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    TEST COMPLETE                         ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

log_info "Test completed: $(date)"
log_info "Results saved to: $OUTPUT_DIR"
log_info "Analysis report: $REPORT_FILE"
echo ""

echo -e "${GREEN}Next steps:${NC}"
echo "1. Review $REPORT_FILE for full analysis"
echo "2. Check $OUTPUT_DIR for individual page responses"
echo "3. Examine JSON files for API response data"
echo ""
