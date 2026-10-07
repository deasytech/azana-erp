<?php

namespace App\Support\ApiDocs;

/**
 * The content of the mobile API reference page (/docs): one place that says what every endpoint is, takes and returns. A test checks it
 * against the real routes and the real responses, so the page cannot quietly go out of date.
 *
 * An endpoint: method, path (under /api/v1), summary, auth (false for sign-in), limit (the rate limiter), permission, params (title and
 * rows of [name, type, required, description]), body (the example request body), notes ([title, html]), statuses ([code, text, ok|err])
 * and responses ([status label, JSON as a PHP array]). Its anchor is derived from the method and path.
 */
final class ApiReference
{
    private const OK = '200 Response';

    private const CREATED = '201 Response';

    private const TEXT = 'string, max 500';

    private const DEVICE_TYPE = 'string, max 64';

    private const CLIENT_ID = '6c9e2a42-9f0e-4d4f-b0c1-0a1f6a6f2c11';

    private const WHEN = '2026-10-08T06:42:10+01:00';

    private const DEVICE = 'pixel-7-3f9a';

    private const SOW = 'SOW-000123';

    private const PIG = 'FIN-000451';

    private const POSITIVE = 'number > 0';

    private const HEALTH = 'health.create';

    /** @return list<array{key: string, title: string, intro: string, endpoints: list<array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            ['key' => 'auth', 'title' => 'Authentication', 'intro' => 'Sign a device in, sign it out, and ask who is signed in.', 'endpoints' => self::auth()],
            ['key' => 'lookups', 'title' => 'Lookups', 'intro' => 'Everything the app reads: scanned codes, animals, the worker\'s tasks and the lists to keep offline.', 'endpoints' => self::lookups()],
            ['key' => 'sync', 'title' => 'Sync', 'intro' => 'Sending what the worker recorded, and finding out what became of it.', 'endpoints' => self::sync()],
            ['key' => 'quick', 'title' => 'Quick actions', 'intro' => 'The twelve things a worker records in the field. Each is a mutation of a given type, sent through POST /sync/push or POST /quick/{type}; the page for each gives its payload.', 'endpoints' => self::quickActions()],
        ];
    }

    /** @return list<string> "METHOD /path" of every documented endpoint */
    public static function signatures(): array
    {
        $sigs = [];

        foreach (self::groups() as $group) {
            foreach ($group['endpoints'] as $e) {
                if (! ($e['quick'] ?? false)) {
                    $sigs[] = $e['method'].' '.$e['path'];
                }
            }
        }

        return $sigs;
    }

    /** @return list<string> the quick action types that are documented */
    public static function quickTypes(): array
    {
        return array_values(array_map(fn ($e) => $e['quick'], array_filter(self::quickActions(), fn ($e) => isset($e['quick']))));
    }

