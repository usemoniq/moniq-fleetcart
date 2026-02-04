#!/bin/bash

# Moniq Payment Gateway Installer for FleetCart
# Usage: ./install.sh /path/to/fleetcart

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if FleetCart path is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Please provide the path to your FleetCart installation${NC}"
    echo "Usage: ./install.sh /path/to/fleetcart"
    exit 1
fi

FLEETCART_PATH="$1"

# Verify FleetCart installation
if [ ! -f "$FLEETCART_PATH/artisan" ]; then
    echo -e "${RED}Error: FleetCart installation not found at $FLEETCART_PATH${NC}"
    echo "Please ensure the path points to your FleetCart root directory"
    exit 1
fi

if [ ! -d "$FLEETCART_PATH/modules" ]; then
    echo -e "${RED}Error: modules directory not found${NC}"
    exit 1
fi

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Moniq Payment Gateway Installer${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "FleetCart path: ${YELLOW}$FLEETCART_PATH${NC}"
echo ""

# Create backup directory
BACKUP_DIR="$FLEETCART_PATH/storage/moniq-backup-$(date +%Y%m%d_%H%M%S)"
echo -e "${YELLOW}Creating backup directory: $BACKUP_DIR${NC}"
mkdir -p "$BACKUP_DIR"

# Function to backup a file if it exists
backup_file() {
    local file="$1"
    if [ -f "$FLEETCART_PATH/$file" ]; then
        local backup_path="$BACKUP_DIR/$file"
        mkdir -p "$(dirname "$backup_path")"
        cp "$FLEETCART_PATH/$file" "$backup_path"
        echo -e "  Backed up: $file"
    fi
}

# Function to copy a file
copy_file() {
    local file="$1"
    local dest_dir="$(dirname "$FLEETCART_PATH/$file")"
    mkdir -p "$dest_dir"
    cp "$SCRIPT_DIR/$file" "$FLEETCART_PATH/$file"
    echo -e "  Installed: $file"
}

echo ""
echo -e "${YELLOW}Step 1: Backing up existing files...${NC}"

# Backup modified files
backup_file "modules/Payment/Providers/PaymentServiceProvider.php"
backup_file "modules/Payment/Routes/public.php"
backup_file "modules/Checkout/Http/Controllers/CheckoutCompleteController.php"
backup_file "modules/Setting/Admin/SettingTabs.php"
backup_file "modules/Setting/Resources/lang/en/attributes.php"
backup_file "modules/Setting/Resources/lang/en/settings.php"
backup_file "modules/Setting/Http/Requests/UpdateSettingRequest.php"
backup_file "modules/Setting/Resources/assets/admin/js/main.js"

echo ""
echo -e "${YELLOW}Step 2: Creating directories...${NC}"

mkdir -p "$FLEETCART_PATH/modules/Payment/Libraries/Moniq"
mkdir -p "$FLEETCART_PATH/modules/Payment/Gateways"
mkdir -p "$FLEETCART_PATH/modules/Payment/Responses"
mkdir -p "$FLEETCART_PATH/modules/Payment/Http/Controllers"
mkdir -p "$FLEETCART_PATH/modules/Setting/Resources/assets/admin/js"
echo -e "  Directories created"

echo ""
echo -e "${YELLOW}Step 3: Installing new files...${NC}"

# Copy new files
copy_file "modules/Payment/Libraries/Moniq/MoniqService.php"
copy_file "modules/Payment/Gateways/Moniq.php"
copy_file "modules/Payment/Responses/MoniqResponse.php"
copy_file "modules/Payment/Http/Controllers/MoniqWebhookController.php"
copy_file "modules/Setting/Resources/views/admin/settings/tabs/moniq.blade.php"

echo ""
echo -e "${YELLOW}Step 4: Installing modified files...${NC}"

# Copy modified files
copy_file "modules/Payment/Providers/PaymentServiceProvider.php"
copy_file "modules/Payment/Routes/public.php"
copy_file "modules/Checkout/Http/Controllers/CheckoutCompleteController.php"
copy_file "modules/Setting/Admin/SettingTabs.php"
copy_file "modules/Setting/Resources/lang/en/attributes.php"
copy_file "modules/Setting/Resources/lang/en/settings.php"
copy_file "modules/Setting/Http/Requests/UpdateSettingRequest.php"
copy_file "modules/Setting/Resources/assets/admin/js/main.js"

echo ""
echo -e "${YELLOW}Step 5: Rebuilding assets...${NC}"
if command -v yarn &> /dev/null || command -v npm &> /dev/null; then
    cd "$FLEETCART_PATH"
    if [ -f "package.json" ]; then
        if command -v yarn &> /dev/null; then
            yarn build 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not rebuild assets with yarn${NC}"
        else
            npm run build 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not rebuild assets with npm${NC}"
        fi
        echo -e "  Assets rebuilt"
    fi
else
    echo -e "  ${YELLOW}Warning: yarn/npm not found. Please rebuild assets manually:${NC}"
    echo "  cd $FLEETCART_PATH && yarn build"
fi

echo ""
echo -e "${YELLOW}Step 6: Clearing cache...${NC}"

cd "$FLEETCART_PATH"

if command -v php &> /dev/null; then
    php artisan cache:clear 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not clear cache${NC}"
    php artisan config:clear 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not clear config${NC}"
    php artisan view:clear 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not clear views${NC}"
    php artisan route:clear 2>/dev/null || echo -e "  ${YELLOW}Warning: Could not clear routes${NC}"
    echo -e "  Cache cleared"
else
    echo -e "  ${YELLOW}Warning: PHP not found in PATH. Please clear cache manually:${NC}"
    echo "  php artisan cache:clear"
    echo "  php artisan config:clear"
    echo "  php artisan view:clear"
    echo "  php artisan route:clear"
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Backup location: ${YELLOW}$BACKUP_DIR${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Log in to FleetCart admin panel"
echo "2. Go to Settings → Payment Methods → Moniq"
echo "3. Enable the gateway and enter your API credentials"
echo "4. Save settings"
echo ""
echo -e "Webhook URL: ${GREEN}https://your-domain.com/moniq/webhook${NC}"
echo ""
echo -e "For support, visit: ${GREEN}https://github.com/usemoniq/moniq-fleetcart${NC}"
