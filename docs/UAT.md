# User acceptance testing

Run these scenarios on the **staging copy or the live system before go-live**, with the people who will do the job, not the developer. Each scenario has a role, steps and the result that counts as a pass. Record who ran it, the date and pass/fail; any fail blocks launch until fixed or consciously accepted by the Owner.

Use realistic but obviously test names ("UAT Pig 1") and remove or cancel the records afterwards through the normal cancel/reverse screens (history is never deleted).

## 1. Receive raw materials (Store Officer)
1. *Procurement > Purchase requests*: raise a request for maize; the manager approves it.
2. Raise and approve the purchase order; receive part of it into the main store, with batch number and expiry.
3. Record the supplier's invoice and pay part of it.

**Pass:** stock on hand shows the received quantity and cost; the order shows *partly received*; *Supplier balances* shows the unpaid rest.

## 2. Make feed (Feed Mill Manager)
1. Plan a production order from an active formula for 1,000 kg.
2. Confirm the actual raw-material use and complete the order.

**Pass:** the raw materials left the store, finished feed arrived with a cost per kg, and reversing the run puts everything back.

## 3. Pig life cycle (Farm Manager, Breeding Manager, Veterinarian, Farm Worker)
1. Register a sow and a boar; record a service; confirm pregnancy; record farrowing; wean.
2. Weigh, move to a pen, vaccinate and treat a piglet with a withdrawal period.
3. Record feed given to a production batch.

**Pass:** the dates were calculated from farm settings; the treated pig cannot be sold or slaughtered until the withdrawal ends; the litter shows mortality and weaning percentages.

## 4. Who can see what (each role)
Sign in as every role in turn and open the menu.

**Pass:** a Farm Worker sees no finance, users, backups or imports; a Sales Officer sees customers and sales but not the journal; an Accountant sees finance but cannot release semen; only the Owner and General Manager see *Backups & monitoring*. Two-factor is demanded for Owner, General Manager, Accountant and Semen Laboratory Manager.

## 5. Sell (Sales Officer, Accountant)
1. Create a credit customer with a limit. Sell semen doses (QC-released batch only) and a finishing pig on an order; confirm, dispatch, take part payment.
2. Try to sell above the credit limit and a failed-QC semen batch.

**Pass:** over-limit and failed-QC sales are warned or blocked as configured; the invoice, receipt and customer balance agree; voiding the receipt restores the balance; after *Finance > Journal entries > Post sales, receipts and purchases* the books show the sale and the receipt.

## 6. Slaughter, meat and trace (Slaughter Manager, Sales Officer)
1. Receive a finished pig at slaughter with inspection, record the carcass, produce meat cuts into a store, sell part to a customer.
2. Open *Trace a product* on the meat batch and on the pig.

**Pass:** the trace reaches back to the semen batch, sow, litter, pen, feed batch, raw-material batch and supplier, and forward to the customer's invoice.

## 7. Mobile and offline (Farm Worker, phone)
1. Sign in to the mobile app and sync. Switch to aeroplane mode; record two weights and a feed entry; scan an ear tag.
2. Reconnect.

**Pass:** each entry appears once (retrying never duplicates); *Mobile sync log* shows them accepted; a weight for an animal that was meanwhile sold appears as a conflict for a manager to review.

## 8. Approvals (General Manager)
Request a stock adjustment, a large payment and a journal above the threshold.

**Pass:** each waits in *Approvals* until a different person with the right to approve decides; the requester cannot approve their own.

## 9. Reports and money (Owner, Accountant)
1. Open the owner home page, profitability, cash flow, budget vs actual, stock on hand, receivables/payables.
2. Run `php artisan erp:reconcile` (or ask the system administrator).

**Pass:** figures match the screens used above; revenue and major costs can be followed back to the records behind them; every reconciliation line is OK.

## 10. Disaster (system administrator)
Follow the drill in `docs/BACKUP_AND_RESTORE.md` on a spare server.

**Pass:** the system comes up with yesterday's data and the owner can sign in.

## Sign-off

| Scenario | Tester | Date | Pass / fail | Notes |
| --- | --- | --- | --- | --- |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | |
| 7 | | | | |
| 8 | | | | |
| 9 | | | | |
| 10 | | | | |
