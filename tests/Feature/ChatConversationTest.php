<?php

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('chat.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the chat page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('chat.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('chat/Index')
        ->has('conversations'),
    );
});

test('chat page shows empty conversations list for new user', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('chat.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('chat/Index')
        ->where('conversations', []),
    );
});

test('authenticated users can create a new conversation', function () {
    ChatAgent::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.store'), [
            'messages' => [
                ['role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Hello, AI!']]],
            ],
        ]);

    $response->assertOk();
    ChatAgent::assertPrompted('Hello, AI!');
});

test('authenticated users can send messages to existing conversations', function () {
    ChatAgent::fake();

    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => Str::uuid(),
        'title' => 'Test Conversation',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.messages', $conversation->id), [
            'messages' => [
                ['role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Follow-up message']]],
            ],
        ]);

    $response->assertOk();
    ChatAgent::assertPrompted('Follow-up message');
});

test('authenticated users can list their conversations', function () {
    $user = User::factory()->create();

    $user->conversations()->create([
        'id' => Str::uuid(),
        'title' => 'My Conversation',
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('chat.conversations'));

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['title' => 'My Conversation']);
});

test('authenticated users can delete their conversations', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'To Delete',
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('chat.destroy', $id));

    $response->assertRedirect();
    $this->assertDatabaseMissing('agent_conversations', ['id' => $id]);
});

test('users cannot access other users conversations', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = $user1->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'User 1 Conversation',
    ]);

    $response = $this
        ->actingAs($user2)
        ->get(route('chat.show', $id));

    $response->assertNotFound();
});

test('users cannot send messages to other users conversations', function () {
    ChatAgent::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = $user1->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'User 1 Conversation',
    ]);

    $response = $this
        ->actingAs($user2)
        ->postJson(route('chat.messages', $id), [
            'messages' => [
                ['role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Trying to access another user conversation']]],
            ],
        ]);

    $response->assertNotFound();
});

test('chat store validates messages is required', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.store'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['messages']);
});

test('chat store validates messages format', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.store'), [
            'messages' => [
                ['role' => 'user', 'parts' => [['type' => 'text', 'text' => str_repeat('a', 10001)]]],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['messages.0.parts.0.text']);
});
