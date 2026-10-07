<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Finance\Actions\PostManualJournal;
use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Sales\Actions\SetCustomerCredit;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Actions\AddTaskEvidence;
use App\Domain\Tasks\Actions\AdvanceTask;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Actions\GenerateDailyTasks;
use App\Domain\Tasks\Actions\GetAlerts;
use App\Domain\Tasks\Actions\GetPendingApprovals;
use App\Domain\Tasks\Actions\SendAlertNotifications;
use App\Domain\Tasks\Messaging\MessageGateway;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Notifications\AlertNotification;
use App\Domain\Tasks\Notifications\TaskAssignedNotification;
use App\Enums\CreditStatus;
use App\Enums\JournalStatus;
use App\Enums\LookupCategory;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

it('creates a task with a number, deadline, priority and category, once per source key', function () {
    $task = newTask(['category' => TaskCategory::Health, 'priority' => TaskPriority::High, 'sourceKey' => 'alert:1']);
    $again = newTask(['sourceKey' => 'alert:1', 'title' => 'Something else']);

    expect($task->number)->toBe('TK-000001')->and($task->status)->toBe(TaskStatus::Open)->and($task->category)->toBe(TaskCategory::Health)->and($task->priority)->toBe(TaskPriority::High)
        ->and($again->id)->toBe($task->id)->and($again->title)->toBe('Check the water lines')->and(Task::count())->toBe(1)
        ->and(fn () => newTask(['title' => ' ']))->toThrow(DomainException::class, 'title')
        ->and(fn () => newTask(['dueOn' => now()->addYears(2)]))->toThrow(DomainException::class, 'a year ahead')
        ->and(fn () => newTask(['role' => 'No such role']))->toThrow(DomainException::class, 'no role');
});

it('assigns a task, keeps the history, and tells the new assignee unless they assigned it to themselves', function () {
    $manager = userWithRole('Farm Manager');
    $worker = farmWorker();
    $other = farmWorker();
    $task = newTask(['assignee' => $worker, 'actor' => $manager]);

    expect($task->assigned_to)->toBe($worker->id)->and($worker->notifications)->toHaveCount(1)->and($worker->notifications->first()->type)->toBe(TaskAssignedNotification::class);

    app(AssignTask::class)($task, $other, $manager, 'Worker is off sick');
    app(AssignTask::class)($task, $other, $manager);                    // same person again: nothing new
    app(AssignTask::class)($task, $manager, $manager);                  // to oneself: no message

    expect($task->refresh()->assigned_to)->toBe($manager->id)->and($task->assignments)->toHaveCount(3)->and($other->notifications)->toHaveCount(1)->and($manager->notifications)->toHaveCount(0)
        ->and($task->assignments->first()->note)->toBeNull()->and($task->assignments->get(1)->note)->toBe('Worker is off sick');

    $inactive = farmWorker();
    $inactive->update(['is_active' => false]);

    expect(fn () => app(AssignTask::class)($task, $inactive))->toThrow(DomainException::class, 'not an active user');
});

it('lets the assignee or a supervisor work a task, and nobody else', function () {
    $worker = farmWorker();
    $colleague = farmWorker();
    $manager = userWithRole('Farm Manager');
    $task = newTask(['assignee' => $worker]);

    expect(fn () => app(AdvanceTask::class)->start($task, $colleague))->toThrow(DomainException::class, 'not yours');

    app(AdvanceTask::class)->start($task, $worker);

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress)->and(fn () => app(AdvanceTask::class)->start($task, $worker))->toThrow(DomainException::class, 'already been started');

    app(AdvanceTask::class)->complete($task, $manager, 'Done on the worker\'s behalf');

    expect($task->refresh()->status)->toBe(TaskStatus::Done)->and($task->completed_by)->toBe($manager->id)->and($task->completed_at)->not->toBeNull()->and($task->completion_notes)->toContain('worker')
        ->and(fn () => app(AdvanceTask::class)->complete($task, $manager))->toThrow(DomainException::class, 'already done');
});

