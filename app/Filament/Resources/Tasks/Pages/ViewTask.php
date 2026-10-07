<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Domain\Tasks\Actions\AddTaskEvidence;
use App\Domain\Tasks\Actions\AdvanceTask;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Support\DomainAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('evidence')->label('Add photo')->icon('heroicon-o-camera')
                ->visible(fn () => $this->record->isOpen() && app(AdvanceTask::class)->mayWork($this->record, auth()->user()))
                ->schema([
                    FileUpload::make('file')->label('Photo')->image()->disk('public')->directory('task-evidence')->maxSize(5120)->required(),
                    TextInput::make('caption')->maxLength(255),
                ])
                ->action(fn (array $data, Action $action) => DomainAction::run(fn () => app(AddTaskEvidence::class)($this->record, $data['file'], $data['caption'] ?? null, auth()->user()), $action, 'Photo added')),
            TaskResource::assign(), TaskResource::start(), TaskResource::complete(), TaskResource::cancel(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('number'), TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()), TextEntry::make('priority')->formatStateUsing(fn ($state) => $state->label()),
            TextEntry::make('title')->columnSpanFull(), TextEntry::make('description')->placeholder('-')->columnSpanFull(),
            TextEntry::make('category')->formatStateUsing(fn ($state) => $state->label()), TextEntry::make('due_on')->label('Due')->date(),
            TextEntry::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
            TextEntry::make('responsible_role')->label('Role')->placeholder('-'), TextEntry::make('requires_evidence')->label('Photo required')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            TextEntry::make('completedBy.name')->label('Completed by')->placeholder('-'), TextEntry::make('completed_at')->dateTime()->placeholder('-'),
            TextEntry::make('completion_notes')->placeholder('-')->columnSpanFull(), TextEntry::make('cancel_reason')->placeholder('-')->columnSpanFull(),
            RepeatableEntry::make('assignments')->label('Assignment history')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('user.name')->label('Person'), TextEntry::make('assignedBy.name')->label('By')->placeholder('-'), TextEntry::make('created_at')->dateTime(),
            ]),
            RepeatableEntry::make('evidence')->label('Photos')->columnSpanFull()->columns(3)->schema([
                ImageEntry::make('path')->disk('public')->label(''), TextEntry::make('caption')->placeholder('-'), TextEntry::make('uploadedBy.name')->label('By')->placeholder('-'),
            ]),
        ]);
    }
}
