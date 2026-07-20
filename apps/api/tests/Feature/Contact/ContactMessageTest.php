<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Domains\Contact\Notifications\ContactMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_send_a_contact_message_to_the_support_inbox(): void
    {
        Notification::fake();
        config(['contact.to_address' => 'support@educonnect.test']);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/contact', [
                'name' => 'Khalid Ahammed',
                'email' => 'student@educonnect.test',
                'topic' => 'product',
                'message' => 'Does Smart Intake read a scanned syllabus?',
            ])
            ->assertNoContent();

        Notification::assertSentOnDemand(
            ContactMessageNotification::class,
            function (ContactMessageNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $notifiable->routes['mail'] === 'support@educonnect.test'
                    && $notification->senderEmail === 'student@educonnect.test'
                    && $notification->senderName === 'Khalid Ahammed'
                    && $notification->topic === 'product'
                    && $channels === ['mail'];
            },
        );
    }

    public function test_an_invalid_submission_is_rejected_and_sends_nothing(): void
    {
        Notification::fake();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/contact', [
                'name' => '',
                'email' => 'not-an-email',
                'topic' => 'unknown-topic',
                'message' => 'too short',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        Notification::assertNothingSent();
    }

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
