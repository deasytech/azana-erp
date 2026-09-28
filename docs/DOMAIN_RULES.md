# Domain Rules

## Animal

Every breeding animal has a permanent identification number.

An animal may have multiple identifiers such as:
- ear tag
- RFID
- QR
- barcode
- manual ID

Movement history is permanent.

## Breeding

Service records must link:
- sow
- boar or semen batch
- service date
- technician
- method

The system calculates expected:
- pregnancy check
- farrowing
- weaning
- next heat
- next service

Dates must be configurable through farm settings rather than scattered constants.

## Farrowing

A farrowing event creates a litter record.

The system must calculate:
- pre-weaning mortality
- weaning percentage
- average birth weight
- average weaning weight
- litter performance
- sow performance

## Health

Animals under an active medication withdrawal period must be flagged and prevented from sale/slaughter until cleared.

## Mortality

Every death is recorded.

Mortality analysis must support:
- pen
- batch
- age
- litter
- sow
- breed
- month
- production stage

## Culling

Culling requires an animal, reason, date, weight, health status, production performance and disposal/sale value.

## Feed Production

Production orders specify the intended output.

The system calculates expected raw-material requirements from the selected formulation.

Actual consumption is confirmed by production staff.

Completion must:
- consume raw materials
- create finished feed
- calculate production cost
- update inventory

## Semen QC

A semen batch cannot be available for sale unless its QC status permits release.

Failed batches must not be sellable.

Possible statuses:
- pending QC
- passed
- failed
- released
- quarantined
- expired
- destroyed

## Sales

Sales must support:
- cash
- bank transfer
- POS
- credit
- part payment
- customer deposits

Credit customers require:
- credit limit
- approved terms
- current balance
- overdue balance
- credit status

Sales must warn or block according to configured credit rules.

## Slaughter

Each animal entering slaughter must have intake and inspection information.

Dressing percentage:

`carcass weight / live weight * 100`

## Meat

Meat products must retain:
- source animal/batch
- production batch
- quantity
- weight
- storage location
- expiry/use-by date
- cost
- status

## Approvals

At minimum, approval capability is required for:
- animal disposal
- mortality correction
- stock adjustment
- purchase order
- customer credit
- discounts above threshold
- semen batch release
- slaughter adjustment
- financial journal
- large payment

Approval thresholds must be configurable.

## Configuration

Management assumptions such as:
- production targets
- semen prices
- pig prices
- sales allocation
- feed targets
- alert thresholds

must be stored as configuration/planning data, not code constants.
