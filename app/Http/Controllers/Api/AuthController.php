<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function register(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'verification_status' => false,
            'account_status' => 'active',
        ]);

        $code = (string) rand(100000, 999999);

        $user->verificationCodes()->create([
            'phone_number' => $user->phone_number,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
            'used' => false,
        ]);

        return $this->successResponse([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number,
            'verification_code_for_testing' => $code,
        ], 'Account created. Verification code sent to phone.', 201);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|exists:users,phone_number',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

        if ($user->verification_status) {
            return $this->successResponse(array_merge([
                'user' => $user,
            ], $this->issueTokens($user)), 'Account already verified.');
        }

        $verificationCode = $user->verificationCodes()
            ->where('code', $request->code)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $verificationCode) {
            return $this->errorResponse('Invalid verification code.', 422);
        }

        if (now()->greaterThan($verificationCode->expires_at)) {
            return $this->errorResponse('Verification code expired.', 422);
        }

        $verificationCode->update([
            'used' => true,
        ]);

        $user->update([
            'verification_status' => true,
        ]);

        return $this->successResponse(array_merge([
            'user' => $user->fresh(),
        ], $this->issueTokens($user)), 'Account verified successfully.');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone_number' => ['Invalid login credentials.'],
            ]);
        }

        if ($user->account_status !== 'active') {
            return $this->errorResponse('Account is disabled.', 403);
        }

        if (! $user->verification_status) {
            return $this->errorResponse('Please verify your phone number first.', 403);
        }

        $user->update([
            'last_login' => now(),
        ]);

        return $this->successResponse(array_merge([
            'user' => $user,
        ], $this->issueTokens($user)), 'Logged in successfully.');
    }

    public function social(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:apple,google',
            'token' => 'required|string',
        ]);

        $profile = $request->provider === 'apple'
            ? $this->verifyAppleToken($request->token)
            : $this->verifyGoogleToken($request->token);

        $uidColumn = $request->provider === 'apple' ? 'apple_uid' : 'google_uid';

        $user = User::where($uidColumn, $profile['provider_uid'])->first();

        if (! $user && ! empty($profile['email'])) {
            $user = User::where('email', $profile['email'])->first();
        }

        if (! $user) {
            $user = User::create([
                'full_name' => $profile['name'] ?? 'DMV User',
                'email' => $profile['email'],
                'password' => Hash::make(Str::random(40)),
                $uidColumn => $profile['provider_uid'],
                'role' => 'user',
                'verification_status' => true,
                'account_status' => 'active',
            ]);
        } elseif (! $user->{$uidColumn}) {
            $user->update([$uidColumn => $profile['provider_uid']]);
        }

        if ($user->account_status !== 'active') {
            return $this->errorResponse('Account is disabled.', 403);
        }

        $user->update(['last_login' => now()]);

        return $this->successResponse(array_merge([
            'user' => $user->fresh(),
        ], $this->issueTokens($user)), 'Signed in successfully.');
    }

    public function refreshToken(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();

        if (! $currentToken || ! $currentToken->can('refresh')) {
            return $this->errorResponse('Invalid refresh token.', 401);
        }

        $user = $request->user();

        $currentToken->delete();

        return $this->successResponse($this->issueTokens($user), 'Token refreshed successfully.');
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * Issue a short-lived access token and a long-lived refresh token for the given user.
     */
    private function issueTokens(User $user): array
    {
        $accessTokenExpiry = now()->addMinutes(15);
        $refreshTokenExpiry = now()->addDays(7);

        $accessToken = $user->createToken('access-token', ['access'], $accessTokenExpiry)->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], $refreshTokenExpiry)->plainTextToken;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 15 * 60,
        ];
    }

    /**
     * Verify a Google ID token and return the provider's user profile.
     */
    private function verifyGoogleToken(string $idToken): array
    {
        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired Google identity token.'],
            ]);
        }

        $payload = $response->json();

        $clientId = config('services.google.client_id');

        if ($clientId && ($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'token' => ['Google identity token was not issued for this app.'],
            ]);
        }

        return [
            'provider_uid' => $payload['sub'],
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? null,
        ];
    }

    /**
     * Verify an Apple identity token (JWT) against Apple's published JWKS and return the profile.
     */
    private function verifyAppleToken(string $identityToken): array
    {
        $parts = explode('.', $identityToken);

        if (count($parts) !== 3) {
            throw ValidationException::withMessages([
                'token' => ['Invalid Apple identity token.'],
            ]);
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        $signature = $this->base64UrlDecode($signatureB64);

        $keys = Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->json('keys') ?? [];
        $key = collect($keys)->firstWhere('kid', $header['kid'] ?? null);

        if (! $key) {
            throw ValidationException::withMessages([
                'token' => ['Unable to verify Apple identity token.'],
            ]);
        }

        $publicKey = $this->jwkToPem($key['n'], $key['e']);

        $verified = openssl_verify(
            $headerB64 . '.' . $payloadB64,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw ValidationException::withMessages([
                'token' => ['Apple identity token signature is invalid.'],
            ]);
        }

        $clientId = config('services.apple.client_id');

        if (($payload['iss'] ?? null) !== 'https://appleid.apple.com'
            || ($payload['exp'] ?? 0) < now()->timestamp
            || ($clientId && ($payload['aud'] ?? null) !== $clientId)) {
            throw ValidationException::withMessages([
                'token' => ['Apple identity token is expired or invalid.'],
            ]);
        }

        return [
            'provider_uid' => $payload['sub'],
            'email' => $payload['email'] ?? null,
            'name' => null,
        ];
    }

    private function jwkToPem(string $n, string $e): string
    {
        $modulus = $this->encodeDerInteger($this->base64UrlDecode($n));
        $exponent = $this->encodeDerInteger($this->base64UrlDecode($e));

        $rsaPublicKey = $this->encodeDerSequence($modulus . $exponent);

        $algorithmIdentifier = pack('H*', '300d06092a864886f70d0101010500');
        $bitString = "\x03" . $this->encodeDerLength(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;

        $publicKeyInfo = $this->encodeDerSequence($algorithmIdentifier . $bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($publicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function encodeDerLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function encodeDerInteger(string $bytes): string
    {
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->encodeDerLength(strlen($bytes)) . $bytes;
    }

    private function encodeDerSequence(string $bytes): string
    {
        return "\x30" . $this->encodeDerLength(strlen($bytes)) . $bytes;
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile retrieved successfully.');
    }

    public function updateSelectedState(Request $request)
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
        ]);

        $user = $request->user();

        $user->update([
            'selected_state_id' => $request->state_id,
        ]);

        return $this->successResponse([
            'user' => $user->fresh('selectedState'),
        ], 'Selected state updated successfully.');
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'full_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $request->user()->id,
            'phone_number' => 'nullable|string|unique:users,phone_number,' . $request->user()->id,
            'preferred_language' => 'nullable|in:ar,en',
        ]);

        $user = $request->user();

        $user->update($request->only([
            'full_name',
            'email',
            'phone_number',
            'preferred_language',
        ]));

        return $this->successResponse($user->fresh(), 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect.', 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return $this->successResponse(null, 'Password changed successfully.');
    }
    public function resendVerificationCode(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string|exists:users,phone_number',
    ]);

    $user = User::where('phone_number', $request->phone_number)->first();

    if ($user->verification_status) {
        return $this->errorResponse('Account is already verified.', 422);
    }

    $recentCodesCount = $user->verificationCodes()
        ->where('created_at', '>=', now()->subHour())
        ->count();

    if ($recentCodesCount >= 3) {
        return $this->errorResponse('Maximum resend attempts reached. Try again later.', 429);
    }

    $code = (string) rand(100000, 999999);

    $user->verificationCodes()->create([
        'phone_number' => $user->phone_number,
        'code' => $code,
        'expires_at' => now()->addMinutes(5),
        'used' => false,
    ]);

    return $this->successResponse([
        'phone_number' => $user->phone_number,
        'verification_code_for_testing' => $code,
    ], 'Verification code resent successfully.');
}