it('lets only the role pick up an unassigned task', function () {
    $store = userWithRole('Store Officer');
    $task = newTask(['title' => 'Count the store', 'role' => 'Store Officer']);

    expect(fn () => app(AdvanceTask::class)->start($task, farmWorker()))->toThrow(DomainException::class, 'not yours');

    app(AdvanceTask::class)->start($task, $store);

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);
});

it('refuses to complete a task that needs a photo until one is attached, and checks the stored path', function () {
    $worker = farmWorker();
    $task = newTask(['assignee' => $worker, 'requiresEvidence' => true]);

    expect(fn () => app(AdvanceTask::class)->complete($task, $worker))->toThrow(DomainException::class, 'photo or file')
        ->and(fn () => app(AddTaskEvidence::class)($task, '../../etc/passwd', null, $worker))->toThrow(DomainException::class, 'not uploaded for a task')
        ->and(fn () => app(AddTaskEvidence::class)($task, 'elsewhere/photo.jpg', null, $worker))->toThrow(DomainException::class, 'not uploaded for a task')
        ->and(fn () => app(AddTaskEvidence::class)($task, 'task-evidence/a.jpg', null, farmWorker()))->toThrow(DomainException::class, 'not yours');

    app(AddTaskEvidence::class)($task, 'task-evidence/a.jpg', ' Pen 4 ', $worker);
    app(AdvanceTask::class)->complete($task, $worker);

    expect($task->refresh()->status)->toBe(TaskStatus::Done)->and($task->evidence)->toHaveCount(1)->and($task->evidence->first()->caption)->toBe('Pen 4')
        ->and(fn () => $task->evidence->first()->delete())->toThrow(LogicException::class);
});

it('cancels a task only with a reason', function () {
    $manager = userWithRole('Farm Manager');
    $task = newTask();

    expect(fn () => app(AdvanceTask::class)->cancel($task, $manager, ' '))->toThrow(DomainException::class, 'reason');

    app(AdvanceTask::class)->cancel($task, $manager, 'No longer needed');

    expect($task->refresh()->status)->toBe(TaskStatus::Cancelled)->and($task->cancel_reason)->toBe('No longer needed')->and(fn () => app(AssignTask::class)($task, farmWorker()))->toThrow(DomainException::class, 'cannot be assigned');
});

it('knows which tasks are overdue', function () {
    $late = newTask(['dueOn' => now()->subDays(2)]);
    $today = newTask(['dueOn' => now()]);
    app(AdvanceTask::class)->cancel($late, userWithRole('Farm Manager'), 'x');

    expect($today->isOverdue())->toBeFalse()->and(newTask(['dueOn' => now()->subDay()])->isOverdue())->toBeTrue()->and($late->refresh()->isOverdue())->toBeFalse();
});

it('generates the day\'s tasks from the rounds and the farm\'s own data, and a second run adds nothing', function () {
    // Stock at or below its reorder level (and the released semen, whose short shelf life is already running out).
    stockItem('MAIZE', ['reorder_level' => '500']);
    // A customer 40 days past an invoice, and an active batch nobody has weighed for 30 days.
    dispatched(semenOrder(creditCustomer(1000000000, 30), releasedSemen(), 2), 40);
    openBatch(['started_on' => now()->subDays(30)->startOfDay()]);

    $first = app(GenerateDailyTasks::class)();
    $second = app(GenerateDailyTasks::class)();

    expect($first)->toBe(['rounds' => 3, 'vaccinations' => 0, 'breeding' => 0, 'stock' => 2, 'production' => 1, 'sales' => 1])
        ->and(array_sum($second))->toBe(0)
        ->and(Task::pluck('title')->all())->toContain('Morning feeding round', 'Water and pen check', 'Evening headcount', 'Reorder Item MAIZE', 'Weigh batch '.ProductionBatch::first()->code, 'Collect overdue payment from Green Acres Farm')
        ->and(Task::firstWhere('title', 'like', 'Collect%')->priority)->toBe(TaskPriority::High)->and(Task::firstWhere('title', 'like', 'Collect%')->responsible_role)->toBe('Sales Officer')
        ->and(Task::where('category', TaskCategory::Rounds)->first()->due_on->isToday())->toBeTrue();
});

