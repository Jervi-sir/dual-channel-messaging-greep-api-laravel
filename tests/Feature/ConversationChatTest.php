<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('participants can open a conversation and receive the latest 30 messages', function () {
    $customer = User::factory()->create();
    $tradesperson = User::factory()->create();

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    foreach (range(1, 35) as $number) {
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $number % 2 === 0 ? $customer->id : $tradesperson->id,
            'type' => 'user',
            'channel' => 'in_app',
            'message' => "Message {$number}",
        ]);
    }

    $response = $this->actingAs($customer)->get(route('conversations.show', $conversation));

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('conversations/page')
            ->where('selectedConversationId', $conversation->id)
            ->has('conversations', 1)
            ->has('messages', 30)
            ->where('messages.0.message', 'Message 6')
            ->where('messages.29.message', 'Message 35')
            ->where('hasMoreMessages', true),
    );
});

test('participants can load older messages in batches of 30', function () {
    $customer = User::factory()->create();
    $tradesperson = User::factory()->create();

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    foreach (range(1, 35) as $number) {
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'type' => 'user',
            'channel' => 'in_app',
            'message' => "Message {$number}",
        ]);
    }

    $response = $this
        ->actingAs($customer)
        ->getJson(route('conversations.messages', ['conversation' => $conversation, 'before_id' => 6]));

    $response
        ->assertOk()
        ->assertJsonPath('has_more', false)
        ->assertJsonCount(5, 'messages')
        ->assertJsonPath('messages.0.message', 'Message 1')
        ->assertJsonPath('messages.4.message', 'Message 5');
});

test('participants can send a message with media', function () {
    Storage::fake('public');

    $customer = User::factory()->create();
    $tradesperson = User::factory()->create();

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    $response = $this->actingAs($customer)->post(route('messages.store', $conversation), [
        'message' => 'Here is the latest photo',
        'file' => UploadedFile::fake()->image('progress.jpg'),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message.message', 'Here is the latest photo');

    $storedMessage = Message::query()->first();

    expect($storedMessage)->not->toBeNull();
    expect($storedMessage?->conversation_id)->toBe($conversation->id);
    expect($storedMessage?->sender_id)->toBe($customer->id);
    expect($storedMessage?->file_path)->not->toBeNull();

    Storage::disk('public')->assertExists($storedMessage->file_path);
});

test('participants can relay text messages to whatsapp through green api', function () {
    config()->set('services.green_api.url', 'https://api.green-api.com');
    config()->set('services.green_api.instance_id', '7103000000');
    config()->set('services.green_api.token', 'green-api-token');

    Http::fake([
        'https://api.green-api.com/*' => Http::response([
            'idMessage' => '3EB0608D6A2901063D63',
        ]),
    ]);

    $customer = User::factory()->create([
        'phone' => '+447700900111',
    ]);
    $tradesperson = User::factory()->create([
        'phone' => '+447700900222',
    ]);

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    $response = $this->actingAs($customer)->post(route('messages.store', $conversation), [
        'message' => 'Can you confirm the booking window?',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message.relay_channel', 'whatsapp')
        ->assertJsonPath('message.provider_status', 'sent')
        ->assertJsonPath('message.provider_message_id', '3EB0608D6A2901063D63');

    $storedMessage = Message::query()->sole();

    expect($storedMessage->relay_channel)->toBe('whatsapp');
    expect($storedMessage->provider_status)->toBe('sent');
    expect($storedMessage->provider_message_id)->toBe('3EB0608D6A2901063D63');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.green-api.com/waInstance7103000000/sendMessage/green-api-token'
            && $request['chatId'] === '447700900222@c.us'
            && str_contains($request['message'], 'Can you confirm the booking window?');
    });
});

test('green api incoming webhook stores a whatsapp message for the latest conversation', function () {
    config()->set('services.green_api.webhook_token', 'Bearer integration-secret');

    $customer = User::factory()->create([
        'phone' => '+447700900111',
    ]);
    $tradesperson = User::factory()->create([
        'phone' => '+447700900222',
    ]);

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $tradesperson->id,
        'type' => 'user',
        'channel' => 'in_app',
        'relay_channel' => 'whatsapp',
        'message' => 'Please reply on WhatsApp if easier.',
    ]);

    $payload = [
        'typeWebhook' => 'incomingMessageReceived',
        'idMessage' => 'F7AEC1B7086ECDC7E6E45923F5EDB825',
        'senderData' => [
            'sender' => '447700900111@c.us',
        ],
        'messageData' => [
            'typeMessage' => 'textMessage',
            'textMessageData' => [
                'textMessage' => 'Replying from WhatsApp now.',
            ],
        ],
    ];

    $this->withHeaders([
        'Authorization' => 'Bearer integration-secret',
    ])->postJson(route('api.green-api.webhook'), $payload)->assertNoContent();

    $storedMessage = Message::query()->latest('id')->first();

    expect($storedMessage)->not->toBeNull();
    expect($storedMessage?->conversation_id)->toBe($conversation->id);
    expect($storedMessage?->sender_id)->toBe($customer->id);
    expect($storedMessage?->channel)->toBe('whatsapp');
    expect($storedMessage?->provider_message_id)->toBe('F7AEC1B7086ECDC7E6E45923F5EDB825');
    expect($storedMessage?->message)->toBe('Replying from WhatsApp now.');
});

