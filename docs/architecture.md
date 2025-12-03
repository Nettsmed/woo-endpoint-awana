api key: 

e82d6cde7b414287b63c8b0c2a1e845d 

# A: Overview

This integration connects four systems to manage membership invoices end-to-end:

- The **CRM** is the master system for organizations and invoices. All membership invoices are created and maintained here.
- **WooCommerce** acts as a stateless “order engine” that creates guest orders based on CRM invoices and passes them on for accounting.
- **Integrera** is the integration hub that reads orders from Woo, sends them to PowerOffice Go (POG), and pushes updates back.
- **POG** is the accounting system that issues the actual invoices and tracks payments.

The core idea is:

> CRM decides what should be invoiced, Woo decides how it is represented as an order, and POG decides what is paid and when.
> 

Invoices are created in the CRM, sent to Woo as webhooks, turned into guest orders, forwarded to POG via Integrera, and then kept in sync when customer numbers and payment status change.

# B: Roles of each system

- **CRM**: master for invoices & organizations.
- **WooCommerce**: guest order engine + technical bridge to Integrera/POG.
- **Integrera**: integration hub; sends orders to POG and reads back status.
- **POG**: accounting, invoices, payments.

# B: Data flow

The following plan determines the flow of the plugin. 

## 1: CRM creates invoice

- CRM creates invoice as **draft**.
- When you **publish** (or similar), Firebase function calls:
    
    `POST https://…/wp-json/awana/v1/invoice`
    
    with the invoice JSON (the structure you showed).
    
- The Woo endpoint:
    - Is authenticated via `X-CRM-API-Key`.
    - Is **idempotent** by `invoiceId`:
        - If no order with crm`_invoice_id = invoiceId`: create
        - If exists: update (status, lines, totals as agreed).

## 2: Woo creates guest order

- Woo creates a **guest order** (no WP user).
- Stores rich meta on the order:
    - `crm_invoice_id`
    - `crm_member_id`
    - `crm_organization_id`
    - `pog_customer_number` (if already known)

## 3: Integrera sends the order to POG

Here we have a couple of conditionals:

- If `pog_customer_number` already exists in order meta:
    - Integrera links the order to the existing customer in POG.
- If `_pog_customer_number` is missing:
    - Integrera/POG creates a new customer in POG.
    - POG returns `pog_customer_number` back to Woo
    - Woo:
        - saves `pog_customer_number` on the order,
        - triggers webhook/HTTP to CRM so that **CRM updates the org** with the same `pog_customer_number`. We find order by `crm_invoice_id`.

## 4: When invoice is paid

When the invoice is paid, Integrera reads this back and marks the order as completed. Woo sends that information back to CRM.

# D: API endpoints

**1) `/awana/v1/invoice`**

- Method: `POST`
- Auth: `X-CRM-API-Key`
- Purpose: create/update guest order from CRM invoice.
- Input:
    - Required fields (invoiceId, productIds, quantities, etc.)
    - Optional fields (totals, vat, descriptions … depending on final choice)
- Output:
    - `wooOrderId`, `status`, etc.
- Idempotency rule: same `invoiceId` = update existing order.

**2) `/awana/v1/invoice-sync`**

- Method: `POST`
- Auth: same
- Purpose:
    - update `pog_customer_id`
    - update payment status (`paid`, `amountPaid`)
- Input schemas for:
    - `updatePogCustomerNumber`
    - `updateInvoiceStatus`
- Output: confirmation + `wooOrderId`.

# E: Field mapping

| CRM / Digital field | Woo storage | Required? |
| --- | --- | --- |
| invoiceId | order meta `_crm_invoice_id` | Yes |
| pogCustomerId | `_pog_customer_id` | Optional |
| productId | Woo product (ID/SKU/mapping) | Yes |
| quantity | line item quantity | Yes |
| email | billing_email | Yes |
| invoiceLines | Line items | Yes |
| memberId | crm_member_id | Yes |
| organizationId | crm_organization_id |  |

# Tasks

## Task 1: Create meta of orders

```sql
SELECT *
FROM wp_wc_orders_meta
WHERE order_id = 66
```