<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Password reset mail that links to the React SPA rather than a Blade route.
 *
 * The SPA route `/reset-password?token=...&email=...` collects the new password
 * and POSTs it to `/api/auth/reset-password`.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $token,
        private readonly string $email,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        unset($notifiable);

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        unset($notifiable);

        $minutes = (int) Config::get('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your '.Config::string('app.name').' password')
            ->greeting('Password reset requested')
            ->line('We received a request to reset the password for this account.')
            ->action('Reset password', $this->resetUrl())
            ->line(sprintf('This link expires in %d minutes.', $minutes))
            ->line('If you did not request a password reset, no further action is required.');
    }

    private function resetUrl(): string
    {
        $frontend = rtrim(Config::string('app.frontend_url'), '/');

        return $frontend.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]);
    }
}
