<?php

namespace App\Filament\Resources\FeedFormulas\Pages;

use App\Domain\Feed\Actions\GetFormulaCost;
use App\Domain\Feed\Actions\ManageFeedFormulaVersions;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FormulaStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewFeedFormula extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = FeedFormulaResource::class;

    /** @var array<string, mixed>|null */
    protected ?array $costMemo = null;

    private function formula(): FeedFormula
    {
        assert($this->record instanceof FeedFormula);

        return $this->record;
    }

    private function cost(): array
    {
        return $this->costMemo ??= app(GetFormulaCost::class)($this->formula());
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Formula')->columns(4)->schema([
                TextEntry::make('code')->weight('bold')->copyable(),
                TextEntry::make('version')->prefix('v'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('feedType.name')->label('Feed type'),
                TextEntry::make('name'),
                TextEntry::make('process_loss_percent')->label('Process loss')->suffix('%'),
                TextEntry::make('activated_at')->label('Activated')->dateTime()->placeholder('-'),
                TextEntry::make('createdBy.name')->label('Created by')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
            ]),
            Section::make('Nutritional specification')->columns(4)->schema(
                collect(FeedFormula::NUTRITION)->map(fn (string $label, string $field) => TextEntry::make($field)->label($label)->placeholder('-'))->values()->all()
            ),
            Section::make('Cost at today\'s ingredient prices')->columns(3)->schema([
                TextEntry::make('per_kg')->label('Per kg')->state(fn () => MoneyColumn::format($this->cost()['per_kg_minor'])),
                TextEntry::make('per_bag')->label(fn () => "Per bag ({$this->cost()['bag_kg']} kg)")->state(fn () => MoneyColumn::format($this->cost()['per_bag_minor'])),
                TextEntry::make('per_tonne')->label('Per tonne')->state(fn () => MoneyColumn::format($this->cost()['per_tonne_minor'])),
                TextEntry::make('unpriced')->label('No cost known yet')->color('danger')->columnSpanFull()
                    ->visible(fn () => $this->cost()['unpriced'] !== [])->state(fn () => implode(', ', $this->cost()['unpriced'])),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $versions = app(ManageFeedFormulaVersions::class);
        $in = fn (Status ...$statuses) => in_array($this->formula()->status, $statuses, true);
        $mayEdit = fn () => auth()->user()->can('update', $this->formula());

        return [
            EditAction::make()->visible(fn () => $in(Status::Draft) && $mayEdit()),
            $this->step('activate', 'Activate', 'heroicon-o-check-badge', 'Formula activated',
                fn () => $versions->activate($this->formula()), fn () => $in(Status::Draft) && $mayEdit(), [], 'success'),
            $this->step('retire', 'Retire', 'heroicon-o-archive-box', 'Formula retired',
                fn () => $versions->retire($this->formula()), fn () => $in(Status::Active) && $mayEdit(), [], 'gray'),
            Action::make('new_version')->label('New version')->icon('heroicon-o-document-duplicate')->requiresConfirmation()
                ->modalDescription('Copies this formula into a new draft you can change.')
                ->visible(fn () => $in(Status::Active, Status::Retired) && auth()->user()->can('create', FeedFormula::class))
                ->action(function (Action $action) use ($versions) {
                    try {
                        $draft = $versions->newVersion($this->formula());
                    } catch (DomainException $e) {
                        Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
                        $action->halt();
                    }

                    Notification::make()->title("Version {$draft->version} started")->success()->send();
                    $this->redirect(FeedFormulaResource::getUrl('view', ['record' => $draft]));
                }),
        ];
    }

    protected function afterStep(): void
    {
        $this->formula()->refresh();
        $this->costMemo = null;
    }
}
