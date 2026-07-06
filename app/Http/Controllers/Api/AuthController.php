<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Traits\ApiResponseTrait;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function register(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email|required_without:phone_number',
            'phone_number' => 'nullable|string|unique:users,phone_number|required_without:email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'verification_status' => true,
            'account_status' => 'active',
        ]);

        $token = $user->createToken('mobile-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->orWhere('phone_number', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid login credentials.'],
            ]);
        }

        if ($user->account_status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is disabled',
            ], 403);
        }

        $user->update([
            'last_login' => now(),
        ]);

        $token = $user->createToken('mobile-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile retrieved successfully');

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

    return response()->json([
        'success' => true,
        'message' => 'Selected state updated successfully',
        'data' => [
            'user' => $user->fresh('selectedState'),
        ],
    ]);
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

        return $this->successResponse($request->user(), 'Profile updated successfully');

}

public function changePassword(Request $request)
{
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Current password is incorrect.',
        ], 422);
    }

    $user->update([
        'password' => Hash::make($request->new_password),
    ]);

        return $this->successResponse($request->user(), 'Password changed successfully.');

}
}
