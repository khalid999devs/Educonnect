<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;
use SensitiveParameter;

final class ResetPasswordNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(#[SensitiveParameter] public readonly string $token)
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
            throw new LogicException('Password reset notifications require an EduConnect user.');
        }

        $frontendUrl = config('app.frontend_url', config('app.url'));

        if (! is_string($frontendUrl) || trim($frontendUrl) === '') {
            throw new LogicException('The frontend URL is not configured.');
        }

        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);
        $resetUrl = rtrim($frontendUrl, '/').'/reset-password?'.$query;
        $expiration = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your EduConnect password')
            ->line('We received a request to reset your EduConnect password.')
            ->action('Reset password', $resetUrl)
            ->line("This password reset link expires in {$expiration} minutes.")
            ->line('If you did not request a password reset, no action is required.');
    }
}
