<?php

namespace App\Filament\Resources\SemenBatches\Pages;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Semen\Actions\GetSemenPrice;
use App\Domain\Semen\Actions\ManageSemenBatch;
use App\Domain\Semen\Actions\ProcessSemenBatch;
use App\Domain\Semen\Actions\RecordSemenQc;
use App\Domain\Semen\Actions\ReleaseSemenBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\SemenBatchStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use App\Filament\Support\StockForms;
use App\Support\Money;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSemenBatch extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = SemenBatchResource::class;

    private function batch(): SemenBatch
    {
        assert($this->record instanceof SemenBatch);

        return $this->record;
    }

    protected function afterStep(): void
    {
        $this->batch()->refresh();
        $this->dispatch('semen-batch-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('boar.animal_number')->label('Boar'),
                TextEntry::make('breed.name')->label('Breed')->placeholder('Not set'),
                TextEntry::make('collected_on')->date(),
                TextEntry::make('expiry_date')->date()->color(fn (SemenBatch $r) => $r->isExpired() ? 'danger' : null),
                TextEntry::make('sellable')->label('Sellable now')->state(fn (SemenBatch $r) => $r->isSellable() ? 'Yes' : 'No')->badge()->color(fn (string $state) => $state === 'Yes' ? 'success' : 'gray'),
                TextEntry::make('dose_price')->label('Price per dose')->state(fn () => ($p = $this->price()) ? Money::ofMinor($p['price_minor'], $p['currency'])->format() : 'No price set'),
                TextEntry::make('doses_produced')->label('Doses made')->placeholder('Not processed yet'),
                TextEntry::make('dose_volume_ml')->label('Dose volume (ml)')->placeholder('-'),
                TextEntry::make('diluent')->placeholder('-'),
                TextEntry::make('releasedBy.name')->label('Released by')->placeholder('-'),
                TextEntry::make('status_reason')->label('Reason')->visible(fn (SemenBatch $r) => $r->status_reason !== null)->columnSpanFull(),
            ]),
            Section::make('Collection')->columns(4)->schema([
                TextEntry::make('collection.collected_at')->label('Collected')->dateTime(),
                TextEntry::make('collection.volume_ml')->label('Volume (ml)'),
                TextEntry::make('collection.ph')->label('pH')->placeholder('-'),
                TextEntry::make('collection.technician_name')->label('Technician')->placeholder('-'),
                TextEntry::make('collection.colour')->label('Colour')->placeholder('-'),
                TextEntry::make('collection.odour')->label('Odour')->placeholder('-'),
                TextEntry::make('collection.notes')->label('Notes')->placeholder('-')->columnSpan(2),
            ]),
        ]);
    }

    /** @return ?array{price_minor: int, currency: string, price_list: string} */
    private function price(): ?array
    {
        $item = $this->batch()->breed_id ? InventoryItem::where('breed_id', $this->batch()->breed_id)->first() : null;

        return $item ? app(GetSemenPrice::class)($item) : null;
    }

    protected function getHeaderActions(): array
    {
        $in = fn (Status ...$statuses) => in_array($this->batch()->status, $statuses, true);
        $may = fn (string $ability) => auth()->user()->can($ability, SemenBatch::class);
        $manage = app(ManageSemenBatch::class);
        $settings = app(ResolveSettings::class);

        return [
            $this->step('qc', 'Record QC', 'heroicon-o-beaker', 'QC recorded',
                fn (array $d) => app(RecordSemenQc::class)($this->batch(), (string) $d['motility'], (string) $d['concentration'], (string) $d['abnormal'], $d['notes'] ?? null),
                fn () => $in(Status::PendingQc) && $may('create'),
                [
                    TextInput::make('motility')->label('Motility (%)')->numeric()->minValue(0)->maxValue(100)->step(0.01)->required()->helperText("Minimum {$settings->get('semen.min_motility_percent')}%."),
                    TextInput::make('concentration')->label('Concentration (million sperm/ml)')->numeric()->minValue(0)->step(0.01)->required()->helperText("Minimum {$settings->get('semen.min_concentration_million_per_ml')}."),
                    TextInput::make('abnormal')->label('Abnormal forms (%)')->numeric()->minValue(0)->maxValue(100)->step(0.01)->required()->helperText("Maximum {$settings->get('semen.max_abnormal_percent')}%."),
                    Textarea::make('notes'),
                ]),
            $this->step('process', 'Record doses made', 'heroicon-o-archive-box', 'Doses recorded',
                fn (array $d) => app(ProcessSemenBatch::class)($this->batch(), (int) $d['doses'], (string) $d['dose_volume'], $d['diluent'] ?? null),
                fn () => $in(Status::Passed) && $may('create'),
                [
                    TextInput::make('doses')->numeric()->integer()->minValue(1)->required()->default(fn () => app(ProcessSemenBatch::class)->maxDoses($this->batch()->load('collection')))
                        ->helperText(fn () => 'This ejaculate can be made into at most '.app(ProcessSemenBatch::class)->maxDoses($this->batch()->load('collection')).' doses.'),
                    TextInput::make('dose_volume')->label('Dose volume (ml)')->numeric()->minValue(0.1)->step(0.1)->required()->default('80'),
                    TextInput::make('diluent')->maxLength(255),
                ], 'primary'),
            $this->step('release', 'Release', 'heroicon-o-check-badge', 'Batch released into stock',
                fn (array $d) => app(ReleaseSemenBatch::class)($this->batch(), auth()->user(), (int) $d['inventory_location_id'], $d['notes'] ?? null),
                fn () => $in(Status::Passed) && $this->batch()->doses_produced !== null && $may('approve'),
                [
                    StockForms::store()->default(fn () => InventoryLocation::firstWhere('code', 'SEMEN')?->id),
                    Textarea::make('notes'),
                ], 'success'),
            $this->step('quarantine', 'Quarantine', 'heroicon-o-shield-exclamation', 'Batch quarantined',
                fn (array $d) => $manage->quarantine($this->batch(), $d['reason']),
                fn () => $in(Status::PendingQc, Status::Passed, Status::Released) && $may('update'),
                [Textarea::make('reason')->required()->helperText('A released batch cannot be sold or used while quarantined.')], 'warning'),
            $this->step('clear', 'Clear quarantine', 'heroicon-o-shield-check', 'Quarantine cleared',
                fn (array $d) => $manage->clearQuarantine($this->batch(), auth()->user(), $d['notes'] ?? null),
                fn () => $in(Status::Quarantined) && $may('approve'), [Textarea::make('notes')], 'success'),
            $this->step('destroy', 'Destroy', 'heroicon-o-trash', 'Batch destroyed',
                fn (array $d) => $manage->destroy($this->batch(), $d['reason']),
                fn () => $this->batch()->status->isOpen() && $may('approve'),
                [Textarea::make('reason')->required()->helperText('Any doses still in stock are written off.')], 'danger'),
        ];
    }
}
