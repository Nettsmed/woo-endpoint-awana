# Awana Digital Sync

WordPress plugin that syncs invoices from Digital/CRM (Firebase) to WooCommerce as guest orders and handles POG/Integrera sync updates.

## Installation

1. Copy the `awana-digital-sync` folder to `wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Add the API key to `wp-config.php`:

```php
define( 'AWANA_DIGITAL_API_KEY', 'your-secret-api-key-here' );
```

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## API Endpoints

### 1. Create/Update Invoice

**POST** `/wp-json/awana/v1/invoice`

**Headers:**
```
X-CRM-API-Key: your-api-key
Content-Type: application/json
```

**Request Body:**
```json
{
  "invoiceId": "firebaseRecordName",
  "invoiceNumber": "321665",
  "status": "unpaid",
  "type": "membership_fee",
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "memberName": "Kristkirken søndagsskole Bergen",
  "organizationId": "kristkirken-i-bergen",
  "organizationName": null,
  "pogCustomerNumber": null,
  "email": "vigdis@kristkirken.no",
  "countryId": "no",
  "currency": "NOK",
  "amount": 1750,
  "total": 1750,
  "totalTax": 19,
  "invoiceDate": "2025-12-02T00:00:00.000Z",
  "dueDate": "2025-12-12T00:00:00.000Z",
  "method": "invoice",
  "source": "awana-crm",
  "syncStatus": {
    "woo": "pending"
  },
  "invoiceLines": [
    {
      "productId": 3102,
      "quantity": 1,
      "description": "Medlemskontingent 2025 - lisens undervisningsbøker (oppgradering)",
      "vatRate": 0,
      "vatCode": "fritatt"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "wooOrderId": 1234,
  "wooOrderNumber": "1234",
  "wooStatus": "pending",
  "digitalInvoiceId": "firebaseRecordName",
  "message": "Order created/updated from digital invoice"
}
```

### 2. Sync Invoice (POG/Integrera)

**POST** `/wp-json/awana/v1/invoice-sync`

**Headers:**
```
X-CRM-API-Key: your-api-key
Content-Type: application/json
```

**Request Body (Update POG Customer Number):**
```json
{
  "invoiceId": "firebaseRecordName",
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "pogCustomerNumber": 123456,
  "updatePogCustomerNumber": true
}
```

**Request Body (Update Payment Status):**
```json
{
  "invoiceId": "firebaseRecordName",
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "pogCustomerNumber": 123456,
  "status": "paid",
  "amountPaid": 1750,
  "updateInvoiceStatus": true
}
```

**Response:**
```json
{
  "success": true,
  "wooOrderId": 1234,
  "updated": {
    "pogCustomerNumber": true,
    "status": true
  }
}
```

## Status Mapping

Digital status → WooCommerce status:
- `draft` → `pending`
- `unpaid` → `on-hold`
- `paid` → `completed`
- `cancelled` → `cancelled`
- `refunded` → `refunded`

## Order Meta Fields

The plugin stores the following meta fields on orders:

### Digital Fields (prefix: `_digital_`)
- `_digital_invoice_id` - Unique invoice ID from Digital system
- `_digital_invoice_number` - Human-readable invoice number
- `_digital_member_id` - Member ID
- `_digital_member_name` - Member name
- `_digital_organization_id` - Organization ID
- `_digital_organization_name` - Organization name
- `_digital_type` - Invoice type (e.g., membership_fee)
- `_digital_source` - Source system (e.g., awana-crm)
- `_digital_sync_woo` - Sync status (pending/synced)
- `_digital_invoice_date` - Invoice date
- `_digital_due_date` - Due date

### POG Fields (prefix: `_pog_`)
- `_pog_customer_number` - POG customer number
- `_pog_last_sync_at` - Last sync timestamp
- `_paid_via_pog` - Boolean indicating payment via POG
- `_amount_paid` - Amount paid
- `_paid_at` - Payment timestamp

## Action Hooks

The plugin triggers the following action hooks:

- `awana_digital_invoice_created` - Fired when an invoice is created/updated
  - Parameters: `$order` (WC_Order), `$data` (array)
  
- `awana_digital_invoice_synced` - Fired when an invoice is synced from POG
  - Parameters: `$order` (WC_Order), `$data` (array)

## Logging

All plugin activity is logged using WooCommerce's logger. Logs can be viewed in:
**WooCommerce → Status → Logs** (select "awana_digital" from the dropdown)

## Product Mapping

The plugin attempts to find products in the following order:
1. By WooCommerce product ID (if `productId` matches)
2. By SKU (if `productId` matches a product SKU)

If a product is not found, the line item is skipped and a warning is logged.

**Pricing:** Product prices are always taken from WooCommerce. The `unitPrice` field is not used - prices are automatically calculated from the WooCommerce product's current price.

## Notes

- All orders are created as **guest orders** (no WordPress user account)
- The plugin uses `invoiceId` as the unique identifier for finding existing orders
- If an order with the same `invoiceId` exists, it will be updated (line items are replaced)
- Currency defaults to NOK if not specified
- Country codes are automatically converted to uppercase (e.g., "no" → "NO")

## Security

- API key authentication is required for all endpoints
- API key must be defined in `wp-config.php` (not in the plugin file)
- Use different API keys for staging and production environments

