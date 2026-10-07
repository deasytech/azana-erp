<?php

namespace App\Filament\Resources\Tasks;

use App\Domain\Tasks\Actions\AdvanceTask;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Support\DomainAction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use UnitEnum;

/** Work to be done, with a deadline and a responsible person: generated daily from farm data, or added by hand. */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Tasks & alerts';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->columnSpanFull(),
            Select::make('category')->options(AnimalResource::enumOptions(TaskCategory::cases()))->default(TaskCategory::General->value)->required(),
            Select::make('priority')->options(AnimalResource::enumOptions(TaskPriority::cases()))->default(TaskPriority::Normal->value)->required(),
            DatePicker::make('due_on')->label('Due')->default(now())->required(),
            Select::make('assigned_to')->label('Assign to')->searchable()->options(fn () => static::userOptions()),
            Select::make('responsible_role')->label('Or leave for a role')->searchable()->options(fn () => Role::orderBy('name')->pluck('name', 'name')->all())
                ->helperText('Anyone holding this role may pick the task up while nobody is assigned.'),
            Toggle::make('requires_evidence')->label('Needs a photo before it can be completed'),
        ]);
    }

    /** @return array<int, string> */
    public static function userOptions(): array
    {
        return User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('assignee'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(50)->description(fn (Task $r) => $r->responsible_role && ! $r->assigned_to ? "For {$r->responsible_role}" : null),
                TextColumn::make('category')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('priority')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        TaskPriority::Urgent => 'danger', TaskPriority::High => 'warning', default => 'gray'
                    }),
                TextColumn::make('due_on')->label('Due')->date()->sortable()->color(fn (Task $r) => $r->isOverdue() ? 'danger' : null)->description(fn (Task $r) => $r->isOverdue() ? 'Overdue' : null),
                TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        TaskStatus::Done => 'success', TaskStatus::InProgress => 'warning', TaskStatus::Cancelled => 'danger', default => 'gray'
                    }),
            ])
            ->filters([
                SelectFilter::make('category')->options(AnimalResource::enumOptions(TaskCategory::cases())),
                SelectFilter::make('status')->options(AnimalResource::enumOptions(TaskStatus::cases())),
                SelectFilter::make('assigned_to')->label('Person')->options(fn () => static::userOptions()),
            ])
            ->recordActions([ViewAction::make(), static::start(), static::complete()])
            ->defaultSort('due_on');
    }

    private static function mayWork(Task $task): bool
    {
        return $task->isOpen() && app(AdvanceTask::class)->mayWork($task, auth()->user());
    }

    public static function start(): Action
    {
        return Action::make('start')->label('Start')->icon('heroicon-o-play')
            ->visible(fn (Task $r) => $r->status === TaskStatus::Open && static::mayWork($r))
            ->action(fn (Task $record, Action $action) => DomainAction::run(fn () => app(AdvanceTask::class)->start($record, auth()->user()), $action, 'Task started'));
    }

    public static function complete(): Action
    {
        return Action::make('complete')->label('Complete')->icon('heroicon-o-check')->color('success')
            ->visible(fn (Task $r) => static::mayWork($r))
            ->schema([Textarea::make('notes')->label('What was done')])
            ->action(fn (Task $record, array $data, Action $action) => DomainAction::run(fn () => app(AdvanceTask::class)->complete($record, auth()->user(), $data['notes'] ?? null), $action, 'Task completed'));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')->label('Cancel task')->icon('heroicon-o-x-mark')->color('danger')
            ->visible(fn (Task $r) => static::mayWork($r))
            ->schema([Textarea::make('reason')->required()])
            ->action(fn (Task $record, array $data, Action $action) => DomainAction::run(fn () => app(AdvanceTask::class)->cancel($record, auth()->user(), $data['reason']), $action, 'Task cancelled'));
    }

    public static function assign(): Action
    {
        return Action::make('assign')->label('Assign')->icon('heroicon-o-user-plus')
            ->visible(fn (Task $r) => $r->isOpen() && auth()->user()->can('update', $r))
            ->schema([Select::make('user_id')->label('Person')->required()->searchable()->options(fn () => static::userOptions()), TextInput::make('note')->maxLength(255)])
            ->action(fn (Task $record, array $data, Action $action) => DomainAction::run(fn () => app(AssignTask::class)($record, (int) $data['user_id'], auth()->user(), $data['note'] ?? null), $action, 'Task assigned'));
    }

    public static function getPages(): array
    {
        return ['index' => ListTasks::route('/'), 'create' => CreateTask::route('/create'), 'view' => ViewTask::route('/{record}')];
    }
}