it('takes the daily rounds from settings and creates none when they are empty', function () {
    app(ResolveSettings::class)->set('tasks.daily_rounds', 'Feed boars; Clean farrowing house');
    app(GenerateDailyTasks::class)();

    expect(Task::pluck('title')->all())->toBe(['Feed boars', 'Clean farrowing house']);

    app(ResolveSettings::class)->set('tasks.daily_rounds', '');

    expect(array_sum(app(GenerateDailyTasks::class)(now()->addDay())))->toBe(0);
});

it('raises alerts from real data, worst first, and shows each person only their areas', function () {
    stockItem('SOYA', ['reorder_level' => '100']);                                     // none in stock: out of stock
    $batch = openBatch(['count' => 100]);
    app(RecordBatchMortality::class)($batch, 10, now()->startOfDay(), lookup(LookupCategory::MortalityCause, 'scours'));
    $customer = creditCustomer(1000000000, 30);
    dispatched(semenOrder($customer, releasedSemen(), 10), 40);                      // 15,000,000 owed and overdue ...
    app(SetCustomerCredit::class)($customer, CreditStatus::Approved, 1000000, 30, userWithRole('Farm Manager'));   // ... then the limit is lowered to 1,000,000
    newTask(['dueOn' => now()->subDays(3), 'priority' => TaskPriority::Urgent]);

    $all = app(GetAlerts::class)();
    $messages = $all->pluck('message')->implode("\n");

    expect($all->first()['severity'])->toBe('danger')
        ->and($messages)->toContain('Out of stock: Item SOYA')->and($messages)->toContain('10 of 100 pigs have died (10.00%')->and($messages)->toContain('owes more than the credit limit')
        ->and($messages)->toContain('has overdue invoices')->and($messages)->toContain('1 overdue task, including high-priority work')
        ->and($all->pluck('key')->unique()->count())->toBe($all->count());

    // A farm worker can see stock, production and tasks but not sales or finance.
    $worker = app(GetAlerts::class)(farmWorker())->pluck('module')->unique()->all();

    expect($worker)->toContain('inventory', 'production', 'tasks')->not->toContain('sales')->not->toContain('finance');
});

it('notifies each person once a day about the critical alerts they may see', function () {
    stockItem('SOYA', ['reorder_level' => '100']);                                      // out of stock: critical, inventory
    $store = userWithRole('Store Officer');
    $nobody = User::factory()->create();   // no role, so no area to see

    $sent = app(SendAlertNotifications::class)();
    $again = app(SendAlertNotifications::class)();

    expect($sent)->toBe(1)->and($again)->toBe(0)
        ->and($store->notifications)->toHaveCount(1)->and($store->notifications->first()->type)->toBe(AlertNotification::class)
        ->and($store->notifications->first()->data['body'])->toContain('Out of stock')
        ->and($nobody->notifications)->toHaveCount(0);

    // A new critical alert later the same day is announced; the old one is not repeated.
    stockItem('WHEAT', ['reorder_level' => '10']);
    app(SendAlertNotifications::class)();

    expect($store->refresh()->notifications)->toHaveCount(2)->and($store->notifications->first()->data['alert_keys'])->toHaveCount(1);
});

