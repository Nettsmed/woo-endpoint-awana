# Epost 1
Hei

Her er slik jeg ser for meg flyt mellom digital og woocommerce for medlemskontingent. Hvordan vil du at ditt endepunkt skal trigges? Er det en adresse som skal motta post-data?

1.
Det opprettes en invoice i digital løsning, med type = membership_fee og method

2.
Firebase function kjører ved onCreate og sender data til woocommerce (json-format?), dette er bare dummydata:
{
  "invoiceId": "firebaseRecordName",
  "invoiceNumber": 400002,
  "invoiceDate": "2025-02-01T10:23:00Z",
  "type": "membership_fee",
  "method": "invoice",
  "memberId": "a34gd-123s-43gd33-dfd",
  "pogCustomerId": 100049,
  "wooUserId": null,
  "status": "unpaid",
  "createdVia": "crm-triggered",
  "createdAt": "2025-02-01T10:23:00Z",
  "createdBy": "userId",
  "syncStatus": {
    "woo": "pending",
    "pog": "not_applicable",
    "email": "pending"
  },
  "email": "testbruker@test.no",
  "firstName": "",
  "lastName": "",
  "customerName": "Testkirken i Norge",
  "shippingLines": [
    {
      "address": null,
      "postalCode": null,
      "postalArea": null,
      "country": "Norge"
    }
  ],
  "currency": "NOK",
  "pricesIncludeTax": true,
  "shippingTotal": 0,
  "shippingTax": 0,
  "total": 2990,
  "totalTax": 100,
  "paymentTerms": 10,
  "dueDate": "2025-12-20T00:00:00.000Z",
  "paymentMethod": "invoice",
  "organizationNumber": null,
  "invoiceLines": [
    {
      "productId": 9110,
      "quantity": 1,
      "accountCode": 1920,
      "description": "Medlemskontingent - tilgang til Awana Digital",
      "unitCost": 500,
      "unitPrice": 500,
      "vat": 100,
      "vatCode": "3"
    },
    {
      "productId": 9111,
      "quantity": 1,
      "accountCode": 1920,
      "description": "Medlemskontingent - tilgang til Awana Digital",
      "unitCost": 2490,
      "unitPrice": 2490,
      "vatCode": "5"
    }
  ]
}

3. Woocommerce oppretter bruker dersom denne mangler

4. Woocommerce oppretter ordre

5. Integrera sender ordre til POGO

6. Dersom pog_customer_number mangler
a. Kunde opprettes i POG
b. pog_customer_number oppdateres I ordre Integrera
c. Woocommerce trigges og bruker funksjonen returnCustomerNumber med  (trigges den av at Integrera skrive denne informasjonen tilbake?)
{
 invoiceId: x
 memberId: x
 pog_customer_number: x
 updatePogCustomerNumber: true
}

6. Fakturakladd sendes ut av Imf som epost eller EHF gjennom POG.

7. Når faktura er betalt henter Integrera status tilbake til ordre og oppdaterer betalingsstatus -> trigger funksjonen updateInvoiceStatus:
{
 invoiceId: x
 memberId: x
 pog_customer_number: x
 status: «paid»
 amountPaid: 2990
 updateInvoiceStatus: true
}

Legger ved første versjon av function.

Eksempel på importert faktura fra Cornerstone (alle faktura for 2025 ligger inne i systemet slik som dette):

{
  "amount": 4000,
  "amountOutstanding": 0,
  "amountPaid": 4000,
  "countryId": "no",
  "createdAt": {
    "__time__": "2025-06-30T14:06:00.504Z"
  },
  "createdBy": "dUUkdPCJKHbHmreGWGdLr0tLben2",
  "description": "Medlemsavgift",
  "documentType": "invoice",
  "dueDate": {
    "__time__": "2025-02-25T23:00:00.000Z"
  },
  "importDate": {
    "__time__": "2025-06-30T14:06:00.504Z"
  },
  "importTag": "invoice-import-2025-06-30",
  "invoiceDate": {
    "__time__": "2025-02-10T23:00:00.000Z"
  },
  "invoiceId": "321665",
  "invoiceNumber": "321665",
  "journalNumber": "78",
  "matchConfidence": "high",
  "matchType": "partialName",
  "memberId": "f59e0cd5-8707-4d4f-a583-d4d9d156cbd8",
  "memberName": "Evangeliehuset Åsgreina",
  "organizationName": "Barne og ungdomsarbeidet Evangeliehuset Åsgreina",
  "paidAt": {
    "__time__": "2025-06-04T22:00:00.000Z"
  },
  "paymentUrl": "https://admin.imf.no/imf-hk/makepayment/65AS8SAU6SZtDcncq5",
  "phone": "+47 920 27 245",
  "reference": "020361550047",
  "source": "excel-import",
  "status": "paid",
  "structureUpdatedAt": {
    "__time__": "2025-07-01T08:46:06.808Z"
  },
  "structureUpdatedBy": "dUUkdPCJKHbHmreGWGdLr0tLben2",
  "template": "Fakturering Awana"
}

# Epost 2: 
Hei

Her er siste versjon av funksjonene som er opprettet for inn og ut av systemet. De er også commited til main nå, men jeg har ikke lagt dem til production eller deployet noe enda.

Her er en ordrekladd som er opprettet i systemet (du finner den i awana-server -> invoices):

{
  "amount": 1750,
  "amountOutstanding": 1750,
  "amountPaid": 0,
  "countryId": "no",
  "createdAt": {
    "__time__": "2025-12-02T14:55:57.547Z"
  },
  "createdBy": "dUUkdPCJKHbHmreGWGdLr0tLben2",
  "currency": "NOK",
  "description": "Medlemskontingent 2025 - lisens undervisningsbøker (oppgradering)",
  "documentType": "invoice",
  "dueDate": {
    "__time__": "2025-12-12T00:00:00.000Z"
  },
  "email": "vigdis@kristkirken.no",
  "invoiceDate": {
    "__time__": "2025-12-02T00:00:00.000Z"
  },
  "invoiceLines": [
    {
      "description": "Medlemskontingent 2025 - lisens undervisningsbøker (oppgradering)",
      "productId": 3102,
      "quantity": 1,
      "unitCost": 1657,
      "unitPrice": 1657,
      "vat": 0,
      "vatCode": "fritatt",
      "vatRate": 0
    },
    {
      "description": "Medlemskontingent 2025 - tilgang Awana digital",
      "productId": 3012,
      "quantity": 1,
      "unitCost": 74,
      "unitPrice": 74,
      "vat": 19,
      "vatCode": "25",
      "vatRate": 0.25
    }
  ],
  "memberId": "b8dab589-dbde-4516-b56e-6b5fcb853ec6",
  "memberName": "Kristkirken søndagsskole Bergen",
  "method": "invoice",
  "notes": null,
  "orgNumber": null,
  "organizationId": "kristkirken-i-bergen",
  "organizationName": null,
  "pogCustomerNumber": null,
  "source": "awana-crm",
  "status": "draft",
  "syncStatus": {
    "email": "pending",
    "pog": "not_applicable",
    "woo": "pending"
  },
  "total": 1750,
  "totalTax": 19,
  "type": "membership_fee"
}

--