test('green api outgoing status webhook updates a relayed message status', function () {
    config()->set('services.green_api.webhook_token', 'Bearer integration-secret');

    $customer = User::factory()->create();
    $tradesperson = User::factory()->create();

    $conversation = Conversation::create([
        'customer_id' => $customer->id,
        'tradesperson_id' => $tradesperson->id,
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $customer->id,
        'type' => 'user',
        'channel' => 'in_app',
        'relay_channel' => 'whatsapp',
        'message' => 'Status check',
        'provider_message_id' => '3EB0608D6A2901063D63',
        'provider_status' => 'sent',
    ]);

    $payload = [
        'typeWebhook' => 'outgoingMessageStatus',
        'idMessage' => '3EB0608D6A2901063D63',
        'status' => 'read',
        'description' => null,
    ];

    $this->withHeaders([
        'Authorization' => 'Bearer integration-secret',
    ])->postJson(route('api.green-api.webhook'), $payload)->assertNoContent();

    expect($message->fresh()->provider_status)->toBe('read');
});

test('users can search for people to start a conversation', function () {
    $customer = User::factory()->create(['name' => 'Chris Customer']);
    $tradesperson = User::factory()->create([
        'name' => 'Taylor Trades',
        'email' => 'taylor@example.com',
        'role' => 'tradesperson',
    ]);
    User::factory()->create(['name' => 'Another Person']);

    $response = $this
        ->actingAs($customer)
        ->getJson(route('conversations.search-users', ['query' => 'tay']));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $tradesperson->id)
        ->assertJsonPath('users.0.name', 'Taylor Trades');
});

test('users can create or reuse a conversation from search', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $tradesperson = User::factory()->create([
        'role' => 'tradesperson',
        'phone' => '+447700900222',
    ]);

    $firstResponse = $this->actingAs($customer)->postJson(route('conversations.store'), [
        'user_id' => $tradesperson->id,
    ]);

    $firstResponse->assertCreated();

    $conversation = Conversation::query()->first();

    expect($conversation)->not->toBeNull();
    expect($conversation?->customer_id)->toBe($customer->id);
    expect($conversation?->tradesperson_id)->toBe($tradesperson->id);

    $secondResponse = $this->actingAs($customer)->postJson(route('conversations.store'), [
        'user_id' => $tradesperson->id,
    ]);

    $secondResponse
        ->assertCreated()
        ->assertJsonPath('conversation_id', $conversation->id);

    expect(Conversation::query()->count())->toBe(1);
});

test('users must supply a whatsapp number before creating a conversation with a contact missing one', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $tradesperson = User::factory()->create([
        'role' => 'tradesperson',
        'phone' => null,
    ]);

    $response = $this->actingAs($customer)->postJson(route('conversations.store'), [
        'user_id' => $tradesperson->id,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    expect(Conversation::query()->count())->toBe(0);
    expect($tradesperson->fresh()->phone)->toBeNull();
});

test('users can save a missing whatsapp number while creating a conversation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $tradesperson = User::factory()->create([
        'role' => 'tradesperson',
        'phone' => null,
    ]);

    $response = $this->actingAs($customer)->postJson(route('conversations.store'), [
        'user_id' => $tradesperson->id,
        'phone' => '(447) 700-900-222',
    ]);

    $response->assertCreated();

    expect(Conversation::query()->count())->toBe(1);
    expect($tradesperson->fresh()->phone)->toBe('+447700900222');
});
