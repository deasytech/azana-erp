# Loading existing data

For staff who move the farm's records into the ERP. Open **Administration > Data imports** (needs the *Data imports* permission and the right to create that kind of data).

## The routine

1. **Download the template** for the kind of data (Excel or CSV). The first row holds the column headings; do not rename or remove them. In Excel, the second sheet, *Guide*, says what each column means.
2. **Fill it in**, one record per row. Format phone-number columns as text so a leading 0 is kept. Dates are written `2026-10-01` (or `01/10/2026`, day first). Money is in naira with up to two decimals (`1250.50`).
3. **Upload and check.** Nothing is saved by the check. Every row is tried against the same rules as typing it in by hand, and gets a verdict.
4. **Fix and re-upload** until no row has a problem. *Download problems* gives a CSV of only the failed rows, with the reason in the second column, ready to correct and upload again.
5. **Import this data** (needs the *approve* permission on Data imports). All rows are saved together, or none are. A file can be imported once.

## What each import does

| Import | Becomes | Rules worth knowing |
| --- | --- | --- |
| Customers | Customers on cash terms (C-000001...) | A customer with the same name, e-mail or phone is reported, never overwritten. Approve credit limits afterwards. |
| Suppliers | Suppliers (SUP-0001...) | Same-name, code or e-mail duplicates are reported. |
| Pigs on the farm | Registered animals with a permanent number | Active animals only. Your own tags go in as identifiers; a tag already on another animal is rejected. List parents above their offspring. A weight needs its date. |
| Inventory opening balances | `opening` entries in the stock ledger, with cost | One opening balance per item, store and batch. Items and stores must exist first. Later corrections are a stock count or adjustment. |
| Feed formulas | Draft formulas, version 1 | One ingredient per row; rows sharing a `formula_code` make one formula. The nutritionist reviews and activates them. |
| Historical sales | Dispatched orders, invoices and receipts flagged *historical* | See below. |

## Historical sales

Historical sales are for the record and for what customers still owe. They:

- **do** appear in customer balances, overdue lists, sales history and sales reports;
- **do not** move stock (the goods are long gone) and are **not** posted to the ledger, so they cannot be counted twice with your opening balances. Enter what was owed and held at go-live through the finance opening balances.

Each row needs your **old invoice reference**. It identifies the sale, so the same file cannot be imported twice.

## Safety

- Imports never overwrite existing records.
- A file may hold up to 5,000 rows; split larger ones.
- Every import is audited (who, when, how many rows) and the imported records carry the normal audit trail.
- The uploaded file is not kept after it is checked; the rows and verdicts stay on the import's page.

## Adding a new kind of import (developers)

Create a class in `app/Domain/Import/Importers` extending `Importer` (columns, `save()` calling the existing domain action), and list it in `ImportRegistry`. The check, error report, templates, permissions and screens come with it.
