# Moniq Payment Gateway Integration

This document describes the Moniq payment gateway integration for FleetCart CMS.

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Technical Architecture](#technical-architecture)
- [API Reference](#api-reference)
- [File Structure](#file-structure)
- [Troubleshooting](#troubleshooting)

---

## Overview

Moniq is a payment gateway powered by the Everydaymoney API. This integration allows FleetCart stores to accept payments through Moniq's hosted checkout page, providing a secure and seamless payment experience for customers.

### Key Features

- **Hosted Checkout**: Customers are redirected to Moniq's secure checkout page
- **JWT Authentication**: Secure two-tier authentication system
- **Token Caching**: Optimized API calls with automatic token caching
- **Automatic Verification**: Payment status verified before order completion
- **Multi-currency Support**: Uses the order's currency at checkout time
- **Webhook Support**: Server-to-server notifications for reliable payment confirmation
- **Customer Tracking**: Customers are tracked by email for consistent identification
- **Address Support**: Customer billing address is stored in Moniq for future reference

---

## Requirements

- FleetCart CMS v4.7.11 or later
- PHP 8.2 or higher
- Valid Moniq merchant account
- Moniq API credentials (Public Key and API Secret)

---

## Configuration

### Step 1: Access Payment Settings

1. Log in to the FleetCart admin panel
2. Navigate to **Settings** in the sidebar
3. Click on the **Payment Methods** tab group
4. Select the **Moniq** tab

### Step 2: Enable and Configure

| Field | Description |
|-------|-------------|
| **Status** | Toggle to enable/disable Moniq payments |
| **Label** | Display name shown to customers at checkout (e.g., "Pay with Moniq") |
| **Description** | Payment method description shown at checkout |
| **Public Key** | Your Moniq merchant public key |
| **API Secret** | Your Moniq merchant API secret |

### Step 3: Save Settings

Click **Save** to apply your configuration. The Moniq payment option will now appear at checkout when enabled.

---

## How It Works

### Customer Payment Flow

```
┌─────────────────┐
│  1. Checkout    │
│  Select Moniq   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  2. Create      │
│  Charge (API)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  3. Redirect to │
│  Moniq Checkout │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  4. Customer    │
│  Completes Pay  │
└────────┬────────┘
         │
         ├─────────────────────────────┐
         ▼                             ▼
┌─────────────────┐           ┌─────────────────┐
│  5a. Redirect   │           │  5b. Webhook    │
│  Back to Store  │           │  (Server-to-    │
│  (Customer)     │           │   Server)       │
└────────┬────────┘           └────────┬────────┘
         │                             │
         ▼                             ▼
┌─────────────────┐           ┌─────────────────┐
│  6a. Verify     │           │  6b. Verify     │
│  Payment (API)  │           │  Payment (API)  │
└────────┬────────┘           └────────┬────────┘
         │                             │
         ▼                             ▼
┌─────────────────┐           ┌─────────────────┐
│  7. Complete    │           │  7. Complete    │
│  Order          │           │  Order          │
└─────────────────┘           └─────────────────┘

    Primary Path               Backup Path
  (Customer Redirect)      (Webhook - Reliable)
```

### Dual Verification System

The integration uses **two paths** to ensure no payments are missed:

1. **Customer Redirect (Primary)**: Customer is redirected back after payment
2. **Webhook (Backup)**: Server-to-server notification from Moniq

If the customer's redirect fails (network issues, browser closed), the webhook ensures the order is still completed.

### Detailed Flow

1. **Checkout Initiation**
   - Customer selects Moniq as payment method
   - Clicks "Place Order" button

2. **Charge Creation**
   - System authenticates with Moniq API (obtains JWT token)
   - Creates a charge with order details (items, customer info, amounts)
   - Receives checkout URL from Moniq

3. **Customer Redirect**
   - Customer is redirected to Moniq's hosted checkout page
   - Moniq order ID is stored in the order's notes field

4. **Payment Completion**
   - Customer completes payment on Moniq's secure page
   - Moniq redirects customer back to store's callback URL

5. **Payment Verification**
   - Store verifies payment status via Moniq API
   - Checks if charge status is "completed", "paid", "successful", or "succeeded"

6. **Order Completion**
   - If verified, order is marked as complete
   - Customer sees order confirmation page
   - If failed, customer is redirected to payment canceled page

---

## Technical Architecture

### Authentication System

Moniq uses a two-tier JWT authentication system:

```
┌─────────────────────────────────────────────────────────┐
│                    Tier 1: Token Request                │
├─────────────────────────────────────────────────────────┤
│  POST /auth/business/token                              │
│                                                         │
│  Headers:                                               │
│    Content-Type: application/json                       │
│    X-Api-Key: {public_key}                              │
│    Authorization: Basic {base64(public_key:api_secret)} │
│                                                         │
│  Response:                                              │
│    { "isError": false, "result": "jwt_token_string" }   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                 Tier 2: API Requests                    │
├─────────────────────────────────────────────────────────┤
│  All subsequent API calls                               │
│                                                         │
│  Headers:                                               │
│    Content-Type: application/json                       │
│    Authorization: Bearer {jwt_token}                    │
└─────────────────────────────────────────────────────────┘
```

### Token Caching

To optimize performance and reduce API calls, JWT tokens are cached:

- **Cache Key**: `moniq_jwt_token`
- **Cache Duration**: 3000 seconds (~50 minutes)
- **Token Lifetime**: Tokens are valid for ~60 minutes (cached before expiry)

```php
// Token is automatically cached and refreshed
Cache::put('moniq_jwt_token', $token, 3000);
```

### Order ID Storage

The Moniq order ID is stored in the order's `note` field as JSON:

```json
{
  "moniq_order_id": "order_abc123xyz"
}
```

This allows the system to verify the correct payment when the customer returns.

### Customer Tracking

Customers are tracked consistently using their email address:

- **No `customerKey` sent**: The integration does not send a `customerKey` field
- **Auto-generated by Moniq**: Moniq generates `customerKey` as `email:{customer_email}`
- **Consistent identification**: Same email = same customer, whether guest or logged in

```
Guest checkout:     john@example.com → customerKey: "email:john@example.com"
Logged-in checkout: john@example.com → customerKey: "email:john@example.com"
                                         ↓
                                   Same customer ✓
```

### Customer Address

If the order has a billing address, it's sent to Moniq and stored with the customer record:

| FleetCart Field | Moniq Field |
|-----------------|-------------|
| `billing_address_1` | `customerAddress.line1` |
| `billing_address_2` | `customerAddress.line2` |
| `billing_city` | `customerAddress.city` |
| `billing_state` | `customerAddress.state` |
| `billing_zip` | `customerAddress.postalCode` |
| `billing_country` | `customerAddress.country` |

The address is updated in Moniq each time the customer makes a purchase, keeping it current.

---

## Webhook

### Webhook URL

```
https://beldiva.co.uk/moniq/webhook
```

This URL is automatically sent to Moniq when creating a charge. Moniq will call this URL to notify your store when a payment is completed.

### Webhook Security

The webhook controller **does not trust the webhook data blindly**. It:
1. Extracts the order ID from the webhook payload
2. Calls Moniq API to verify the actual payment status
3. Only completes the order if API confirms payment is successful

This prevents fake webhook attacks.

### Webhook Payload

Moniq sends payment status updates to the webhook URL. The controller looks for the order ID in these locations:
- `metadata.order_id`
- `order.metadata.order_id`
- `referenceKey` (format: `order_123`)
- `order.referenceKey`

---

## API Reference

### Base URL

```
https://em-api-prod.everydaymoney.app
```

### Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/auth/business/token` | POST | Obtain JWT authentication token |
| `/payment/checkout/api-charge-order` | POST | Create a payment charge |
| `/business/order/{order_id}` | GET | Verify payment status |

### Create Charge Request

```json
POST /payment/checkout/api-charge-order
Authorization: Bearer {jwt_token}

{
  "currency": "GBP",
  "email": "customer@example.com",
  "phone": "+441onal234567890",
  "customerName": "John Doe",
  "narration": "Order #123 from Beldiva",
  "transactionRef": "FC-123-1704067200",
  "referenceKey": "order_123",
  "redirectUrl": "https://beldiva.co.uk/checkout/123/complete?paymentMethod=moniq",
  "webhookUrl": "https://beldiva.co.uk/moniq/webhook",
  "orderLines": [
    {
      "itemName": "Red Dress",
      "quantity": 2,
      "amount": 49.99
    },
    {
      "itemName": "Blue Shoes",
      "quantity": 1,
      "amount": 79.99
    }
  ],
  "customerAddress": {
    "line1": "123 Main St",
    "line2": "Apt 4",
    "city": "London",
    "state": "Greater London",
    "postalCode": "SW1A 1AA",
    "country": "GB"
  },
  "metadata": {
    "order_id": "123",
    "store_name": "Beldiva"
  }
}
```

**Note:**
- `customerKey` is not sent; Moniq auto-generates it as `email:{customer_email}` for consistent customer tracking
- `customerAddress` is only included if the order has a billing address
- `orderLines.amount` is the unit price per item (Moniq calculates line totals)

### Create Charge Response

```json
{
  "isError": false,
  "result": {
    "checkoutURL": "https://checkout.everydaymoney.app/pay/abc123",
    "order": {
      "id": "order_abc123xyz",
      "charges": [
        {
          "transactionRef": "FC-123-1704067200",
          "status": "pending"
        }
      ]
    }
  }
}
```

### Verify Payment Response

```json
GET /business/order/{order_id}

{
  "isError": false,
  "result": {
    "id": "order_abc123xyz",
    "charges": [
      {
        "transactionRef": "FC-123-1704067200",
        "status": "completed"
      }
    ]
  }
}
```

### Payment Statuses

| Status | Description | Action |
|--------|-------------|--------|
| `completed` | Payment successful | Complete order |
| `paid` | Payment successful | Complete order |
| `successful` | Payment successful | Complete order |
| `succeeded` | Payment successful | Complete order |
| `pending` | Awaiting payment | Cancel order |
| `failed` | Payment failed | Cancel order |

---

## File Structure

```
modules/
├── Payment/
│   ├── Gateways/
│   │   └── Moniq.php                 # Gateway implementation
│   ├── Http/
│   │   └── Controllers/
│   │       └── MoniqWebhookController.php  # Webhook handler
│   ├── Libraries/
│   │   └── Moniq/
│   │       └── MoniqService.php      # API service class
│   ├── Providers/
│   │   └── PaymentServiceProvider.php # Gateway registration
│   ├── Responses/
│   │   └── MoniqResponse.php         # Response handler
│   └── Routes/
│       └── public.php                # Webhook route
├── Checkout/
│   └── Http/
│       └── Controllers/
│           └── CheckoutCompleteController.php  # Redirect verification
└── Setting/
    ├── Admin/
    │   └── SettingTabs.php           # Admin tab registration
    ├── Http/
    │   └── Requests/
    │       └── UpdateSettingRequest.php  # Validation rules
    └── Resources/
        ├── lang/
        │   └── en/
        │       ├── attributes.php    # Field labels
        │       └── settings.php      # Tab translations
        └── views/
            └── admin/
                └── settings/
                    └── tabs/
                        └── moniq.blade.php  # Settings form
```

### File Descriptions

| File | Purpose |
|------|---------|
| `MoniqService.php` | Handles all Moniq API communication including token management, charge creation, and payment verification |
| `Moniq.php` | Implements `GatewayInterface`, manages purchase flow, redirect URL, and webhook URL generation |
| `MoniqResponse.php` | Formats gateway responses, implements `ShouldRedirect` and `HasTransactionReference` interfaces |
| `MoniqWebhookController.php` | Handles server-to-server webhook from Moniq, verifies payment, completes order |
| `CheckoutCompleteController.php` | Handles customer redirect from Moniq, verifies payment, and completes order |
| `moniq.blade.php` | Admin settings form for configuring Moniq credentials |

---

## Troubleshooting

### Common Issues

#### 1. "Failed to obtain Moniq token"

**Cause**: Invalid API credentials or network issues.

**Solution**:
- Verify Public Key and API Secret are correct
- Check that credentials are for the production environment
- Ensure server can reach `https://em-api-prod.everydaymoney.app`

#### 2. Payment Not Completing / Order Stuck

**Cause**: Payment verification failing or incorrect order ID.

**Solution**:
- Check Laravel logs: `storage/logs/laravel.log`
- Look for "Moniq callback error" messages
- Verify the order's `note` field contains `moniq_order_id`

#### 3. Redirect Loop or Blank Page

**Cause**: Missing or incorrect redirect URL configuration.

**Solution**:
- Ensure your store's URL is correctly configured in `.env`
- Check that HTTPS is properly configured if required
- Verify the checkout route is accessible

#### 4. Currency Mismatch Error

**Cause**: Store currency not supported by Moniq.

**Solution**:
- Check Moniq documentation for supported currencies
- Configure store to use a supported currency

### Debug Mode

To enable detailed logging, add to your `.env`:

```env
LOG_LEVEL=debug
```

Then check `storage/logs/laravel.log` for detailed Moniq-related messages.

### Clearing Token Cache

If you need to force a new token (e.g., after changing credentials):

```bash
php artisan tinker
>>> Cache::forget('moniq_jwt_token');
```

Or clear all cache:

```bash
php artisan cache:clear
```

### Testing Checklist

- [ ] Moniq credentials entered correctly in admin settings
- [ ] Moniq payment method enabled
- [ ] Test product added to cart
- [ ] Moniq appears as payment option at checkout
- [ ] Redirect to Moniq checkout works
- [ ] Payment completion redirects back to store
- [ ] Order marked as complete after successful payment
- [ ] Failed payment redirects to cancellation page

---

## Support

For Moniq API issues, contact Everydaymoney support.

For FleetCart integration issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Browser console for JavaScript errors
- Network tab for failed API requests
