# 07 - Deployment Guide

**Safe deployment procedures and rollback strategies**

---

## Pre-Deployment Checklist

### Code Quality
- [ ] All files pass `php -l` syntax check
- [ ] No PHP errors in last 24 hours of logs
- [ ] All API endpoints tested and returning valid JSON
- [ ] Frontend tested in Chrome, Firefox, Safari
- [ ] Mobile responsive tested (375px, 768px, 1024px)

### Security
- [ ] No secrets in code (all in .env or config.php)
- [ ] Session cookies secure (HTTPS, HttpOnly, SameSite)
- [ ] CSRF tokens on all POST forms
- [ ] Multi-tenancy filtering on ALL queries
- [ ] Soft delete checks (`deleted_at IS NULL`)

### Database
- [ ] Migrations tested in staging
- [ ] Rollback scripts prepared
- [ ] Backups verified and tested

### Documentation
- [ ] CHANGELOG.md updated
- [ ] Breaking changes documented
- [ ] New features documented in KB

---

## Deployment Steps

### Step 1: Backup (CRITICAL)

```bash
#!/bin/bash
# backup.sh - Run BEFORE any deployment

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/master/backups/supplier_portal"
APP_DIR="/home/master/applications/jcepnzzkmj/public_html/supplier"

mkdir -p $BACKUP_DIR

# Backup files
echo "Backing up files..."
tar -czf $BACKUP_DIR/files_$TIMESTAMP.tar.gz \
    --exclude='logs/*' \
    --exclude='cache/*' \
    -C $(dirname $APP_DIR) \
    $(basename $APP_DIR)

# Backup database
echo "Backing up database..."
mysqldump -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
    vend_suppliers \
    vend_consignments \
    vend_products \
    vend_inventory \
    faulty_products \
    faulty_product_notes \
    supplier_activity_log \
    supplier_portal_sessions \
    > $BACKUP_DIR/database_$TIMESTAMP.sql

echo "✅ Backup complete"
echo "Files: $BACKUP_DIR/files_$TIMESTAMP.tar.gz"
echo "Database: $BACKUP_DIR/database_$TIMESTAMP.sql"
```

**Run with:**
```bash
chmod +x backup.sh
./backup.sh
```

### Step 2: Deploy Code

```bash
#!/bin/bash
# deploy.sh - Deploy new code

APP_DIR="/home/master/applications/jcepnzzkmj/public_html/supplier"
TEMP_DIR="/tmp/supplier_deploy_$(date +%s)"

echo "Starting deployment..."

# Create temporary directory
mkdir -p $TEMP_DIR

# Copy new files to temp (assuming files are in current directory)
echo "Copying files to temp..."
cp -r ./* $TEMP_DIR/

# Test PHP syntax in temp directory
echo "Testing PHP syntax..."
for file in $(find $TEMP_DIR -name "*.php"); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ Syntax error in $file"
        php -l "$file"
        rm -rf $TEMP_DIR
        exit 1
    fi
done
echo "✅ All files passed syntax check"

# Sync to production (preserves config.php)
echo "Syncing to production..."
rsync -av --delete \
    --exclude='config.php' \
    --exclude='logs/*' \
    --exclude='cache/*' \
    --exclude='.git' \
    $TEMP_DIR/ $APP_DIR/

# Clean up
rm -rf $TEMP_DIR

echo "✅ Deployment complete"
```

