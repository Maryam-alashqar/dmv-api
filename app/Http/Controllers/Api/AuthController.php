<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            $token = $user->createToken('mobile-token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Account already verified.');
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

        $token = $user->createToken('mobile-token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->fresh(),
            'token' => $token,
        ], 'Account verified successfully.');
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

        $token = $user->createToken('mobile-token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
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

    if ($user->profile_photo_url) {
        $oldPath = str_replace('/storage/', '', $user->profile_photo_url);

        if (Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    $path = $request->file('photo')->store('profile-photos', 'public');

    $user->update([
        'profile_photo_url' => '/storage/' . $path,
    ]);

    return $this->successResponse([
        'profile_photo_url' => $user->profile_photo_url,
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
