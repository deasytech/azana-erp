<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Domain\Tasks\Actions\GenerateDailyTasks;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'mine';
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $open = [TaskStatus::Open->value, TaskStatus::InProgress->value];

        return [
            'mine' => Tab::make('My tasks')->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_to', auth()->id())->whereIn('status', $open)),
            'open' => Tab::make('All open')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $open)),
            'overdue' => Tab::make('Overdue')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $open)->whereDate('due_on', '<', now()->toDateString())),
            'all' => Tab::make('Everything'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label("Generate today's tasks")->icon('heroicon-o-sparkles')->requiresConfirmation()
                ->modalDescription('Adds the day\'s rounds and the work the farm\'s own data calls for (vaccinations, breeding, stock, weighing, overdue payments). Running it twice adds nothing twice.')
                ->visible(fn () => auth()->user()->can('create', Task::class))
                ->action(function () {
                    $made = app(GenerateDailyTasks::class)();
                    Notification::make()->title(array_sum($made).' new tasks')->body(collect($made)->filter()->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ') ?: 'Nothing new was due.')->success()->send();
                }),
            CreateAction::make()->label('New task'),
        ];
    }
}
