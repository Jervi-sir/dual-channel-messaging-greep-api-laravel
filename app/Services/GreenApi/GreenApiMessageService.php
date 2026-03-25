<?php

namespace App\Services\GreenApi;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Throwable;

class GreenApiMessageService
{
    public function __construct(protected GreenApiClient $client)
    {
    }

    public function relayOutgoingMessage(Message $message, Conversation $conversation, User $sender): Message
    {
        // if (!$this->client->isConfigured() || blank($message->message) || $message->file_path) {
        //     return $message;
        // }

        $recipient = $conversation->otherParticipantFor($sender);

        if (!$recipient || blank($recipient->phone)) {
            return $message;
        }

        $message->forceFill([
            'relay_channel' => 'whatsapp',
            'provider_status' => 'pending',
            'provider_error' => null,
        ])->save();

        try {
            $payload = $this->client->sendMessage(
                PhoneNumber::toChatId($recipient->phone),
                $this->formatOutgoingMessage($message, $sender, $conversation)
            );

            $message->forceFill([
                'provider_message_id' => $payload['idMessage'],
                'provider_status' => 'sent',
            ])->save();
        } catch (Throwable $throwable) {
            report($throwable);

            $message->forceFill([
                'provider_status' => 'failed',
                'provider_error' => $throwable->getMessage(),
            ])->save();
        }

        return $message->refresh();
    }

    public function handleWebhook(array $payload): void
    {
        match (Arr::get($payload, 'typeWebhook')) {
            'incomingMessageReceived' => $this->storeIncomingMessage($payload),
            'outgoingMessageStatus' => $this->syncOutgoingMessageStatus($payload),
            'outgoingAPIMessageReceived' => $this->syncOutgoingMessageReceived($payload),
            'outgoingMessageReceived' => $this->syncOutgoingMessageReceived($payload),
            default => null,
        };
    }

    protected function storeIncomingMessage(array $payload): void
    {
        $providerMessageId = Arr::get($payload, 'idMessage');
        $phone = PhoneNumber::fromChatId(Arr::get($payload, 'senderData.sender'));

        if (!is_string($providerMessageId) || !$phone) {
            return;
        }

        if (Message::query()->where('provider_message_id', $providerMessageId)->exists()) {
            return;
        }

        $sender = User::query()->where('phone', $phone)->first();

        if (!$sender) {
            return;
        }

        $conversation = $this->resolveInboundConversation($sender);

        if (!$conversation) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => 'whatsapp',
            'channel' => 'whatsapp',
            'message' => $this->extractIncomingMessageText($payload),
            'provider_message_id' => $providerMessageId,
            'provider_status' => 'received',
        ]);

        $message->load('sender:id,name');
        $conversation->touch();

        broadcast(new MessageSent($message))->toOthers();
    }

    protected function syncOutgoingMessageStatus(array $payload): void
    {
        $providerMessageId = Arr::get($payload, 'idMessage');

        if (!is_string($providerMessageId)) {
            return;
        }

        $message = Message::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if (!$message) {
            return;
        }

        $message->forceFill([
            'provider_status' => Arr::get($payload, 'status'),
            'provider_error' => Arr::get($payload, 'description'),
        ])->save();
    }

    protected function syncOutgoingMessageReceived(array $payload): void
    {
        $providerMessageId = Arr::get($payload, 'idMessage');

        if (!is_string($providerMessageId)) {
            return;
        }

        $message = Message::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message) {
            $message->forceFill([
                'provider_status' => 'sent',
            ])->save();

            return;
        }

        $this->storeOutgoingFromPhone($payload);
    }

    protected function storeOutgoingFromPhone(array $payload): void
    {
        $wid = Arr::get($payload, 'instanceData.wid');
        $chatId = Arr::get($payload, 'senderData.chatId');

        $senderPhone = PhoneNumber::fromChatId($wid);
        $recipientPhone = PhoneNumber::fromChatId($chatId);

        if (!$recipientPhone) {
            return;
        }

        $recipient = User::query()->where('phone', $recipientPhone)->first();
        $sender = $senderPhone
            ? User::query()->where('phone', $senderPhone)->first()
            : null;

        if (!$recipient) {
            return;
        }

        $conversation = $this->resolveOutboundConversation($recipient, $sender);

        if (!$conversation) {
            return;
        }

        $sender ??= $conversation->otherParticipantFor($recipient);

        if (!$sender) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => 'whatsapp',
            'channel' => 'whatsapp',
            'relay_channel' => 'whatsapp',
            'message' => $this->extractIncomingMessageText($payload),
            'provider_message_id' => Arr::get($payload, 'idMessage'),
            'provider_status' => 'sent',
        ]);

        $message->load('sender:id,name');
        $conversation->touch();

        broadcast(new MessageSent($message))->toOthers();
    }

    protected function resolveOutboundConversation(User $recipient, ?User $sender): ?Conversation
    {
        if ($sender) {
            return Conversation::query()
                ->betweenUsers($sender, $recipient)
                ->latest('updated_at')
                ->first();
        }

        return Conversation::query()
            ->forUser($recipient)
            ->whereHas('messages', function ($query): void {
                $query->where('relay_channel', 'whatsapp');
            })
            ->latest('updated_at')
            ->first()
            ?? Conversation::query()
                ->forUser($recipient)
                ->latest('updated_at')
                ->first();
    }

    protected function resolveInboundConversation(User $sender): ?Conversation
    {
        return Conversation::query()
            ->forUser($sender)
            ->whereHas('messages', function ($query): void {
                $query->where('relay_channel', 'whatsapp');
            })
            ->latest('updated_at')
            ->first()
            ?? Conversation::query()
                ->forUser($sender)
                ->latest('updated_at')
                ->first();
    }

    protected function extractIncomingMessageText(array $payload): string
    {
        $text = Arr::get($payload, 'messageData.textMessageData.textMessage')
            ?? Arr::get($payload, 'messageData.extendedTextMessageData.text')
            ?? Arr::get($payload, 'messageData.extendedTextMessageData.description')
            ?? Arr::get($payload, 'messageData.fileMessageData.caption')
            ?? Arr::get($payload, 'messageData.fileMessageData.fileName');

        return is_string($text) && trim($text) !== ''
            ? trim($text)
            : '[WhatsApp media message]';
    }

    protected function formatOutgoingMessage(Message $message, User $sender, Conversation $conversation): string
    {
        $recipient = $conversation->otherParticipantFor($sender);
        $recipientName = $recipient?->name ?? 'Recipient';

        return "{$sender->name} via {$recipientName} conversation:\n\n{$message->message}";
    }
}
