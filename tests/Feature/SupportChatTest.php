<?php

use App\Events\SupportMessageSent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportChatBroadcaster;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
    $this->editor = User::factory()->create(['role' => 'editor']);
});

it('creates a support conversation and first client message', function () {
    Event::fake([SupportMessageSent::class]);

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Need help with my account',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('conversation.client.id', $this->client->id)
        ->assertJsonPath('conversation.messages.0.body', 'Need help with my account')
        ->assertJsonPath('bootstrap.unread_count', 0);

    expect(SupportConversation::count())->toBe(1);
    expect(SupportMessage::count())->toBe(1);

    $conversation = SupportConversation::first();

    expect($conversation->client_id)->toBe($this->client->id);
    expect($conversation->last_message_sender_id)->toBe($this->client->id);
    expect($conversation->client_last_read_at)->not->toBeNull();

    Event::assertDispatched(SupportMessageSent::class, function (SupportMessageSent $event) use ($conversation) {
        $payload = $event->broadcastWith();

        return $event->conversation->is($conversation)
            && $event->message->body === 'Need help with my account'
            && $payload['conversation']['id'] === $conversation->id
            && $payload['message']['body'] === 'Need help with my account';
    });
});

it('reuses the existing support conversation for later client messages', function () {
    $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), ['body' => 'First message'])
        ->assertCreated();

    $conversationId = SupportConversation::firstOrFail()->id;

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), ['body' => 'Second message'])
        ->assertCreated()
        ->assertJsonCount(2, 'conversation.messages');

    expect(SupportConversation::count())->toBe(1);
    expect(SupportConversation::firstOrFail()->id)->toBe($conversationId);
    expect(SupportMessage::count())->toBe(2);
    expect($response->json('conversation.messages.1.body'))->toBe('Second message');
});

it('still stores the message when broadcasting fails', function () {
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('SSL certificate problem'));

    app()->instance(SupportChatBroadcaster::class, new SupportChatBroadcaster($dispatcher));

    $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Broadcast should not break chat',
        ])
        ->assertCreated()
        ->assertJsonPath('conversation.messages.0.body', 'Broadcast should not break chat');

    expect(SupportConversation::count())->toBe(1);
    expect(SupportMessage::count())->toBe(1);
});

it('lets admins list conversations, load one, reply, and mark it read', function () {
    $conversation = createSupportConversation($this->client, [
        'admin_last_read_at' => now()->subHour(),
    ]);

    createSupportMessage($conversation, $this->client, 'Hello admin');

    $this->actingAs($this->admin)
        ->get(route('admin.messages.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Messages')
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.admin_unread_count', 1)
        );

    $this->actingAs($this->admin)
        ->getJson(route('admin.messages.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.id', $conversation->id)
        ->assertJsonPath('conversation.messages.0.body', 'Hello admin');

    expect($conversation->fresh()->admin_last_read_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->postJson(route('admin.messages.messages.store', $conversation), [
            'body' => 'We can help with that.',
        ])
        ->assertCreated()
        ->assertJsonPath('conversation.messages.1.body', 'We can help with that.');

    $conversation->forceFill([
        'admin_last_read_at' => now()->subHour(),
    ])->save();

    $this->actingAs($this->admin)
        ->postJson(route('admin.messages.read', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.admin_unread_count', 0);
});

it('forbids editors from support chat routes and channels', function () {
    $conversation = createSupportConversation($this->client);

    config(['broadcasting.default' => 'pusher']);

    $this->actingAs($this->editor)
        ->get(route('support-chat.show'))
        ->assertForbidden();

    $this->actingAs($this->editor)
        ->get(route('admin.messages.index'))
        ->assertForbidden();

    $this->actingAs($this->editor)
        ->post('/broadcasting/auth', [
            'channel_name' => "private-support.conversation.{$conversation->id}",
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();

    $this->actingAs($this->editor)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-support.admin.inbox',
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('calculates unread counts using only opposite-role messages', function () {
    $conversation = createSupportConversation($this->client, [
        'client_last_read_at' => Carbon::parse('2026-04-06 09:00:00'),
        'admin_last_read_at' => Carbon::parse('2026-04-06 09:00:00'),
    ]);

    createSupportMessage($conversation, $this->client, 'Client question', Carbon::parse('2026-04-06 09:05:00'));
    createSupportMessage($conversation, $this->admin, 'Admin answer', Carbon::parse('2026-04-06 09:10:00'));

    $this->actingAs($this->admin)
        ->get(route('admin.messages.index'))
        ->assertInertia(fn ($page) => $page
            ->where('conversations.0.admin_unread_count', 1)
            ->where('conversations.0.client_unread_count', 1)
        );
});

it('reorders the admin conversation list when a new message arrives', function () {
    $olderClient = User::factory()->create(['role' => 'client']);
    $newerClient = User::factory()->create(['role' => 'client']);

    $olderConversation = createSupportConversation($olderClient);
    $newerConversation = createSupportConversation($newerClient);

    createSupportMessage($olderConversation, $olderClient, 'Older conversation', Carbon::parse('2026-04-06 08:00:00'));
    createSupportMessage($newerConversation, $newerClient, 'Newer conversation', Carbon::parse('2026-04-06 09:00:00'));

    $this->actingAs($this->admin)
        ->get(route('admin.messages.index'))
        ->assertInertia(fn ($page) => $page
            ->where('conversations.0.id', $newerConversation->id)
            ->where('conversations.1.id', $olderConversation->id)
        );

    $this->actingAs($olderClient)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Fresh follow-up',
        ])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->get(route('admin.messages.index'))
        ->assertInertia(fn ($page) => $page
            ->where('conversations.0.id', $olderConversation->id)
        );
});

function createSupportConversation(User $client, array $attributes = []): SupportConversation
{
    return SupportConversation::create([
        'client_id' => $client->id,
        ...$attributes,
    ]);
}

function createSupportMessage(SupportConversation $conversation, User $sender, string $body, ?Carbon $createdAt = null): SupportMessage
{
    $message = $conversation->messages()->create([
        'sender_id' => $sender->id,
        'body' => $body,
    ]);

    if ($createdAt) {
        $message->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    $conversation->forceFill([
        'last_message_at' => $message->created_at,
        'last_message_sender_id' => $sender->id,
    ])->save();

    return $message->fresh();
}