it('sends critical alerts by SMS or WhatsApp through the gateway only when switched on and a phone is known', function () {
    $sent = [];
    app()->instance(MessageGateway::class, new class($sent) implements MessageGateway
    {
        public function __construct(public array &$sent) {}

        public function send(string $channel, string $to, string $body): void
        {
            $this->sent[] = [$channel, $to, $body];
        }
    });
    stockItem('SOYA', ['reorder_level' => '100']);
    $store = userWithRole('Store Officer', ['phone' => '+2348012345678']);
    $quiet = userWithRole('Store Officer', ['phone' => null]);

    app(SendAlertNotifications::class)();
    expect($sent)->toBe([]);                                                       // switched off

    app(ResolveSettings::class)->set('notifications.sms_enabled', true);
    app(ResolveSettings::class)->set('notifications.whatsapp_enabled', true);
    stockItem('WHEAT', ['reorder_level' => '10']);
    app(SendAlertNotifications::class)();

    expect(collect($sent)->pluck(0)->all())->toBe(['sms', 'whatsapp'])->and(collect($sent)->pluck(1)->unique()->all())->toBe(['+2348012345678'])->and($sent[0][2])->toContain('Critical alert')->toContain('WHEAT')
        ->and($quiet->notifications)->not->toBeEmpty();                           // still in the bell, just no text message
});

it('lists what waits for approval only for people who may approve it', function () {
    $clerk = userWithRole('Accountant', ['name' => 'Clerk']);
    $entry = app(PostManualJournal::class)(now(), 'Owner capital', [['account_id' => finId('bank'), 'debit_minor' => 5000], ['account_id' => finId('sales_pigs'), 'credit_minor' => 5000]], $clerk);
    $boss = owner();

    $mine = app(GetPendingApprovals::class)($clerk);
    $theirs = app(GetPendingApprovals::class)($boss);

    expect($entry->status)->toBe(JournalStatus::Pending)->and($mine)->toHaveCount(1)->and($mine->first())->toMatchArray(['type' => 'Manual journal', 'reference' => $entry->number, 'mine' => true, 'raised_by' => 'Clerk'])
        ->and($theirs->first()['mine'])->toBeFalse()->and(app(GetPendingApprovals::class)(farmWorker()))->toHaveCount(0);
});

it('posts a manual journal at once at or below the approval limit and holds one above it', function () {
    $lines = fn (int $minor) => [['account_id' => finId('bank'), 'debit_minor' => $minor], ['account_id' => finId('sales_pigs'), 'credit_minor' => $minor]];

    expect(app(PostManualJournal::class)(now(), 'Default: everything waits', $lines(1))->status)->toBe(JournalStatus::Pending);

    app(ResolveSettings::class)->set('finance.journal_approval_threshold_minor', 100000);

    expect(app(PostManualJournal::class)(now(), 'Small', $lines(100000))->status)->toBe(JournalStatus::Posted)
        ->and(app(PostManualJournal::class)(now(), 'Large', $lines(100001))->status)->toBe(JournalStatus::Pending)
        ->and(finBalance('bank'))->toBe(100000);
});

it('takes no evidence for a closed task, and only a person allowed to edit tasks may assign one', function () {
    $worker = farmWorker();
    $task = newTask(['assignee' => $worker]);

    expect(fn () => app(AssignTask::class)($task, $worker, User::factory()->create()))->toThrow(DomainException::class, 'may not assign');

    app(AdvanceTask::class)->complete($task, $worker);

    expect(fn () => app(AddTaskEvidence::class)($task, 'task-evidence/late.jpg', null, $worker))->toThrow(DomainException::class, 'only be added while it is open');
});

it('lists no approvals for an inactive user, and only the modules the user may approve', function () {
    $clerk = userWithRole('Accountant');
    app(PostManualJournal::class)(now(), 'Capital', [['account_id' => finId('bank'), 'debit_minor' => 5000], ['account_id' => finId('sales_pigs'), 'credit_minor' => 5000]], $clerk);
    $budget = app(SaveBudget::class)('Plan', 2026, [['account_id' => finId('sales_pigs'), 'month' => 1, 'amount_minor' => 100]], null, null, $clerk);
    $boss = owner();

    $items = app(GetPendingApprovals::class)($boss);

    expect($items->pluck('type')->sort()->values()->all())->toBe(['Budget', 'Manual journal'])->and($items->firstWhere('type', 'Budget')['raised_by'])->toBe($clerk->name);

    $boss->update(['is_active' => false]);

    expect(app(GetPendingApprovals::class)($boss))->toHaveCount(0)->and(app(GetPendingApprovals::class)(farmWorker()))->toHaveCount(0);
});
