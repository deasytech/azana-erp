<?php

namespace App\Filament\Resources\MeatProductionBatches\Pages;

use App\Domain\Meat\Actions\ReverseMeatProduction;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Enums\MeatProductionStatus;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Pages\TraceExplorer;
use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewMeatProductionBatch extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = MeatProductionBatchResource::class;

    private function batch(): MeatProductionBatch
    {
        assert($this->record instanceof MeatProductionBatch);

        return $this->record;
    }

    public function infolist(Schema $schema): Schema
    {
        $money = fn (string $field) => fn () => MoneyColumn::format($this->batch()->{$field});

        return $schema->components([
            Section::make('Meat batch')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('produced_on')->date(),
                TextEntry::make('location.name')->label('Cold room'),
                TextEntry::make('input_kg')->label('From carcasses (kg)'),
                TextEntry::make('output_kg')->label('Products made (kg)'),
                TextEntry::make('waste_kg')->label('Waste (kg)'),
                TextEntry::make('producedBy.name')->label('Made by')->placeholder('-'),
                TextEntry::make('live_cost')->label('Cost of raising the pigs')->state($money('live_cost_minor')),
                TextEntry::make('other_cost')->label('Processing costs')->state($money('other_cost_minor')),
                TextEntry::make('total_cost')->label('Total cost')->state($money('total_cost_minor')),
                TextEntry::make('notes')->placeholder('-'),
                TextEntry::make('reverse_reason')->label('Reversed because')->color('danger')->visible(fn (MeatProductionBatch $b) => $b->reverse_reason !== null)->columnSpanFull(),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('trace')->label('Trace this product')->icon('heroicon-o-magnifying-glass')->color('gray')
                ->visible(fn () => TraceExplorer::canAccess())
                ->url(fn () => TraceExplorer::urlFor('meat_batch', $this->batch()->number)),
            $this->step('reverse', 'Reverse', 'heroicon-o-arrow-uturn-left', 'Meat production reversed',
                fn (array $d) => app(ReverseMeatProduction::class)($this->batch(), $d['reason']),
                fn () => $this->batch()->status === MeatProductionStatus::Produced && auth()->user()->can('approve', $this->batch()),
                [Textarea::make('reason')->required()->helperText('The meat leaves stock and the carcasses go back to hanging. Refused if any has been sold or used.')], 'danger'),
        ];
    }
}
