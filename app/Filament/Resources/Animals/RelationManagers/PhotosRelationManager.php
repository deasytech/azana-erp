<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use App\Domain\Animal\Models\AnimalPhoto;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Private, randomly named, image-only, size-limited (SECURITY.md: secure file uploads).
            FileUpload::make('path')->label('Photo')->required()->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->disk(config('filesystems.default'))->directory('animal-photos')->visibility('private'),
            TextInput::make('caption')->maxLength(255),
            DatePicker::make('taken_on')->maxDate(now()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caption')->placeholder('-'),
                TextColumn::make('taken_on')->date()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->label('Uploaded'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add photo')->mutateDataUsing(fn (array $data) => $data + [
                    'disk' => config('filesystems.default'), 'uploaded_by' => auth()->id(),
                ]),
            ])
            ->recordActions([
                // Served through the ERP so access follows permissions; files are never publicly linked.
                Action::make('download')->label('Download')
                    ->action(fn (AnimalPhoto $record) => Storage::disk($record->disk)->download($record->path)),
                DeleteAction::make()->before(fn (AnimalPhoto $record) => Storage::disk($record->disk)->delete($record->path)),
            ]);
    }
}
