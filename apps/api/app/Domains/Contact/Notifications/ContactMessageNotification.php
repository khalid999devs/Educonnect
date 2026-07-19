<?php

declare(strict_types=1);

namespace App\Domains\Contact\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A support message submitted through the public marketing contact form, sent
 * on demand to the support inbox. Delivered synchronously so the sender gets
 * immediate confirmation; the submitter's address is the reply-to.
 */
final class ContactMessageNotification extends Notification
{
    private const TOPIC_LABELS = [
        'product' => 'Product question',
        'privacy' => 'Privacy or data',
        'partnership' => 'University or mentor interest',
        'other' => 'Something else',
    ];

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $topic,
        public readonly string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New contact message: '.$this->topicLabel())
            ->replyTo($this->senderEmail, $this->senderName)
            ->line('Topic: '.$this->topicLabel())
            ->line('From: '.$this->senderName.' <'.$this->senderEmail.'>')
            ->line('Message:')
            ->line($this->body);
    }

    public function topicLabel(): string
    {
        return self::TOPIC_LABELS[$this->topic] ?? 'Something else';
    }
}
