<?php

namespace App\Filament\Resources\Litters\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Litter\Models\Litter;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Litters\FarrowingForm;
use App\Filament\Resources\Litters\LitterResource;
use App\Filament\Support\AnimalPicker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLitters extends ListRecords
{
    use NotifiesDomainErrors;

    protected static string $resource = LitterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('farrow')->label('Record farrowing')->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()->can('create', Litter::class))
                ->schema([AnimalPicker::sow()->required(), ...FarrowingForm::fields()])
                ->action(function (array $data, Action $action) {
                    $litter = $this->attempt(fn () => FarrowingForm::submit(Animal::findOrFail($data['sow_id']), $data), $action);
                    Notification::make()->title("Litter {$litter->litter_number} created")->success()->send();
                }),
        ];
    }
}
