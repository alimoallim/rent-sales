<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Mail\TestMailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SystemSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json([
            'data' => [
                'app_name' => config('app.name'),
                'frontend_url' => config('app.frontend_url'),
                'mail' => [
                    'driver' => config('mail.default'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'username' => config('mail.mailers.smtp.username'),
                    'scheme' => config('mail.mailers.smtp.scheme'),
                    'is_configured' => $this->mailIsConfigured(),
                ],
                'password_reset' => [
                    'code_ttl_minutes' => (int) config('auth.password_reset_code_ttl', 15),
                ],
            ],
        ]);
    }

    public function sendTestMail(SendTestMailRequest $request): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $this->mailIsConfigured()) {
            return response()->json([
                'message' => 'SMTP is not fully configured. Set MAIL_* variables in the server environment.',
            ], 422);
        }

        try {
            Mail::to($request->string('email')->toString())->send(new TestMailMessage);
        } catch (TransportExceptionInterface $exception) {
            return response()->json([
                'message' => $this->smtpFailureMessage($exception),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to send test email: '.$exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Test email sent.',
        ]);
    }

    private function smtpFailureMessage(TransportExceptionInterface $exception): string
    {
        $detail = $exception->getMessage();

        if (str_contains($detail, '535')) {
            return 'SMTP authentication failed (535 Incorrect authentication data). '
                .'The username and password were rejected. Use the password for the exact mailbox in MAIL_USERNAME. '
                .'If admin@rasulmart.com uses Google Workspace, set MAIL_HOST=smtp.gmail.com with a Google app password—not mail.rasulmart.com. '
                .'If using cPanel mail (info@rasulmart.com), set MAIL_HOST=mail.rasulmart.com and the cPanel mailbox password. '
                .'After changing .env, run: php artisan config:clear';
        }

        if (str_contains($detail, 'Connection could not be established')) {
            return 'Could not connect to the SMTP server. Check MAIL_HOST, MAIL_PORT, and MAIL_SCHEME (smtps for port 465, null for port 587). '
                .'After changing .env, run: php artisan config:clear';
        }

        return 'SMTP error: '.$detail;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->isAdmin(), 403);
    }

    private function mailIsConfigured(): bool
    {
        if (config('mail.default') === 'log') {
            return false;
        }

        return filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'));
    }
}
