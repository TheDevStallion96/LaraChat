<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'message' => ['required', 'string', 'max:10000'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $agent = new ChatAgent(
            provider: $validated['provider'] ?? null,
            model: $validated['model'] ?? null,
        );

        $response = $agent
            ->forUser($request->user())
            ->stream($validated['message'])
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

        return Inertia::render('chat/Index', [
            'conversations' => $conversations,
            'activeConversationId' => $conversation,
        ]);
    }

    public function messages(Request $request, string $conversation)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $request->user()
            ->conversations()
            ->where('id', $conversation)
            ->firstOrFail();

        $agent = new ChatAgent(
            provider: $validated['provider'] ?? null,
            model: $validated['model'] ?? null,
        );

        $response = $agent
            ->continue($conversation, as: $request->user())
            ->stream($validated['message'])
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
