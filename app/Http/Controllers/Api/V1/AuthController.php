<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Signs a device in (one token per device) and out. Anyone whose role needs two-factor sign-in uses the web app instead. */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'device_id' => 'required|string|max:64', 'device_name' => 'nullable|string|max:60']);
        $user = User::where('email', $data['email'])->first();

        if ($refusal = $this->refusal($user, $data['password'])) {
            return response()->json(['message' => $refusal[1], 'code' => $refusal[0]], $refusal[2]);
        }

        // One token per device: signing in again on the same device replaces the old one.
        $user->tokens()->where('name', $this->tokenName($data['device_id']))->delete();
        $token = $user->createToken($this->tokenName($data['device_id']), ['mobile'], config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration')) : null);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'user' => $this->profile($user)]);
    }

    /**
     * Why this person may not sign in, if they may not: [code, message, status].
     *
     * @return ?array{0: string, 1: string, 2: int}
     */
    private function refusal(?User $user, string $password): ?array
    {
        // The same answer whether the email or the password is wrong, and a hash is checked either way (so the time taken tells nothing).
        $known = Hash::check($password, $user?->password ?? Hash::make(str()->random(16))) && $user;

        return match (true) {
            ! $known => ['invalid_credentials', 'These credentials do not match.', 401],
            ! $user->is_active || ! $user->can('mobile.view') => ['mobile_forbidden', 'You are not allowed to use the mobile app.', 403],
            $user->requiresTwoFactor() => ['two_factor_required', 'Your role needs two-factor sign-in: please use the web app.', 403],
            default => null,
        };
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    private function tokenName(string $deviceId): string
    {
        return 'mobile:'.$deviceId;
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->getRoleNames()->all(),
            // What the app may offer: the permissions behind the twelve quick actions and the lookups.
            'permissions' => $user->getAllPermissions()->pluck('name')->filter(fn ($p) => preg_match('/\.(view|create|edit)$/', $p))->values()->all(),
        ];
    }
}
