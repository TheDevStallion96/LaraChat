<?php

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Illuminate\Support\Str;

function createMessage($conversation, array $attributes = [])
{
    return $conversation->messages()->create(array_merge([
        'agent' => 'App\\Ai\\Agents\\ChatAgent',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
    ], $attributes));
}

// Edit Message Tests...

test('guests cannot edit messages', function () {
    $response = $this->patchJson(
        route('chat.messages.update', ['conversation' => Str::uuid(), 'message' => Str::uuid()]),
        ['text' => 'edited'],
    );

    $response->assertStatus(401);
});

test('authenticated users can edit their messages', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => $msgId = Str::uuid(),
        'role' => 'user',
        'content' => 'Original message',
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msgId]), [
            'text' => 'Edited message',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('agent_conversation_messages', [
        'id' => $msgId,
        'content' => 'Edited message',
    ]);
});

test('editing a message deletes subsequent messages', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    $msg1Id = Str::uuid();
    $msg2Id = Str::uuid();

    createMessage($conversation, [
        'id' => $msg1Id,
        'role' => 'user',
        'content' => 'First message',
        'created_at' => now()->subMinutes(5),
    ]);

    createMessage($conversation, [
        'id' => $msg2Id,
        'role' => 'assistant',
        'content' => 'Response to first',
        'created_at' => now()->subMinutes(4),
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msg1Id]), [
            'text' => 'Edited first message',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('agent_conversation_messages', [
        'id' => $msg1Id,
        'content' => 'Edited first message',
    ]);
    $this->assertDatabaseMissing('agent_conversation_messages', [
        'id' => $msg2Id,
    ]);
});

test('edit returns updated messages', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => $msgId = Str::uuid(),
        'role' => 'user',
        'content' => 'Original',
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msgId]), [
            'text' => 'Updated',
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'messages' => [
            '*' => ['id', 'role', 'parts'],
        ],
    ]);
});

test('users cannot edit messages in other conversations', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = $user1->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => $msgId = Str::uuid(),
        'role' => 'user',
        'content' => 'Original',
    ]);

    $response = $this
        ->actingAs($user2)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msgId]), [
            'text' => 'Hacked',
        ]);

    $response->assertNotFound();
});

test('edit validates text is required', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => $msgId = Str::uuid(),
        'role' => 'user',
        'content' => 'Original',
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msgId]), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['text']);
});

test('edit validates text max length', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => $msgId = Str::uuid(),
        'role' => 'user',
        'content' => 'Original',
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('chat.messages.update', ['conversation' => $id, 'message' => $msgId]), [
            'text' => str_repeat('a', 10001),
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['text']);
});

// Regenerate Tests...

test('guests cannot regenerate', function () {
    $response = $this->postJson(
        route('chat.regenerate', ['conversation' => Str::uuid()]),
    );

    $response->assertStatus(401);
});

test('authenticated users can regenerate', function () {
    ChatAgent::fake();

    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'user',
        'content' => 'Hello',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'assistant',
        'content' => 'Hi there!',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.regenerate', $id));

    $response->assertOk();
});

test('regenerate deletes old assistant message', function () {
    ChatAgent::fake();

    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'user',
        'content' => 'Hello',
    ]);

    createMessage($conversation, [
        'id' => $assistantMsgId = Str::uuid(),
        'role' => 'assistant',
        'content' => 'Hi there!',
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('chat.regenerate', $id));

    $this->assertDatabaseMissing('agent_conversation_messages', [
        'id' => $assistantMsgId,
    ]);
});

test('users cannot regenerate in other conversations', function () {
    ChatAgent::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = $user1->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'user',
        'content' => 'Hello',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'assistant',
        'content' => 'Hi!',
    ]);

    $response = $this
        ->actingAs($user2)
        ->postJson(route('chat.regenerate', $id));

    $response->assertNotFound();
});

test('regenerate returns error when no user message exists', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => $id = Str::uuid(),
        'title' => 'Test',
    ]);

    createMessage($conversation, [
        'id' => Str::uuid(),
        'role' => 'assistant',
        'content' => 'Hi there!',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('chat.regenerate', $id));

    $response->assertStatus(422);
});
