# Integration Flow Documentation

This document outlines the complete integration flow and what's covered by the plugin.

## ✅ Covered by Plugin

### 1. CRM Creates Invoice → WooCommerce

**Flow:**
- CRM creates invoice as **draft**
- When published, Firebase function calls: `POST /wp-json/awana/v1/invoice`
- Plugin authenticates via `X-CRM-API-Key` header
- Plugin is **idempotent** by `invoiceId`:
  - If no order with `_digital_invoice_id = invoiceId`: **creates** new guest order
  - If exists: **updates** order (status, lines, totals)

**Status:** ✅ Fully implemented

### 2. WooCommerce Creates Guest Order

**Flow:**
- WooCommerce creates a **guest order** (no WP user account)
- Stores rich meta on the order:
  - `_digital_invoice_id` / `_crm_invoice_id` (aliases)
  - `_digital_member_id` / `_crm_member_id` (aliases)
  - `_digital_organization_id` / `_crm_organization_id` (aliases)
  - `_pog_customer_number` / `_pog_customer_id` (aliases, if already known)

**Status:** ✅ Fully implemented

### 3. Integrera Sends Order to POG

**Flow:**
- Integrera reads order from WooCommerce
- If `_pog_customer_number` exists: links to existing POG customer
- If `_pog_customer_number` is missing: POG creates new customer
- Integrera calls: `POST /wp-json/awana/v1/invoice-sync` with `updatePogCustomerNumber: true`
- Plugin updates `_pog_customer_number` on order
- **Plugin automatically sends webhook to CRM** with POG customer number

**Status:** ✅ Fully implemented (webhook added)

### 4. When Invoice is Paid

**Flow:**
- Integrera reads payment status from POG
- Integrera calls: `POST /wp-json/awana/v1/invoice-sync` with `updateInvoiceStatus: true, status: "paid"`
- Plugin marks order as `completed` in WooCommerce
- **Plugin automatically sends webhook to CRM** with payment information

**Status:** ✅ Fully implemented (webhook added)

## Configuration Required

### 1. API Key for Incoming Requests

Add to `wp-config.php`:
```php
define( 'AWANA_DIGITAL_API_KEY', 'your-secret-api-key-here' );
```

### 2. CRM Webhook URL (for outgoing notifications)

**Option A: Define in wp-config.php (recommended)**
```php
define( 'AWANA_CRM_WEBHOOK_URL', 'https://your-crm-endpoint.com/webhook' );
define( 'AWANA_CRM_WEBHOOK_API_KEY', 'optional-api-key-for-crm' ); // Optional
```

**Option B: Store in WordPress options** (can be configured via admin UI in future)

### 3. Firebase Function Configuration

Update your Firebase function to call:
- **Invoice creation:** `POST https://your-wp-site.com/wp-json/awana/v1/invoice`
- **Headers:** `X-CRM-API-Key: your-api-key`

### 4. Integrera Configuration

Configure Integrera to call:
- **POG customer sync:** `POST https://your-wp-site.com/wp-json/awana/v1/invoice-sync`
- **Payment status sync:** `POST https://your-wp-site.com/wp-json/awana/v1/invoice-sync`
- **Headers:** `X-CRM-API-Key: your-api-key`

## Webhook Payloads

### POG Customer Created Webhook

When a new POG customer is created, the plugin sends:

```json
POST {AWANA_CRM_WEBHOOK_URL}
{
  "invoiceId": "firebaseRecordName",
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "pogCustomerNumber": 123456,
  "event": "pog_customer_created",
  "timestamp": "2025-01-15 10:30:00"
}
```

### Invoice Paid Webhook

When an invoice is paid, the plugin sends:

```json
POST {AWANA_CRM_WEBHOOK_URL}
{
  "invoiceId": "firebaseRecordName",
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "status": "paid",
  "amountPaid": 1750,
  "paidAt": "2025-01-15 10:30:00",
  "event": "invoice_paid",
  "timestamp": "2025-01-15 10:30:00"
}
```

## Meta Field Naming

The plugin stores meta fields with both naming conventions for compatibility:

- `_digital_invoice_id` = `_crm_invoice_id` (same value)
- `_digital_member_id` = `_crm_member_id` (same value)
- `_digital_organization_id` = `_crm_organization_id` (same value)
- `_pog_customer_number` = `_pog_customer_id` (same value)

## Action Hooks

The plugin triggers these hooks that can be used for additional integrations:

- `awana_digital_invoice_created` - Fired when invoice is created/updated
  - Parameters: `$order` (WC_Order), `$data` (array)
  
- `awana_digital_invoice_synced` - Fired when invoice is synced from POG
  - Parameters: `$order` (WC_Order), `$data` (array)

## Summary

✅ **All 4 steps are fully covered:**
1. ✅ CRM → WooCommerce (invoice creation)
2. ✅ WooCommerce guest order creation
3. ✅ POG customer sync + CRM notification
4. ✅ Payment status sync + CRM notification

The plugin handles the complete bidirectional flow between CRM, WooCommerce, POG, and Integrera.

