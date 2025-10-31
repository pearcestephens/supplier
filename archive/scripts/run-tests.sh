#!/bin/bash
# Quick test runner script

echo "Running Bootstrap & PDO Validation Test..."
echo ""

cd /home/master/applications/jcepnzzkmj/public_html/supplier

# Run the validation test
php tests/bootstrap-pdo-validation.php

exit $?
