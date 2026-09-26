<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use LogicException;

final class VerifyEmailNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new LogicException('Email verification notifications require an EduConnect user.');
        }

        $expiration = (int) config('auth.verification.expire', 60);
        $signedUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes($expiration),
            [
                'user' => $notifiable->getRouteKey(),
                'hash' => hash('sha1', $notifiable->getEmailForVerification()),
            ],
        );

        /*
         * The signed endpoint only accepts stateful SPA traffic, so the email
         * links to the frontend verification page, which performs the
         * authenticated fetch against the signed API URL it carries.
         */
        $frontendUrl = config('app.frontend_url', config('app.url'));

        if (! is_string($frontendUrl) || trim($frontendUrl) === '') {
            throw new LogicException('The frontend URL is not configured.');
        }

        $verificationUrl = rtrim($frontendUrl, '/').'/verify-email?url='.urlencode($signedUrl);

        return (new MailMessage)
            ->subject('Verify your EduConnect email address')
            ->line('Verify your email address to finish securing your EduConnect account.')
            ->action('Verify email address', $verificationUrl)
            ->line("This verification link expires in {$expiration} minutes.")
            ->line('If you did not create this account, no action is required.');
    }
}
