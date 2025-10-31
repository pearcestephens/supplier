#!/bin/bash
# Verify all pages are accessible
echo "Testing all portal pages..."
for page in index orders warranty downloads reports account; do
  status=$(curl -sI https://staff.vapeshed.co.nz/supplier/demo/${page}.html | head -1)
  echo "${page}.html: $status"
done
