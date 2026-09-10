<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function notice(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : view('auth.verify');
    }

    /**
     * Mark the authenticated user's email address as verified.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $expectedKey = (string) $request->user()->getKey();
        $expectedIdentification = (string) ($request->user()->identification ?? '');

        if (! hash_equals($expectedKey, (string) $id) && ! hash_equals($expectedIdentification, (string) $id)) {
            throw new AuthorizationException('Invalid user identification.');
        }

        if (! hash_equals((string) $hash, sha1($request->user()->getEmailForVerification()))) {
            throw new AuthorizationException('Invalid email verification signature.');
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard')->with('status', 'Tu correo electrónico ya ha sido verificado anteriormente.');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('dashboard')->with('status', '¡Tu correo electrónico ha sido verificado con éxito!');
    }

    /**
     * Resend the email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
