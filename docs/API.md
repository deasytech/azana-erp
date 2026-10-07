# Mobile API (v1)

> The browsable reference for app developers is served by the application at **`/docs`** (sidebar, search, copyable cURL and JSON examples, light and dark). It is built from `app/Support/ApiDocs/ApiReference.php`, and tests fail if it drifts from the real routes, payloads or responses. This file is the plain-text companion.

Base URL: `https://<host>/api/v1`. JSON in, JSON out. Every endpoint except sign-in needs `Authorization: Bearer <token>`.
The mobile API calls the same domain actions as the web app; it contains no business rules of its own.

## Sign-in and access

| Method and path | What it does |
|---|---|
| `POST /auth/login` | Body: `email`, `password`, `device_id` (a stable id the app makes once and keeps), optional `device_name`. Returns `token`, `expires_at`, `user` (name, roles, permissions). One token per device: signing in again on a device replaces its token. 20 attempts a minute per address and 5 a minute per email, whatever the address. |
| `POST /auth/logout` | Revokes this device's token. |
| `GET /me` | The signed-in user, roles and the view/create/edit permissions the app can use to decide what to offer. |

A user may use the API only while active, holding the `mobile.view` permission, and on a token issued by `/auth/login`. This is checked on every request, so withdrawing the permission or deactivating the account ends access immediately. Roles that must sign in with two-factor (accountant, owner...) get `403 two_factor_required` and use the web app. Tokens expire after `SANCTUM_EXPIRATION` minutes (default 30 days). Wrong email or password is always `401 invalid_credentials`.

What a user may *do* is decided by their ordinary permissions (the same ones as on the web), not by the token.

## Lookups

| Path | Returns |
|---|---|
| `GET /scan/{code}` | What a scanned QR code or barcode is: `{type, id, code, label}`, type being `animal`, `pen`, `production_batch`, `item`, `litter` or `task`. An animal code may be its tag, number, public id or the QR link. 404 when nothing matches or the user may not see it. |
| `GET /animals/{code}` | Animal summary: number, public id, sex, category, breed, status, position, latest weight. |
| `GET /tasks` | The worker's open tasks, soonest due first. |
| `GET /reference?version=` | The lists to keep offline (pens, locations, stores, feed types, medicines, vaccination schedules, items, mortality causes, movement reasons, animal categories, service methods, the quick-action names) with a `version`. Send the version you hold: an unchanged list comes back as `{version, unchanged: true}`. |

## Quick actions and sync

Everything a worker records is a **mutation**:

```json
{
  "client_id": "6c9e2a42-9f0e-4d4f-b0c1-0a1f6a6f2c11",
  "type": "record_weight",
  "occurred_at": "2026-10-08T06:42:10+01:00",
  "payload": { "animal": "SOW-000123", "weight_kg": 182.5 }
}
```

* `client_id`: a UUID the device makes when the worker saves. It is the idempotency key.
* `occurred_at`: when it happened on the device (not in the future, more than 5 minutes ahead). The server records the event with this time.
* `payload`: fields of the quick action. Animals, batches, litters and tasks are named by their codes (the scanned code); everything else (pen, medicine, feed type, cause...) by the `id` from `/reference`.

`POST /sync/push` takes `{device_id, mutations: [...]}` (1 to 100) and returns `{server_time, results: [...]}`; mutations are processed **in the order sent**, each independently. `POST /quick/{type}` sends one (`{device_id, client_id, occurred_at, payload}`) and answers with one result and an HTTP status (201 accepted, 200 replayed, 409 conflict, 422 rejected, 503 failed).

A **result**:

```json
{ "client_id": "...", "status": "accepted", "replayed": false, "server_type": "weight_record", "server_id": 981, "error": null }
```

| `status` | Meaning | What the device does |
|---|---|---|
| `accepted` | Done. `server_type` / `server_id` identify what was made. | Remove from the queue. |
| `rejected` | The request is wrong: `error.code` is `unknown_type`, `invalid_envelope`, `forbidden`, `invalid_payload` (with `error.fields`), or the code of a business rule the data breaks. Sending it again will not help. | Show the worker; let them correct and send a *new* mutation. |
| `conflict` | The server has moved on since the device saw it (animal already dead, litter already weaned, a duplicate farrowing, a stock count on numbers that changed...). Nothing was applied. | Show the worker; do not resend. A supervisor sees it under *Mobile sync log* in the web app. |
| `failed` | Unexpected server error; nothing was saved. | Keep in the queue and send the **same** mutation again later. |

`replayed: true` means the server had already answered this `client_id`: it returns the stored result and does nothing. Sending the same mutation any number of times, in any batch, never repeats the business transaction. A `client_id` belongs to the user and device that first sent it.

`GET /sync/status?device_id=` returns counts by status, `last_synced_at` and the mutations needing attention. `GET /sync/mutations/{client_id}` returns the stored outcome of one.

### The twelve quick actions

| `type` | Payload | Domain action | Needs |
|---|---|---|---|
| `add_birth` | `litter` (litter number), `piglets[]` {`sex`, `birth_weight_kg?`}, `pen_id?` | RegisterLitterPiglets | animals.create |
| `record_weight` | `animal`, `weight_kg`, `method?` **or** `batch`, `average_weight_kg`, `sample_size` | RecordWeight / RecordBatchWeighIn | animals.create / production.create |
| `record_feed` | `batch` or `animal`, `feed_type_id`, `quantity_kg`, `inventory_location_id?` | RecordFeedConsumption | production.create |
| `record_treatment` | `animal`, `medicine_id`, `batch_id?`, `dose?`, `dose_unit?`, `route?`, `notes?` | RecordTreatment | health.create |
| `record_vaccination` | `animal`, `schedule_id?`, `medicine_id?`, `batch_id?`, `dose?`, `notes?` | RecordVaccination | health.create |
| `record_mortality` | `animal`, `cause_id`, `disease_id?` **or** `batch`, `count`, `cause_id` | RecordMortality / RecordBatchMortality | health.create / production.create |
| `move_pigs` | `animal`, `pen_id` or `location_id`, `reason_id?` | RecordAnimalMovement | animals.edit |
| `record_service` | `sow`, `method`, `boar?`, `semen_source?`, `semen_batch_id?`, `semen_location_id?`, `doses?` | RecordService | breeding.create |
| `record_farrowing` | `sow`, `total_born`, `born_alive`, `stillborn?`, `mummified?`, `total_birth_weight_kg?`, `assisted?` | RecordFarrowing | breeding.create |
| `record_weaning` | `litter`, `weaned_count`, `total_weight_kg?`, `destination_pen_id?` | WeanLitter | breeding.edit |
| `stock_count` | `location_id`, `lines[]` {`item_id`, `batch_id?`, `counted_quantity`, `seen_quantity?`, `reason?`} | StartStockCount, RecordCountLine, SubmitStockCount | inventory.create |
| `complete_task` | `task` (task number), `notes?` | AdvanceTask::complete | tasks.edit |

`stock_count` is one mutation: the count is opened, every line entered and the count submitted for approval, or nothing at all. If a line carries `seen_quantity` (what the device was shown) and the system's quantity is now different, the result is a `conflict` (`stale_count`) and no count is left behind.

## Errors

Validation of the request itself is `422` `{message, errors}`; no/invalid token `401`; missing mobile access `403 mobile_forbidden`; too many requests `429`. Domain rules inside a mutation are reported in the mutation's result, never as an HTTP error.
