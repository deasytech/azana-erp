# Database Architecture

## Core Entity Groups

### Farm Structure
- farms
- production_units
- buildings
- rooms
- pens
- locations

### Animal
- animals
- animal_identifiers
- breeds
- genetic_lines
- animal_parentage
- animal_photos
- animal_status_history
- animal_movements
- weight_records

### Breeding
- heat_events
- breeding_services
- pregnancies
- farrowings
- litters
- piglets
- weaning_records

### Health
- health_events
- diseases
- vaccinations
- vaccination_schedules
- medicines
- medicine_batches
- treatments
- veterinary_visits
- laboratory_results
- withdrawal_periods
- mortality_records
- culling_records

### Feed
- feed_types
- feed_formulas
- feed_formula_items
- feed_production_orders
- feed_production_batches
- feed_consumption_records

### Inventory
- inventory_items
- inventory_locations
- inventory_batches
- inventory_transactions
- stock_counts
- stock_count_lines
- stock_adjustments

### Procurement
- suppliers
- purchase_requests
- purchase_request_lines
- purchase_orders
- purchase_order_lines
- goods_receipts
- goods_receipt_lines
- supplier_invoices
- supplier_payments

### Semen
- boars
- semen_collections
- semen_batches
- semen_qc_records
- semen_inventory_records
- semen_price_lists

### Sales
- customers
- sales_orders
- sales_order_lines
- invoices
- invoice_lines
- payments
- payment_allocations
- pig_sales
- semen_sales
- meat_sales

### Slaughter / Meat
- slaughter_batches
- slaughter_records
- carcasses
- meat_products
- meat_production_batches
- meat_inventory_records

### Finance
- accounts
- journal_entries
- journal_lines
- revenue_records
- expense_records
- cash_transactions
- cost_centres
- budgets
- budget_lines

### Operations
- employees
- tasks
- task_assignments
- approvals
- notifications
- biosecurity_visits
- biosecurity_checks

### Assets
- assets
- asset_maintenance_records
- vehicle_records
- vehicle_trips
- spare_parts

### Governance
- users
- roles
- permissions
- audit_logs
- attachments

## Inventory Ledger

Every stock-changing operation creates an inventory transaction.

Required transaction concepts should include:

- opening
- purchase
- receipt
- production
- consumption
- sale
- transfer_in
- transfer_out
- return
- wastage
- adjustment
- reservation/release where required

Never update a stock balance without a corresponding ledger entry.

## Traceability

Foreign keys and references must allow:

Animal -> Litter -> Sow -> Boar/Semen Batch

Animal -> Feed Consumption -> Feed Batch -> Raw Material Batch -> Supplier

Animal -> Slaughter -> Carcass -> Meat Batch -> Meat Sale -> Customer

Semen Batch -> Boar -> Collection -> QC -> Inventory -> Sale/Use

## Offline Identifiers

Mobile-created records should have:

- client UUID/ULID
- server ID after synchronization
- device ID
- created_at
- synced_at
- sync status
- idempotency key

A duplicate sync request must not create duplicate business transactions.

## Migration Rules

- One concern per migration where practical.
- Foreign keys where lifecycle permits.
- Index all business identifiers.
- Index frequent filtering fields.
- Index dates used in operational reports.
- Use unique constraints for identifiers.
- Do not create polymorphic relationships without a clear need.