public function forgotPassword(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string|exists:users,phone_number',
    ]);

    $user = User::where('phone_number', $request->phone_number)->first();

    $code = (string) rand(100000, 999999);

    $user->verificationCodes()->create([
        'phone_number' => $user->phone_number,
        'code' => $code,
        'expires_at' => now()->addMinutes(5),
        'used' => false,
    ]);

    return $this->successResponse([
        'verification_code_for_testing' => $code,
    ], 'Password reset code sent successfully.');
}

public function resetPassword(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string|exists:users,phone_number',
        'code' => 'required|digits:6',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::where('phone_number', $request->phone_number)->first();

    $verification = $user->verificationCodes()
        ->where('code', $request->code)
        ->where('used', false)
        ->latest()
        ->first();

    if (! $verification) {
        return $this->errorResponse('Invalid verification code.', 422);
    }

    if ($verification->expires_at->isPast()) {
        return $this->errorResponse('Verification code has expired.', 422);
    }

    $verification->update([
        'used' => true,
    ]);

    $user->update([
        'password' => Hash::make($request->password),
    ]);

    return $this->successResponse(null, 'Password reset successfully.');
}

public function updateProfilePhoto(Request $request)
{
    $request->validate([
        'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $user = $request->user();

    $oldPath = $user->getRawOriginal('profile_photo_url');

    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
        Storage::disk('public')->delete($oldPath);
    }

    $path = $request->file('photo')->store('profile-photos', 'public');

    $user->update([
        'profile_photo_url' => $path,
    ]);

    return $this->successResponse([
        'profile_photo_url' => $user->fresh()->profile_photo_url,
    ], 'Profile photo updated successfully.');
}

public function deleteAccount(Request $request)
{
    $user = $request->user();

    $user->tokens()->delete();

    $user->update([
        'account_status' => 'disabled',
        'email' => $user->email ? 'deleted_' . $user->id . '_' . $user->email : null,
        'phone_number' => $user->phone_number ? 'deleted_' . $user->id . '_' . $user->phone_number : null,
        'full_name' => 'Deleted User',
        'profile_photo_url' => null,
    ]);

    return $this->successResponse(null, 'Account deleted successfully.');
}
}
