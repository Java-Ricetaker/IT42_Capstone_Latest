<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        
        $user = User::where('email', $request->email)->first();

        if (!$user || $user->role !== 'patient') {
            return response()->json([
                'message' => 'Password reset is only available to patient accounts.'
            ], 403);
        }

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        


        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 500);
    }
}
