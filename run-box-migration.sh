#!/bin/bash
# Run shipment boxes migration

echo "Running shipment_boxes migration..."

mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < /home/master/applications/jcepnzzkmj/public_html/supplier/migrations/006_shipment_boxes.sql

if [ $? -eq 0 ]; then
    echo "✓ Migration completed successfully!"
    echo ""
    echo "Tables created:"
    echo "  - shipment_boxes"
    echo "  - shipment_box_items"
    echo ""
    echo "Box/parcel tracking system is now ready to use!"
else
    echo "✗ Migration failed. Check error messages above."
    exit 1
fi
