<?php

namespace App\Filament\Resources\Animals\Pages\Concerns;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Actions\GetAnimalRestrictions;
use App\Domain\Health\Models\CullingRecord;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\Health\Models\QuarantineRecord;
use App\Filament\Support\HealthForms;
use App\Filament\Support\HealthSubmissions;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Collection;

/** Health buttons on the animal profile. Needs the NotifiesDomainErrors trait (attempt). */
trait HasHealthActions
{
    /** @var array<int|string, array{withdrawals: Collection, quarantine: ?QuarantineRecord}> */
    protected array $restrictionsByAnimal = [];

    /** What restricts the animal, computed once per request for this record (several entries read it). */
    protected function restrictions(Animal $animal): array
    {
        return $this->restrictionsByAnimal[$animal->getKey()] ??= app(GetAnimalRestrictions::class)($animal);
    }

    protected function forgetRestrictions(): void
    {
        $this->restrictionsByAnimal = [];
    }

    /** @return list<ActionGroup> */
    protected function healthActions(): array
    {
        $canRecord = fn () => $this->record->isActive() && auth()->user()->can('create', HealthEvent::class);
        $canCull = fn () => $this->record->isActive() && auth()->user()->can('approve', CullingRecord::class);

        return [ActionGroup::make([
            $this->healthAction('treat', 'Treat', 'heroicon-o-beaker', HealthForms::treatment(), HealthSubmissions::treat(...), 'Treatment recorded', $canRecord),
            $this->healthAction('vaccinate', 'Vaccinate', 'heroicon-o-shield-check', HealthForms::vaccination(), HealthSubmissions::vaccinate(...), 'Vaccination recorded', $canRecord),
            $this->healthAction('report_case', 'Report sick / injured', 'heroicon-o-exclamation-triangle', HealthForms::caseReport(), HealthSubmissions::reportCase(...), 'Health case opened', $canRecord),
            $this->healthAction('quarantine', 'Quarantine / isolate', 'heroicon-o-lock-closed', HealthForms::quarantine(), HealthSubmissions::quarantine(...), 'Animal quarantined', $canRecord),
            $this->healthAction('death', 'Record death', 'heroicon-o-x-circle', HealthForms::mortality(), HealthSubmissions::recordDeath(...), 'Death recorded', $canRecord, danger: true),
            $this->healthAction('cull', 'Cull', 'heroicon-o-trash', HealthForms::culling(), HealthSubmissions::cull(...), 'Animal culled', $canCull, danger: true),
        ])->label('Health')->icon('heroicon-o-heart')->button()];
    }

    /**
     * @param  list<Component>  $fields
     * @param  Closure(Animal, array<string, mixed>): mixed  $submit
     */
    private function healthAction(string $name, string $label, string $icon, array $fields, Closure $submit, string $message, Closure $visible, bool $danger = false): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($danger ? 'danger' : 'primary')
            ->visible($visible)
            ->requiresConfirmation($danger)
            ->schema($fields)
            ->action(function (array $data, Action $action) use ($submit, $message) {
                $this->attempt(fn () => $submit($this->record, $data), $action);
                $this->record->refresh();
                $this->forgetRestrictions();
                Notification::make()->title($message)->success()->send();
            });
    }
}
