#!/bin/bash

# Moniq Payment Gateway Uninstaller for FleetCart
# Usage: ./uninstall.sh /path/to/fleetcart /path/to/backup

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ -z "$1" ] || [ -z "$2" ]; then
    echo -e "${RED}Error: Please provide FleetCart path and backup directory${NC}"
    echo "Usage: ./uninstall.sh /path/to/fleetcart /path/to/backup"
    echo ""
    echo "Example: ./uninstall.sh /var/www/html/fleetcart /var/www/html/fleetcart/storage/moniq-backup-20240101_120000"
    exit 1
fi

FLEETCART_PATH="$1"
BACKUP_DIR="$2"

if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${RED}Error: Backup directory not found: $BACKUP_DIR${NC}"
    exit 1
fi

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}  Moniq Payment Gateway Uninstaller${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""

echo -e "${YELLOW}Step 1: Removing Moniq files...${NC}"

rm -f "$FLEETCART_PATH/modules/Payment/Libraries/Moniq/MoniqService.php"
rm -rf "$FLEETCART_PATH/modules/Payment/Libraries/Moniq"
rm -f "$FLEETCART_PATH/modules/Payment/Gateways/Moniq.php"
rm -f "$FLEETCART_PATH/modules/Payment/Responses/MoniqResponse.php"
rm -f "$FLEETCART_PATH/modules/Payment/Http/Controllers/MoniqWebhookController.php"
rm -f "$FLEETCART_PATH/modules/Setting/Resources/views/admin/settings/tabs/moniq.blade.php"
echo -e "  Moniq files removed"

echo ""
echo -e "${YELLOW}Step 2: Restoring original files from backup...${NC}"

restore_file() {
    local file="$1"
    if [ -f "$BACKUP_DIR/$file" ]; then
        cp "$BACKUP_DIR/$file" "$FLEETCART_PATH/$file"
        echo -e "  Restored: $file"
    else
        echo -e "  ${YELLOW}Warning: Backup not found for $file${NC}"
    fi
}

restore_file "modules/Payment/Providers/PaymentServiceProvider.php"
restore_file "modules/Payment/Routes/public.php"
restore_file "modules/Checkout/Http/Controllers/CheckoutCompleteController.php"
restore_file "modules/Setting/Admin/SettingTabs.php"
restore_file "modules/Setting/Resources/lang/en/attributes.php"
restore_file "modules/Setting/Resources/lang/en/settings.php"
restore_file "modules/Setting/Http/Requests/UpdateSettingRequest.php"

echo ""
echo -e "${YELLOW}Step 3: Clearing cache...${NC}"

cd "$FLEETCART_PATH"
php artisan cache:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
echo -e "  Cache cleared"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Uninstall Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Moniq payment gateway has been removed."
echo "Original files have been restored from backup."