    public static function anchor(array $e): string
    {
        return strtolower($e['method']).'-'.trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($e['path'])), '-');
    }

    private static function user(): array
    {
        return ['id' => 14, 'name' => 'Ngozi Eze', 'email' => 'ngozi@azanafarms.com', 'roles' => ['Farm Worker'], 'permissions' => ['animals.view', 'animals.create', 'health.view', self::HEALTH, 'production.view', 'production.create', 'tasks.view', 'tasks.edit']];
    }

    /** @return list<array<string, mixed>> */
    private static function auth(): array
    {
        return [
            [
                'method' => 'POST', 'path' => '/auth/login', 'auth' => false, 'limit' => 'mobile-login',
                'summary' => 'Signs a device in and returns its token. One token per device: signing in again on the same device replaces the earlier token.',
                'params' => ['title' => 'Body', 'rows' => [
                    ['email', 'string', true, 'The worker\'s email.'], ['password', 'string', true, 'Their password.'],
                    ['device_id', self::DEVICE_TYPE, true, 'A stable id the app makes once and keeps. It names the token and marks every mutation this device sends.'],
                    ['device_name', 'string, max 60', false, 'A label for the device (shown nowhere yet).'],
                ]],
                'body' => ['email' => 'ngozi@azanafarms.com', 'password' => '••••••••', 'device_id' => self::DEVICE],
                'notes' => [['Who may sign in', 'An active user who holds the <code>mobile.view</code> permission. Roles that need <b>two-factor</b> sign-in (accountant, owner) get <code>403 two_factor_required</code> and use the web app. Wrong email or password is always <code>401 invalid_credentials</code>: the response never says which was wrong.']],
                'statuses' => [[200, 'OK', 'ok'], [401, 'invalid_credentials', 'err'], [403, 'mobile_forbidden / two_factor_required', 'err'], [422, 'validation', 'err'], [429, 'too many attempts', 'err']],
                'responses' => [[self::OK, ['token' => '7|kQ3m...e1c', 'expires_at' => '2026-11-12T09:30:00+00:00', 'user' => self::user()]]],
            ],
            [
                'method' => 'POST', 'path' => '/auth/logout', 'summary' => 'Revokes this device\'s token. Sign in again to get a new one.', 'limit' => 'mobile',
                'statuses' => [[200, 'OK', 'ok'], [401, 'unauthenticated', 'err']],
                'responses' => [[self::OK, ['message' => 'Signed out.']]],
            ],
            [
                'method' => 'GET', 'path' => '/me', 'limit' => 'mobile',
                'summary' => 'The signed-in user, their roles, and their view, create and edit permissions: use them to decide which quick actions to offer.',
                'notes' => [['Checked every time', 'Access is re-checked on every request. If the worker\'s account is deactivated or the mobile permission is withdrawn, the very next request is <code>403 mobile_forbidden</code>, even though the token has not expired.']],
                'statuses' => [[200, 'OK', 'ok'], [401, 'unauthenticated', 'err'], [403, 'mobile_forbidden', 'err']],
                'responses' => [[self::OK, self::user()]],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function lookups(): array
    {
        return [
            [
                'method' => 'GET', 'path' => '/reference', 'limit' => 'mobile',
                'summary' => 'The lists to keep on the device so forms work offline: pens, locations, stores, feed types, medicines, vaccination schedules, stock items, mortality causes, movement reasons and animal categories.',
                'params' => ['title' => 'Query', 'rows' => [['version', 'string', false, 'The version you already hold. If nothing changed, only <code>{version, unchanged: true}</code> comes back.']]],
                'notes' => [['Refresh', 'Fetch on sign-in and whenever the app comes online, sending the version you hold. The ids in these lists are the ids quick-action payloads use.']],
                'statuses' => [[200, 'OK', 'ok'], [401, 'unauthenticated', 'err'], [403, 'mobile_forbidden', 'err']],
                'responses' => [
                    [self::OK, ['version' => 'a41f09c7d2e8b365', 'data' => [
                        'quick_actions' => ['add_birth', 'record_weight', 'record_feed', 'record_treatment', 'record_vaccination', 'record_mortality', 'move_pigs', 'record_service', 'record_farrowing', 'record_weaning', 'stock_count', 'complete_task'],
                        'service_methods' => ['natural', 'artificial_insemination'],
                        'pens' => [['id' => 7, 'code' => 'FIN-03']], 'locations' => [['id' => 2, 'code' => 'QUAR', 'name' => 'Quarantine']], 'stores' => [['id' => 1, 'code' => 'FEED', 'name' => 'Feed store']],
                        'feed_types' => [['id' => 3, 'code' => 'GROWER', 'name' => 'Grower feed']], 'medicines' => [['id' => 12, 'code' => 'AMOX', 'name' => 'Amoxicillin']],
                        'vaccination_schedules' => [['id' => 4, 'name' => 'Erysipelas booster', 'medicine_id' => 9]], 'items' => [['id' => 21, 'code' => 'MAIZE', 'name' => 'Maize']],
                        'mortality_causes' => [['id' => 5, 'code' => 'scours', 'name' => 'Scours / diarrhoea']], 'movement_reasons' => [['id' => 2, 'code' => 'weaning', 'name' => 'Weaning']],
                        'animal_categories' => [['id' => 1, 'code' => 'sow', 'name' => 'Sow']],
                    ]]],
                    ['200 Response (unchanged)', ['version' => 'a41f09c7d2e8b365', 'unchanged' => true]],
                ],
            ],
            [
                'method' => 'GET', 'path' => '/scan/{code}', 'limit' => 'mobile',
                'summary' => 'Says what a scanned QR code or barcode is. An animal code may be its tag, its number, its public id or the QR link printed on its tag.',
                'params' => ['title' => 'Path', 'rows' => [['code', 'string', true, 'The scanned text, URL-encoded.']]],
                'notes' => [['Types', '<code>animal</code>, <code>pen</code>, <code>production_batch</code>, <code>item</code>, <code>litter</code> or <code>task</code>. Only kinds the user may view are matched: a code for something they cannot see is a <code>404</code>, the same as a code that matches nothing.']],
                'statuses' => [[200, 'OK', 'ok'], [404, 'nothing matches', 'err'], [401, 'unauthenticated', 'err'], [403, 'mobile_forbidden', 'err']],
                'responses' => [[self::OK, ['type' => 'animal', 'id' => 318, 'code' => self::SOW, 'label' => 'SOW-000123 (active)']]],
            ],
            [
                'method' => 'GET', 'path' => '/animals/{code}', 'limit' => 'mobile',
                'summary' => 'A summary of one animal, for the screen shown after a scan.',
                'params' => ['title' => 'Path', 'rows' => [['code', 'string', true, 'Tag, number, public id or QR link.']]],
                'statuses' => [[200, 'OK', 'ok'], [404, 'no such animal', 'err'], [403, 'not allowed to view animals', 'err']],
                'responses' => [[self::OK, ['animal_number' => self::SOW, 'public_id' => '01JABCDEF123456789XYZ', 'sex' => 'female', 'category' => 'Sow', 'breed' => 'Large White', 'status' => 'active', 'position' => 'Farrowing house 1 / FH-02', 'latest_weight_kg' => '182.50', 'url' => 'https://app.azanafarms.com/admin/animals/318']]],
            ],
            [
                'method' => 'GET', 'path' => '/tasks', 'limit' => 'mobile', 'permission' => 'tasks.view',
                'summary' => 'The worker\'s own open tasks, soonest due first (at most 200). Complete one with the <code>complete_task</code> quick action.',
                'statuses' => [[200, 'OK', 'ok'], [403, 'no tasks permission', 'err']],
                'responses' => [[self::OK, ['data' => [['number' => 'TK-000412', 'title' => 'Weigh batch GRW-2026-04', 'description' => 'Last weighed 21 Sep 2026', 'category' => 'production', 'priority' => 'normal', 'status' => 'open', 'due_on' => '2026-10-08', 'overdue' => false, 'requires_evidence' => false]]]]],
            ],
        ];
    }

    private static function result(array $over = []): array
    {
        return $over + ['client_id' => self::CLIENT_ID, 'status' => 'accepted', 'replayed' => false, 'server_type' => 'weight_record', 'server_id' => 981, 'error' => null];
    }

    /** @return list<array<string, mixed>> */
    private static function sync(): array
    {
        $mutation = ['client_id' => self::CLIENT_ID, 'type' => 'record_weight', 'occurred_at' => self::WHEN, 'payload' => ['animal' => self::SOW, 'weight_kg' => 182.5]];
        $conflict = self::result(['status' => 'conflict', 'server_type' => null, 'server_id' => null, 'error' => ['code' => 'animal_not_active', 'message' => 'SOW-000123 is dead and cannot be changed.', 'fields' => null]]);

        return [
            [
                'method' => 'POST', 'path' => '/sync/push', 'limit' => 'mobile',
                'summary' => 'Sends a queue of mutations. They are processed <b>in the order sent</b>, each on its own: one bad entry never blocks the rest. The whole queue may be sent again after a dropped connection.',
                'params' => ['title' => 'Body', 'rows' => [
                    ['device_id', self::DEVICE_TYPE, true, 'The id used at sign-in.'], ['mutations', 'array, 1 to 100', true, 'The queue, oldest first.'],
                    ['mutations[].client_id', 'uuid', true, 'Made on the device when the worker saves. The idempotency key: see below.'],
                    ['mutations[].type', 'string', true, 'One of the twelve quick actions.'],
                    ['mutations[].occurred_at', 'ISO 8601 date-time', true, 'When it happened on the device. The server records the event with this time. Not more than 5 minutes ahead of the server.'],
                    ['mutations[].payload', 'object', true, 'The fields of that quick action.'],
                ]],
                'body' => ['device_id' => self::DEVICE, 'mutations' => [$mutation]],
                'notes' => [
                    ['Exactly once', 'The first time a <code>client_id</code> arrives, the action runs and the outcome is stored. Every later time, the <b>stored outcome</b> comes back with <code>replayed: true</code> and nothing runs again. So resend freely: after a timeout, a crash, or when unsure. Only <code>failed</code> outcomes (a server error) are run again. A <code>client_id</code> belongs to the user and device that first sent it.'],
                    ['Order', 'Send the queue in the order the worker recorded it. If a later mutation depends on an earlier one (piglets after the farrowing that made their litter), the earlier one is already done when the later one runs.'],
                ],
                'statuses' => [[200, 'OK (each result has its own status)', 'ok'], [422, 'invalid request', 'err'], [401, 'unauthenticated', 'err'], [403, 'mobile_forbidden', 'err']],
                'responses' => [[self::OK, ['server_time' => '2026-10-08T07:05:00+00:00', 'results' => [self::result(), $conflict]]]],
            ],
            [
                'method' => 'POST', 'path' => '/quick/{type}', 'limit' => 'mobile',
                'summary' => 'Sends one mutation by itself. Same contract as one entry of <code>/sync/push</code>, with a single result and an HTTP status that says what happened.',
                'params' => ['title' => 'Body', 'rows' => [
                    ['device_id', self::DEVICE_TYPE, true, 'The id used at sign-in.'], ['client_id', 'uuid', true, 'The idempotency key.'],
                    ['occurred_at', 'ISO 8601 date-time', true, 'When it happened on the device.'], ['payload', 'object', true, 'The quick action\'s fields.'],
                ]],
                'body' => ['device_id' => self::DEVICE, 'client_id' => self::CLIENT_ID, 'occurred_at' => self::WHEN, 'payload' => ['animal' => self::SOW, 'weight_kg' => 182.5]],
                'path_example' => '/quick/record_weight',
                'statuses' => [[201, 'accepted', 'ok'], [200, 'replayed (already done)', 'ok'], [409, 'conflict', 'err'], [422, 'rejected', 'err'], [503, 'failed: send again', 'err']],
                'responses' => [[self::CREATED, self::result()], ['409 Response', $conflict]],
            ],
            [
                'method' => 'GET', 'path' => '/sync/status', 'limit' => 'mobile',
                'summary' => 'How this device\'s mutations stand: counts by status, the time of the last accepted one, and what needs attention (up to 50, newest first).',
                'params' => ['title' => 'Query', 'rows' => [['device_id', 'string', true, 'The device to report on.']]],
                'statuses' => [[200, 'OK', 'ok'], [422, 'device_id missing', 'err']],
                'responses' => [[self::OK, ['device_id' => self::DEVICE, 'counts' => ['accepted' => 41, 'rejected' => 1, 'conflict' => 1, 'failed' => 0], 'last_synced_at' => '2026-10-08 07:05:00', 'needs_attention' => [['client_id' => '0d6f5c6e-6d3a-4f43-9b52-2a9d9a0f2b10', 'type' => 'record_weight', 'status' => 'conflict', 'code' => 'animal_not_active', 'message' => 'SOW-000123 is dead and cannot be changed.', 'reviewed' => false]]]]],
            ],
            [
                'method' => 'GET', 'path' => '/sync/mutations/{client_id}', 'limit' => 'mobile',
                'summary' => 'The stored outcome of one mutation. Only the user who sent it can read it.',
                'params' => ['title' => 'Path', 'rows' => [['client_id', 'uuid', true, 'The mutation\'s client id.']]],
                'statuses' => [[200, 'OK', 'ok'], [404, 'not yours or unknown', 'err']],
                'responses' => [[self::OK, ['client_id' => self::CLIENT_ID, 'type' => 'record_weight', 'status' => 'accepted', 'server_type' => 'weight_record', 'server_id' => 981, 'attempts' => 1, 'synced_at' => '2026-10-08T07:05:00+00:00', 'error' => null]]],
            ],
        ];
    }

    /**
     * One entry per quick action. Each has the same shape; the details are data.
     *
     * @return list<array<string, mixed>>
     */
    private static function quickActions(): array
    {
        $rows = fn (array $r) => ['title' => 'Payload', 'rows' => $r];
        $codes = fn (string ...$c) => ['Outcomes the server can return', 'Besides <code>invalid_payload</code> (a field is missing or wrong, listed in <code>error.fields</code>) and <code>forbidden</code> (the user\'s role lacks the permission): '.implode(', ', array_map(fn ($x) => "<code>{$x}</code>", $c)).'.'];
        // $about is [summary, permission]; $codesList the outcome codes the action can return besides the generic ones.
        $quick = fn (string $type, array $about, array $params, array $payload, array $codesList, string $serverType, ?string $extra = null) => [
            'quick' => $type, 'method' => 'POST', 'path' => "/quick/{$type}", 'permission' => $about[1], 'limit' => 'mobile', 'summary' => $about[0], 'params' => $rows($params),
            'body' => ['device_id' => self::DEVICE, 'client_id' => self::CLIENT_ID, 'occurred_at' => self::WHEN, 'payload' => $payload],
            'notes' => array_values(array_filter([$extra ? ['Good to know', $extra] : null, $codes(...$codesList)])), 'statuses' => [[201, 'accepted', 'ok'], [409, 'conflict', 'err'], [422, 'rejected', 'err']],
            'responses' => [[self::CREATED, self::result(['server_type' => $serverType])]],
        ];

        return [
            $quick('add_birth', ['Registers the piglets of a litter that were born alive, one animal each, with their birth weights.', 'animals.create'],
                [['litter', 'string', true, 'The litter number (scanned or typed).'], ['pen_id', 'integer', false, 'Pen to place them in.'], ['piglets', 'array, 1 to 30', true, 'One entry per piglet.'], ['piglets[].sex', 'male | female', true, ''], ['piglets[].birth_weight_kg', self::POSITIVE, false, '']],
                ['litter' => 'LT-000087', 'pen_id' => 4, 'piglets' => [['sex' => 'male', 'birth_weight_kg' => 1.4], ['sex' => 'female']]], ['litter_capacity', 'litter_weaned'], 'litter',
                'The litter must already exist: send <code>record_farrowing</code> first. Registering more piglets than were born alive is rejected.'),
            $quick('record_weight', ['Weighs one pig, or records the average weight of a batch.', 'animals.create (animal) / production.create (batch)'],
                [['animal', 'string', false, 'The pig\'s code. Give <b>either</b> animal <b>or</b> batch.'], ['weight_kg', self::POSITIVE, false, 'Needed with animal.'], ['method', 'string', false, 'Default <code>scale</code>.'],
                    ['batch', 'string', false, 'The production batch code.'], ['average_weight_kg', self::POSITIVE, false, 'Needed with batch.'], ['sample_size', 'integer', false, 'How many pigs were weighed. Needed with batch.'], ['notes', self::TEXT, false, '']],
                ['animal' => self::PIG, 'weight_kg' => 82.5], ['animal_not_active', 'weigh_in_duplicate', 'weight_implausible'], 'weight_record'),
            $quick('record_feed', ['Records feed eaten by a batch or one animal. Name a store and the feed is drawn from stock.', 'production.create'],
                [['batch', 'string', false, 'Batch code. Give <b>either</b> batch <b>or</b> animal.'], ['animal', 'string', false, 'Animal code.'], ['feed_type_id', 'integer', true, 'From <code>/reference</code> feed_types.'], ['quantity_kg', self::POSITIVE, true, ''], ['inventory_location_id', 'integer', false, 'The store to draw from (from <code>stores</code>).']],
                ['batch' => 'GRW-2026-04', 'feed_type_id' => 3, 'quantity_kg' => 120, 'inventory_location_id' => 1], ['insufficient_stock', 'feed_not_stocked', 'feed_quantity'], 'feed_consumption_record'),
            $quick('record_treatment', ['Records a treatment given to a pig. A medicine with a withdrawal period starts one.', self::HEALTH],
                [['animal', 'string', true, ''], ['medicine_id', 'integer', true, 'From <code>medicines</code>.'], ['batch_id', 'integer', false, 'The medicine batch used.'], ['dose', self::POSITIVE, false, ''], ['dose_unit', 'string, max 20', false, ''], ['route', 'string, max 40', false, ''], ['notes', self::TEXT, false, '']],
                ['animal' => self::PIG, 'medicine_id' => 12, 'dose' => 5, 'dose_unit' => 'ml', 'route' => 'IM'], ['animal_not_active'], 'treatment'),
            $quick('record_vaccination', ['Records a vaccination, optionally against a schedule.', self::HEALTH],
                [['animal', 'string', true, ''], ['schedule_id', 'integer', false, 'From <code>vaccination_schedules</code>.'], ['medicine_id', 'integer', false, 'The vaccine, when there is no schedule.'], ['batch_id', 'integer', false, ''], ['dose', self::POSITIVE, false, ''], ['notes', self::TEXT, false, '']],
                ['animal' => self::PIG, 'schedule_id' => 4], ['vaccination_duplicate', 'not_a_vaccine', 'schedule_mismatch'], 'vaccination'),
            $quick('record_mortality', ['Records a death: of one pig, or of a number of pigs in a batch.', 'health.create (animal) / production.create (batch)'],
                [['animal', 'string', false, 'Give <b>either</b> animal <b>or</b> batch.'], ['disease_id', 'integer', false, ''], ['batch', 'string', false, 'Batch code.'], ['count', 'integer', false, 'Pigs lost. Needed with batch.'], ['cause_id', 'integer', true, 'From <code>mortality_causes</code>.'], ['notes', self::TEXT, false, '']],
                ['batch' => 'GRW-2026-04', 'count' => 2, 'cause_id' => 5], ['animal_not_active'], 'mortality_record',
                'A pig already recorded dead (by someone else, or from another device) comes back as a <code>conflict</code> with <code>animal_not_active</code>, not as a second death.'),
            $quick('move_pigs', ['Moves a pig to a pen or location.', 'animals.edit'],
                [['animal', 'string', true, ''], ['pen_id', 'integer', false, 'Give <b>either</b> pen_id <b>or</b> location_id.'], ['location_id', 'integer', false, ''], ['reason_id', 'integer', false, 'From <code>movement_reasons</code>.'], ['notes', self::TEXT, false, '']],
                ['animal' => self::PIG, 'pen_id' => 7, 'reason_id' => 2], ['movement_same_place', 'movement_out_of_order', 'pen_full', 'inactive_pen'], 'animal_movement'),
            $quick('record_service', ['Records a service (mating or insemination) of a sow.', 'breeding.create'],
                [['sow', 'string', true, ''], ['method', 'natural | artificial_insemination', true, ''], ['boar', 'string', false, 'The boar\'s code.'], ['semen_source', 'string', false, ''], ['semen_batch_id', 'integer', false, 'For insemination from stock.'], ['semen_location_id', 'integer', false, ''], ['doses', 'integer 1 to 20', false, ''], ['technician_name', 'string', false, ''], ['notes', 'string', false, '']],
                ['sow' => self::SOW, 'method' => 'natural', 'boar' => 'BOAR-000020'], ['sow_pregnant', 'sow_lactating', 'service_out_of_order'], 'breeding_service'),
            $quick('record_farrowing', ['Records a farrowing; the litter is created automatically. The mutation\'s <code>occurred_at</code> is the farrowing date.', 'breeding.create'],
                [['sow', 'string', true, ''], ['total_born', 'integer', true, ''], ['born_alive', 'integer', true, ''], ['stillborn', 'integer', false, ''], ['mummified', 'integer', false, ''], ['total_birth_weight_kg', self::POSITIVE, false, ''], ['assisted', 'boolean', false, ''], ['breeding_service_id', 'integer', false, ''], ['notes', 'string', false, '']],
                ['sow' => self::SOW, 'total_born' => 12, 'born_alive' => 10, 'stillborn' => 1, 'mummified' => 1], ['sow_lactating', 'farrowing_duplicate', 'litter_counts'], 'litter'),
            $quick('record_weaning', ['Weans a litter: records the count and weight, and dates the sow\'s next service.', 'breeding.edit'],
                [['litter', 'string', true, 'The litter number.'], ['weaned_count', 'integer', true, ''], ['total_weight_kg', self::POSITIVE, false, ''], ['destination_pen_id', 'integer', false, ''], ['notes', 'string', false, '']],
                ['litter' => 'LT-000087', 'weaned_count' => 9, 'total_weight_kg' => 72.5], ['litter_weaned', 'weaning_weight', 'weaned_count'], 'weaning_record'),
            $quick('stock_count', ['Counts a store: opens the count, enters every line and submits it for approval, as <b>one</b> all-or-nothing mutation.', 'inventory.create'],
                [['location_id', 'integer', true, 'The store, from <code>stores</code>.'], ['lines', 'array, 1 to 200', true, ''], ['lines[].item_id', 'integer', true, ''], ['lines[].batch_id', 'integer', false, ''], ['lines[].counted_quantity', 'number >= 0', true, 'What was physically counted.'],
                    ['lines[].seen_quantity', 'number >= 0', false, 'What the device was showing as in stock. If the system holds something else now, the count is a conflict.'], ['lines[].reason', 'string, max 255', false, 'Required by the server for any line that differs from the system.'], ['notes', 'string', false, '']],
                ['location_id' => 1, 'lines' => [['item_id' => 21, 'counted_quantity' => 95, 'seen_quantity' => 100, 'reason' => 'Spillage']]], ['stale_count', 'count_not_draft'], 'stock_count',
                'Send <code>seen_quantity</code> with every line. A count made on numbers that have since changed is refused as <code>stale_count</code> and <b>nothing is left behind</b>: make the worker count again.'),
            $quick('complete_task', ['Completes a task. A worker completes their own; a supervisor may complete anyone\'s.', 'tasks.edit'],
                [['task', 'string', true, 'The task number, e.g. TK-000412.'], ['notes', 'string, max 1000', false, 'What was done.']],
                ['task' => 'TK-000412', 'notes' => 'Weighed 20 pigs, average 61.2 kg'], ['task_state', 'task_forbidden', 'task_evidence'], 'task'),
        ];
    }
}
