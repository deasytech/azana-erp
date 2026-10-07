<?php

use App\Domain\Finance\Actions\PostManualJournal;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskStatus;
use App\Filament\Pages\Alerts;
use App\Filament\Pages\ApprovalInbox;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders the task, alert and approval pages', function () {
    $this->actingAs(owner());
    $task = newTask();

    foreach ([TaskResource::getUrl('index'), TaskResource::getUrl('create'), TaskResource::getUrl('view', ['record' => $task]), Alerts::getUrl(), ApprovalInbox::getUrl()] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('lets every role see its tasks but keeps the pages from a user with no role', function () {
    $this->actingAs(farmWorker());
    $this->get(TaskResource::getUrl('index'))->assertSuccessful();
    $this->get(Alerts::getUrl())->assertSuccessful();

    $this->actingAs(User::factory()->create());
    $this->get(TaskResource::getUrl('index'))->assertForbidden();
    $this->get(ApprovalInbox::getUrl())->assertForbidden();
});

it('creates a task from the form and hands it to a person', function () {
    $manager = userWithRole('Farm Manager');
    $worker = farmWorker();
    $this->actingAs($manager);

    Livewire::test(CreateTask::class)
        ->fillForm(['title' => 'Repair the gate', 'category' => 'general', 'priority' => 'high', 'due_on' => now()->addDays(2)->toDateString(), 'assigned_to' => $worker->id])
        ->call('create')->assertHasNoFormErrors();

    $task = Task::sole();

    expect($task->assigned_to)->toBe($worker->id)->and($task->created_by)->toBe($manager->id)->and($worker->notifications)->toHaveCount(1);

    Livewire::test(CreateTask::class)->fillForm(['title' => 'No due date', 'category' => 'general', 'priority' => 'normal', 'due_on' => null])->call('create')->assertHasFormErrors(['due_on']);
});

it('shows a person their tasks by default and lets them start and complete one', function () {
    $worker = farmWorker();
    $mine = newTask(['title' => 'Mine', 'assignee' => $worker]);
    $theirs = newTask(['title' => 'Theirs', 'assignee' => farmWorker()]);
    $this->actingAs($worker);

    Livewire::test(ListTasks::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs])
        ->callAction(TestAction::make('start')->table($mine))->assertNotified('Task started')
        ->callAction(TestAction::make('complete')->table($mine), ['notes' => 'Fixed'])->assertNotified('Task completed');

    expect($mine->fresh()->status)->toBe(TaskStatus::Done)->and($mine->fresh()->completion_notes)->toBe('Fixed');

    Livewire::test(ListTasks::class)->set('activeTab', 'all')->assertCanSeeTableRecords([$theirs])->assertActionHidden(TestAction::make('complete')->table($theirs));
});

it('refuses to complete a task that needs a photo, then accepts it once one is added', function () {
    Storage::fake('public');
    $worker = farmWorker();
    $task = newTask(['assignee' => $worker, 'requiresEvidence' => true]);
    $this->actingAs($worker);

    Livewire::test(ListTasks::class)->callAction(TestAction::make('complete')->table($task), ['notes' => 'x'])->assertNotified('Not saved');
    expect($task->fresh()->status)->toBe(TaskStatus::Open);

    Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
        ->callAction('evidence', ['file' => UploadedFile::fake()->image('pen4.jpg'), 'caption' => 'Pen 4'])->assertNotified('Photo added');

    expect($task->evidence)->toHaveCount(1)->and($task->evidence->first()->path)->toStartWith('task-evidence/');

    Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])->callAction('complete', ['notes' => 'Done'])->assertNotified('Task completed');
    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('lets a supervisor assign and cancel a task from its page', function () {
    $manager = userWithRole('Farm Manager');
    $worker = farmWorker();
    $task = newTask();
    $this->actingAs($manager);

    Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
        ->callAction('assign', ['user_id' => $worker->id, 'note' => 'Please do today'])->assertNotified('Task assigned')
        ->callAction('cancel', ['reason' => 'Done by the contractor'])->assertNotified('Task cancelled');

    expect($task->fresh())->assigned_to->toBe($worker->id)->status->toBe(TaskStatus::Cancelled);
});

it('generates the day\'s tasks from the list page', function () {
    $this->actingAs(owner());

    Livewire::test(ListTasks::class)->callAction('generate')->assertNotified('3 new tasks');
    Livewire::test(ListTasks::class)->callAction('generate')->assertNotified('0 new tasks');

    expect(Task::count())->toBe(3);
});

it('shows the alerts a person may see, and what is waiting for their approval', function () {
    stockItem('SOYA', ['reorder_level' => '100']);
    $clerk = userWithRole('Accountant');
    $entry = app(PostManualJournal::class)(now(), 'Owner capital', [['account_id' => finId('bank'), 'debit_minor' => 5000], ['account_id' => finId('sales_pigs'), 'credit_minor' => 5000]], $clerk);

    $this->actingAs(owner());
    Livewire::test(Alerts::class)->assertSee('Out of stock: Item SOYA');
    Livewire::test(ApprovalInbox::class)->assertSee($entry->number)->assertSee('Manual journal')->assertSee(JournalEntryResource::getUrl('view', ['record' => $entry]));

    $this->actingAs($clerk);
    Livewire::test(ApprovalInbox::class)->assertSee($entry->number)->assertSee('You');

    $this->actingAs(farmWorker());
    Livewire::test(ApprovalInbox::class)->assertSee('Nothing is waiting for your approval');
});

it('sends the critical alerts to people from the alerts page, for supervisors only', function () {
    stockItem('SOYA', ['reorder_level' => '100']);
    $store = userWithRole('Store Officer');

    $this->actingAs(farmWorker());
    Livewire::test(Alerts::class)->assertActionHidden('notify');

    $this->actingAs(userWithRole('Farm Manager'));
    Livewire::test(Alerts::class)->callAction('notify')->assertNotified();

    expect($store->notifications)->toHaveCount(1);
});

it('opens the approval inbox for someone who can approve in a module even without task rights', function () {
    $approver = User::factory()->create();
    $approver->assignRole(Role::create(['name' => 'Finance approver', 'guard_name' => 'web'])->givePermissionTo('finance.approve'));

    $this->actingAs($approver);
    $this->get(ApprovalInbox::getUrl())->assertSuccessful();
    $this->get(TaskResource::getUrl('index'))->assertForbidden();
});