### Step 3: Run Migrations (if any)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier
php migrations/run-pending.php
```

### Step 4: Clear Cache

```bash
# Clear any cached data
rm -rf /home/master/applications/jcepnzzkmj/public_html/supplier/cache/*

# Restart PHP-FPM (if needed)
# sudo systemctl restart php-fpm
```

### Step 5: Smoke Test

```bash
#!/bin/bash
# smoke-test.sh - Quick production verification

BASE_URL="https://staff.vapeshed.co.nz/supplier"

echo "Running smoke tests..."

# Test homepage
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Homepage loads"
else
    echo "❌ Homepage failed (HTTP $HTTP_CODE)"
fi

# Test API health (session-debug doesn't require auth)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/api/session-debug.php)
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "✅ API accessible"
else
    echo "❌ API failed (HTTP $HTTP_CODE)"
fi

# Check PHP errors in last 5 minutes
RECENT_ERRORS=$(find /home/master/applications/jcepnzzkmj/logs -name "*.error.log" -mmin -5 -exec grep -i "fatal\|error" {} \; | wc -l)
if [ "$RECENT_ERRORS" = "0" ]; then
    echo "✅ No recent errors"
else
    echo "⚠️  Found $RECENT_ERRORS recent error(s)"
fi

echo "Smoke tests complete"
```

---

## Rollback Procedure

### Quick Rollback

```bash
#!/bin/bash
# rollback.sh - Restore from backup

# List available backups
echo "Available backups:"
ls -lh /home/master/backups/supplier_portal/

echo ""
read -p "Enter backup timestamp (e.g., 20251026_143000): " TIMESTAMP

BACKUP_DIR="/home/master/backups/supplier_portal"
APP_DIR="/home/master/applications/jcepnzzkmj/public_html/supplier"

# Confirm
read -p "Rollback to $TIMESTAMP? This will OVERWRITE current files! (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Rollback cancelled"
    exit 0
fi

# Backup current state before rollback
echo "Backing up current state..."
./backup.sh

# Restore files
echo "Restoring files from backup..."
tar -xzf $BACKUP_DIR/files_$TIMESTAMP.tar.gz -C $(dirname $APP_DIR)

# Restore database (optional - usually NOT needed)
read -p "Restore database too? (yes/no): " RESTORE_DB
if [ "$RESTORE_DB" = "yes" ]; then
    echo "Restoring database..."
    mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < $BACKUP_DIR/database_$TIMESTAMP.sql
fi

echo "✅ Rollback complete"
echo "⚠️  Check logs and test functionality"
```

---

## Zero-Downtime Deployment (Advanced)

### Using Symlink Strategy

```bash
#!/bin/bash
# zero-downtime-deploy.sh

BASE_DIR="/home/master/applications/jcepnzzkmj/public_html"
RELEASES_DIR="$BASE_DIR/releases"
CURRENT_LINK="$BASE_DIR/supplier"
NEW_RELEASE="$RELEASES_DIR/$(date +%Y%m%d_%H%M%S)"

mkdir -p $RELEASES_DIR

# Deploy to new release directory
echo "Deploying to $NEW_RELEASE..."
mkdir -p $NEW_RELEASE
cp -r ./* $NEW_RELEASE/

# Link shared resources (config, logs, uploads)
ln -s $BASE_DIR/shared/config.php $NEW_RELEASE/config.php
ln -s $BASE_DIR/shared/logs $NEW_RELEASE/logs
ln -s $BASE_DIR/shared/uploads $NEW_RELEASE/uploads

# Test new release
echo "Testing new release..."
for file in $(find $NEW_RELEASE -name "*.php"); do
    php -l "$file" > /dev/null 2>&1 || {
        echo "❌ Syntax error in $file"
        rm -rf $NEW_RELEASE
        exit 1
    }
done

# Atomic switch
echo "Switching symlink..."
ln -sfn $NEW_RELEASE $CURRENT_LINK

echo "✅ Zero-downtime deployment complete"

# Keep only last 5 releases
cd $RELEASES_DIR
ls -t | tail -n +6 | xargs -r rm -rf
```

---

## Database Migrations

### Migration Script Template

Create `migrations/YYYYMMDD_HHMMSS_description.php`:

```php
<?php
/**
 * Migration: Add tracking_carrier column
 * Date: 2025-10-26
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = pdo();

try {
    echo "Running migration...\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // UP migration
    $pdo->exec("
        ALTER TABLE vend_consignments 
        ADD COLUMN tracking_carrier VARCHAR(100) DEFAULT NULL 
        AFTER tracking_number
    ");
    
    echo "✅ Added tracking_carrier column\n";
    
    // Commit
    $pdo->commit();
    
    echo "✅ Migration complete\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

### Migration Runner

Create `migrations/run-pending.php`:

```php
<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$migrationsDir = __DIR__;
$appliedFile = $migrationsDir . '/.applied_migrations';

// Load applied migrations
$applied = file_exists($appliedFile) ? file($appliedFile, FILE_IGNORE_NEW_LINES) : [];

// Find pending migrations
$pending = [];
foreach (glob($migrationsDir . '/*.php') as $file) {
    $filename = basename($file);
    if ($filename === 'run-pending.php') continue;
    
    if (!in_array($filename, $applied)) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    echo "No pending migrations\n";
    exit(0);
}

echo "Found " . count($pending) . " pending migration(s)\n";

foreach ($pending as $migration) {
    $filename = basename($migration);
    echo "\nRunning: $filename\n";
    echo str_repeat('-', 50) . "\n";
    
    ob_start();
    include $migration;
    $output = ob_get_clean();
    echo $output;
    
    // Mark as applied
    file_put_contents($appliedFile, $filename . "\n", FILE_APPEND);
}

echo "\n✅ All migrations complete\n";
```

---

## Post-Deployment Verification

### Automated Verification Script

```bash
#!/bin/bash
# verify-deployment.sh

echo "Verifying deployment..."

ERRORS=0

# 1. Check critical files exist
FILES=(
    "bootstrap.php"
    "config.php"
    "index.php"
    "api/endpoint.php"
    "lib/Auth.php"
    "lib/DatabasePDO.php"
)

for file in "${FILES[@]}"; do
    if [ -f "/home/master/applications/jcepnzzkmj/public_html/supplier/$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
        ((ERRORS++))
    fi
done

# 2. Test database connection
php -r "
require '/home/master/applications/jcepnzzkmj/public_html/supplier/bootstrap.php';
try {
    \$pdo = pdo();
    echo '✅ Database connection OK\n';
} catch (Exception \$e) {
    echo '❌ Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
" || ((ERRORS++))

# 3. Check for PHP errors in last 10 minutes
RECENT_ERRORS=$(find /home/master/applications/jcepnzzkmj/logs -name "*.error.log" -mmin -10 -exec grep -c "Fatal\|Parse error" {} + 2>/dev/null | awk '{s+=$1} END {print s}')
if [ "${RECENT_ERRORS:-0}" -eq 0 ]; then
    echo "✅ No recent PHP errors"
else
    echo "⚠️  Found $RECENT_ERRORS recent error(s)"
fi

# 4. Test homepage loads
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://staff.vapeshed.co.nz/supplier/)
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo "✅ Homepage loads (HTTP $HTTP_CODE)"
else
    echo "❌ Homepage failed (HTTP $HTTP_CODE)"
    ((ERRORS++))
fi

# Summary
echo ""
if [ $ERRORS -eq 0 ]; then
    echo "✅ Deployment verified successfully"
    exit 0
else
    echo "❌ Deployment verification failed with $ERRORS error(s)"
    exit 1
fi
```

---

## Deployment Workflow Summary

```
┌─────────────────┐
│ 1. Backup       │ ./backup.sh
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ 2. Deploy Code  │ ./deploy.sh
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ 3. Migrate DB   │ php migrations/run-pending.php
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ 4. Clear Cache  │ rm -rf cache/*
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ 5. Smoke Test   │ ./smoke-test.sh
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ 6. Verify       │ ./verify-deployment.sh
└────────┬────────┘
         │
         ├─ SUCCESS ──► Done ✅
         │
         └─ FAILURE ──► ./rollback.sh ❌
```

---

## Hotfix Procedure

For urgent production fixes:

```bash
# 1. Create hotfix branch
git checkout -b hotfix/critical-auth-bug

# 2. Make minimal fix
# Edit only affected files

# 3. Test locally
php -l affected-file.php

# 4. Backup production
./backup.sh

# 5. Deploy ONLY affected files
scp affected-file.php user@server:/path/to/supplier/

# 6. Verify fix immediately
./smoke-test.sh

# 7. Monitor logs for 15 minutes
tail -f logs/apache_*.error.log
```

---

## Monitoring After Deployment

### First 15 Minutes

```bash
# Watch error logs
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log | grep -i "error\|fatal\|warning"

# Monitor HTTP status codes
watch -n 5 'curl -s -o /dev/null -w "Homepage: %{http_code}\n" https://staff.vapeshed.co.nz/supplier/'
```

### First Hour

- Check error log count: should be zero
- Verify user activity log shows normal logins
- Test critical flows (login, view orders, update tracking)

### First Day

- Review all API response times
- Check for any unusual patterns in logs
- Verify no user-reported issues

---

## Deployment Schedule

**Recommended deployment windows:**
- Monday-Thursday: 9am-12pm NZST (low traffic)
- NEVER: Friday afternoon, weekends, holidays
- Emergency hotfixes: Anytime, with manager approval

---

## Next Steps

- **Troubleshooting:** [08-TROUBLESHOOTING.md](08-TROUBLESHOOTING.md)
- **Code Snippets:** [09-CODE-SNIPPETS.md](09-CODE-SNIPPETS.md)

---

**Last Updated:** 2025-10-26  
**Related:** [06-TESTING-GUIDE.md](06-TESTING-GUIDE.md)
