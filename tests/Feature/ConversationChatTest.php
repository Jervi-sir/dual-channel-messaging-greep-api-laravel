<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    $tradesperson = User::factory()->create(['role' => 'tradesperson']);

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
