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

        // The same answer whether the email or the password is wrong, and the hash is checked either way.
        if (! Hash::check($data['password'], $user?->password ?? Hash::make(str()->random(16))) || ! $user) {
            return response()->json(['message' => 'These credentials do not match.', 'code' => 'invalid_credentials'], 401);
        }

        if (! $user->is_active || ! $user->can('mobile.view')) {
            return response()->json(['message' => 'You are not allowed to use the mobile app.', 'code' => 'mobile_forbidden'], 403);
        }

        if ($user->requiresTwoFactor()) {
            return response()->json(['message' => 'Your role needs two-factor sign-in: please use the web app.', 'code' => 'two_factor_required'], 403);
        }

        // One token per device: signing in again on the same device replaces the old one.
        $user->tokens()->where('name', $this->tokenName($data['device_id']))->delete();
        $token = $user->createToken($this->tokenName($data['device_id']), ['mobile'], config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration')) : null);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'user' => $this->profile($user)]);
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
