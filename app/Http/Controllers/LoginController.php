<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    /**
     * Maximum login attempts allowed per email/IP.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Rate limiter decay time in seconds.
     */
    private const DECAY_SECONDS = 60;

    /**
     * Authenticate user and generate token.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Normalize email for consistent rate limiting
        $email = strtolower(trim($request->email));
        $key   = 'login:' . $email;

        // Check if too many attempts
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'status'  => false,
                'message' => "Too many login attempts. Please try again later.",
            ], 429);
        }

        try {
            //check if user exists
            $user = User::where('email', $email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                // Increment rate limiter on failed attempt
                RateLimiter::hit($key, self::DECAY_SECONDS);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // Clear rate limiter on successful login
            RateLimiter::clear($key);

            //generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            //return response with success message and token
            return response()->json([
                'status'  => true,
                'message' => 'Login successful.',
                'data'    => new UserResource($user),
                'token'   => $token,
            ], 200);

        } catch (Exception $e) {
            //login failed, log error message
            Log::error('Login failed', [
                'email' => $email,
                'ip'    => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            //return response with error message
            return response()->json([
                'status'  => false,
                'message' => 'An error occurred during login. Please try again later.',
            ], 500);
        }
    }
}
