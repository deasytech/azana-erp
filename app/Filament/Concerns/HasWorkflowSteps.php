<?php

namespace App\Filament\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

/**
 * One workflow button (submit, approve, reject, cancel...) on a record page: runs a domain action, shows a rule
 * violation as a notification, and refreshes the record. Pages needing more after a step override afterStep().
 */
trait HasWorkflowSteps
{
    use NotifiesDomainErrors;

    /**
     * @param  Closure(array<string, mixed>): mixed  $run
     * @param  Closure(): bool  $visible
     * @param  list<Component>  $schema
     */
    protected function step(string $name, string $label, string $icon, string $done, Closure $run, Closure $visible, array $schema = [], string $color = 'primary'): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)->requiresConfirmation()
            ->visible($visible)->schema($schema)
            ->action(function (array $data, Action $action) use ($run, $done) {
                $this->attempt(fn () => $run($data), $action);
                $this->afterStep();
                Notification::make()->title($done)->success()->send();
            });
    }

    protected function afterStep(): void
    {
        $this->record->refresh();
    }
}
