<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RegistrationController extends Controller
{
    /**
     * Maximum registration attempts allowed per IP address.
     */
    private const MAX_ATTEMPTS_PER_IP = 5;

    /**
     * Rate limiter decay time in seconds (1 hour).
     */
    private const DECAY_SECONDS = 3600;

    /**
     * Register a new user.
     *
     * @param RegistrationRequest $request
     * @return JsonResponse
     */
    public function register(RegistrationRequest $request): JsonResponse
    {
        $ip = $request->ip();
        $ipKey = 'registration:ip:' . $ip;

        // Check IP-based rate limit
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            return response()->json([
                'status'  => false,
                'message' => "Too many registration attempts from this IP. Please try again later",
            ], 429);
        }

        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => strtolower(trim($request->email)),
                'password' => $request->password,
            ]);

            // Increment rate limiter on successful registration
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'Registration completed successfully.',
                'data'    => new UserResource($user),
                'token'   => $token,
            ], 201);

        } catch (Exception $e) {
            // Increment rate limiter on failed registration too
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);

            Log::error('Registration failed', [
                'email' => $request->email,
                'ip'    => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }
}