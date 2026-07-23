<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use App\Events\AiRequestUpdated;
use App\Models\AiRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = $this->getConversations($request);

        return Inertia::render('chat/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.parts' => ['required', 'array', 'min:1'],
            'messages.*.parts.*.type' => ['required', 'string'],
            'messages.*.parts.*.text' => ['required_with:messages.*.parts.*.type,text', 'string', 'max:10000'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $message = collect($validated['messages'])
            ->where('role', 'user')
            ->last()['parts'][0]['text'] ?? '';

        $provider = $validated['provider'] ?? null;
        $model = $validated['model'] ?? null;

        $agent = new ChatAgent(
            provider: $provider,
            model: $model,
        );

        $aiRequest = $this->logRequestStart($request, null, $provider, $model);

        $response = $agent
            ->forUser($request->user())
            ->stream($message, provider: $provider, model: $model)
            ->then(function ($response) use ($aiRequest) {
                $this->completeRequest($aiRequest, $response);
            })
            ->usingVercelDataProtocol();

        return $response;
    }

    public function show(Request $request, string $conversation): Response
    {
        $conversationModel = $request->user()
            ->conversations()
            ->where('id', $conversation)
            ->firstOrFail();

        $conversations = $this->getConversations($request);

        $conversationMessages = $conversationModel->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'parts' => [
                    ['type' => 'text', 'text' => $message->content],
                ],
            ])->all();

        return Inertia::render('chat/Index', [
            'conversations' => $conversations,
            'activeConversationId' => $conversation,
            'initialMessages' => $conversationMessages,
        ]);
    }

    public function messages(Request $request, string $conversation)
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.parts' => ['required', 'array', 'min:1'],
            'messages.*.parts.*.type' => ['required', 'string'],
            'messages.*.parts.*.text' => ['required_with:messages.*.parts.*.type,text', 'string', 'max:10000'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $request->user()
            ->conversations()
            ->where('id', $conversation)
            ->firstOrFail();

        $message = collect($validated['messages'])
            ->where('role', 'user')
            ->last()['parts'][0]['text'] ?? '';

        $provider = $validated['provider'] ?? null;
        $model = $validated['model'] ?? null;

        $agent = new ChatAgent(
            provider: $provider,
            model: $model,
        );

        $aiRequest = $this->logRequestStart($request, $conversation, $provider, $model);

        $response = $agent
            ->continue($conversation, as: $request->user())
            ->stream($message, provider: $provider, model: $model)
            ->then(function ($response) use ($aiRequest) {
                $this->completeRequest($aiRequest, $response);
            })
            ->usingVercelDataProtocol();

        return $response;
    }

    public function destroy(Request $request, string $conversation)
    {
        $user = $request->user();

        $conversationModel = $user->conversations()
            ->where('id', $conversation)
            ->firstOrFail();

        $conversationModel->delete();

        return back();
    }

    public function conversations(Request $request)
    {
        return response()->json($this->getConversations($request));
    }

    private function logRequestStart(Request $request, ?string $conversationId, ?string $provider, ?string $model): AiRequest
    {
        $aiRequest = AiRequest::create([
            'user_id' => $request->user()->id,
            'conversation_id' => $conversationId,
            'provider' => $provider,
            'model' => $model,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        AiRequestUpdated::dispatch($aiRequest);

        return $aiRequest;
    }

    private function completeRequest(AiRequest $aiRequest, $response): void
    {
        $usage = $response->usage ?? null;

        $aiRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_ms' => $aiRequest->started_at->diffInMilliseconds(now()),
            'prompt_tokens' => $usage?->promptTokens ?? null,
            'completion_tokens' => $usage?->completionTokens ?? null,
            'total_tokens' => $usage?->totalTokens ?? null,
        ]);

        $aiRequest->refresh();

        AiRequestUpdated::dispatch($aiRequest);
    }

    /**
     * @return array<int, array{id: string, title: string, updated_at: string}>
     */
    private function getConversations(Request $request): array
    {
        return $request->user()
            ->conversations()
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at->toISOString(),
            ])
            ->all();
    }
}
