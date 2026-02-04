# Moniq Payment Gateway for FleetCart

Integrate Moniq payment gateway into your FleetCart e-commerce store. Customers are redirected to Moniq's secure hosted checkout page to complete their purchase.

## Requirements

- FleetCart CMS v4.7.11 or later
- PHP 8.2 or higher
- Valid Moniq merchant account
- Moniq API credentials (Public Key and API Secret)

## Features

- Hosted checkout (redirect to Moniq's secure payment page)
- Webhook support for reliable payment confirmation
- Customer address tracking
- JWT token caching for optimized API calls
- Debug logging support

## Installation

### Option 1: Automated Installation (Recommended)

1. Download or clone this repository
2. Upload to your server
3. Run the installation script:

```bash
chmod +x install.sh
./install.sh /path/to/your/fleetcart
```

For example:
```bash
./install.sh /var/www/html/fleetcart
```

### Option 2: Manual Installation

#### Step 1: Copy New Files

Copy these new files to your FleetCart installation:

| Source | Destination |
|--------|-------------|
| `modules/Payment/Libraries/Moniq/MoniqService.php` | `modules/Payment/Libraries/Moniq/MoniqService.php` |
| `modules/Payment/Gateways/Moniq.php` | `modules/Payment/Gateways/Moniq.php` |
| `modules/Payment/Responses/MoniqResponse.php` | `modules/Payment/Responses/MoniqResponse.php` |
| `modules/Payment/Http/Controllers/MoniqWebhookController.php` | `modules/Payment/Http/Controllers/MoniqWebhookController.php` |
| `modules/Setting/Resources/views/admin/settings/tabs/moniq.blade.php` | `modules/Setting/Resources/views/admin/settings/tabs/moniq.blade.php` |
| `modules/Setting/Resources/assets/admin/js/main.js` | `modules/Setting/Resources/assets/admin/js/main.js` |

#### Step 2: Replace Modified Files

**Important:** Back up your existing files before replacing them.

| Source | Destination |
|--------|-------------|
| `modules/Payment/Providers/PaymentServiceProvider.php` | `modules/Payment/Providers/PaymentServiceProvider.php` |
| `modules/Payment/Routes/public.php` | `modules/Payment/Routes/public.php` |
| `modules/Checkout/Http/Controllers/CheckoutCompleteController.php` | `modules/Checkout/Http/Controllers/CheckoutCompleteController.php` |
| `modules/Setting/Admin/SettingTabs.php` | `modules/Setting/Admin/SettingTabs.php` |
| `modules/Setting/Resources/lang/en/attributes.php` | `modules/Setting/Resources/lang/en/attributes.php` |
| `modules/Setting/Resources/lang/en/settings.php` | `modules/Setting/Resources/lang/en/settings.php` |
| `modules/Setting/Http/Requests/UpdateSettingRequest.php` | `modules/Setting/Http/Requests/UpdateSettingRequest.php` |
| `modules/Setting/Resources/assets/admin/js/main.js` | `modules/Setting/Resources/assets/admin/js/main.js` |

#### Step 3: Rebuild Assets

Since the JavaScript file is modified, you need to rebuild assets:

```bash
cd /path/to/fleetcart
yarn build
# or: npm run build
```

#### Step 4: Clear Cache

Clear the Laravel cache:

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

## Configuration

1. Log in to FleetCart admin panel
2. Navigate to **Settings** → **Payment Methods** → **Moniq**
3. Enable the payment gateway
4. Enter your credentials:
   - **Public Key**: Your Moniq merchant public key
   - **API Secret**: Your Moniq merchant API secret
5. Customize the label and description (optional)
6. Click **Save**

## Webhook URL

Your webhook URL is:
```
https://your-domain.com/moniq/webhook
```

Configure this URL in your Moniq dashboard for server-to-server payment notifications.

## File Structure

```
modules/
├── Payment/
│   ├── Gateways/
│   │   └── Moniq.php                    # Gateway implementation
│   ├── Http/
│   │   └── Controllers/
│   │       └── MoniqWebhookController.php   # Webhook handler
│   ├── Libraries/
│   │   └── Moniq/
│   │       └── MoniqService.php         # API service class
│   ├── Providers/
│   │   └── PaymentServiceProvider.php   # Gateway registration (modified)
│   ├── Responses/
│   │   └── MoniqResponse.php            # Response handler
│   └── Routes/
│       └── public.php                   # Webhook route (modified)
├── Checkout/
│   └── Http/
│       └── Controllers/
│           └── CheckoutCompleteController.php  # Payment verification (modified)
└── Setting/
    ├── Admin/
    │   └── SettingTabs.php              # Admin tab registration (modified)
    ├── Http/
    │   └── Requests/
    │       └── UpdateSettingRequest.php # Validation rules (modified)
    └── Resources/
        ├── assets/
        │   └── admin/
        │       └── js/
        │           └── main.js          # JS toggle handler (modified)
        ├── lang/
        │   └── en/
        │       ├── attributes.php       # Field labels (modified)
        │       └── settings.php         # Tab translations (modified)
        └── views/
            └── admin/
                └── settings/
                    └── tabs/
                        └── moniq.blade.php  # Settings form (new)
```

## Troubleshooting

### "Failed to obtain Moniq token"
- Verify your Public Key and API Secret are correct
- Check that your server can reach `https://em-api-prod.everydaymoney.app`

### Payment not completing
- Check Laravel logs: `storage/logs/laravel.log`
- Verify the webhook URL is accessible from the internet
- Ensure SSL certificate is valid

### Clear token cache
If you change API credentials, clear the cached token:

```bash
php artisan tinker
>>> Cache::forget('moniq_jwt_token');
```

## Documentation

See [docs/moniq-payment-gateway.md](docs/moniq-payment-gateway.md) for detailed technical documentation.

## Support

For issues with this integration, please open an issue on GitHub.

For Moniq API issues, contact Moniq support.

## License

GPL-2.0+
