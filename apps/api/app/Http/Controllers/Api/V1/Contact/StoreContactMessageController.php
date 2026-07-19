<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Domains\Contact\Notifications\ContactMessageNotification;
use App\Http\Requests\Api\V1\Contact\StoreContactMessageRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

final class StoreContactMessageController
{
    public function __invoke(StoreContactMessageRequest $request): Response
    {
        /** @var array{name: string, email: string, topic: string, message: string} $data */
        $data = $request->validated();

        Notification::route('mail', (string) config('contact.to_address'))
            ->notify(new ContactMessageNotification(
                senderName: $data['name'],
                senderEmail: $data['email'],
                topic: $data['topic'],
                body: $data['message'],
            ));

        return response()->noContent();
    }
}
