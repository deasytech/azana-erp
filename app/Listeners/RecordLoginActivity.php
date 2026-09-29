<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/** Auto-discovered by Laravel from the type-hinted handle* methods; do not also register manually. */
class RecordLoginActivity
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        $ip = request()->ip();

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
        }

        $this->record('login', $user?->getAuthIdentifier(), $user?->email);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user?->getAuthIdentifier(), $event->user?->email);
    }

    public function handleFailed(Failed $event): void
    {
        // Only the identifier is stored - never the submitted password.
        $this->record('failed', $event->user?->getAuthIdentifier(), $event->credentials['email'] ?? null);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record('lockout', null, $event->request->input('email'));
    }

    private function record(string $event, int|string|null $userId, ?string $email): void
    {
        $request = request();

        LoginActivity::create([
            'user_id' => $userId,
            'email' => $email,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ]);
    }
}
