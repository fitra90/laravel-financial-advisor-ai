<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\AgentService;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Mention;
use Illuminate\Support\Facades\Auth;

class AdvancedChat extends Component
{
    public $message = '';
    public $messages = [];
    public $threads = [];
    public $currentThreadId = null;
    public $context = 'all';
    public $isProcessing = false;
    public $showThreadHistory = false;
    public $mentions = [];
    public $filteredMentions = [];
    public $showMentionDropdown = false;
    public $mentionSearch = '';
    public $showMentions = false;
    public $cursorPosition = 0;

    protected $listeners = ['voiceInput' => 'handleVoiceInput'];

    public function mount()
    {
        $this->loadThreads();
        $this->loadOrCreateDefaultThread();
        $this->loadMentions();
    }

    // ... (keep all existing methods: loadThreads, loadOrCreateDefaultThread, loadMessages, newThread, switchThread, etc.)
    public function newThread()
    {
        $thread = Thread::create([
            'user_id' => Auth::id(),
            'title' => 'New conversation - ' . now()->format('M d, H:i'),
            'context' => 'all',
            'last_message_at' => now(),
        ]);

        $this->currentThreadId = $thread->id;
        $this->loadMessages();
        $this->loadThreads();
    }

    public function switchThread($threadId)
    {
        $this->currentThreadId = $threadId;
        $this->loadMessages();
        $this->showThreadHistory = false;
    }
    /**
     * Load mentions from database
     */
    public function loadMentions()
    {
        $dbMentions = Mention::where('user_id', Auth::id())->get();

        if ($dbMentions->isEmpty()) {
            $this->generateMentions();
            $dbMentions = Mention::where('user_id', Auth::id())->get();
        }

        $this->mentions = $dbMentions->map(function($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'avatar' => $m->avatar_url ?? $this->generateAvatar($m->name),
                'type' => $m->type,
                'search_text' => strtolower($m->name . ' ' . $m->email),
            ];
        })->toArray();

        $this->filteredMentions = $this->mentions;
    }

    /**
     * Detect @ symbol and filter mentions in real-time
     */
    public function updatedMessage($value)
    {
        // Find if there's an @ symbol
        $lastAtPos = strrpos($value, '@');
        
        if ($lastAtPos === false) {
            $this->showMentionDropdown = false;
            $this->filteredMentions = $this->mentions;
            return;
        }

        // Get text after the last @
        $searchText = substr($value, $lastAtPos + 1);
        
        // Check if there's a space after @ (which means mention is complete)
        $spaceAfterAt = strpos($searchText, ' ');
        if ($spaceAfterAt !== false) {
            $this->showMentionDropdown = false;
            return;
        }

        // Show dropdown and filter mentions
        $this->showMentionDropdown = true;
        $this->mentionSearch = strtolower($searchText);
        
        if (empty($this->mentionSearch)) {
            $this->filteredMentions = $this->mentions;
        } else {
            $this->filteredMentions = array_filter($this->mentions, function($mention) {
                return str_contains($mention['search_text'], $this->mentionSearch);
            });
        }
    }

    /**
     * Insert selected mention into message
     */
    public function selectMention($mentionId)
    {
        $mention = collect($this->mentions)->firstWhere('id', $mentionId);
        
        if (!$mention) return;

        // Find the last @ position
        $lastAtPos = strrpos($this->message, '@');
        
        if ($lastAtPos !== false) {
            // Replace from @ to cursor with the mention
            $before = substr($this->message, 0, $lastAtPos);
            $after = substr($this->message, $lastAtPos + 1);
            
            // Remove the search text after @
            $spacePos = strpos($after, ' ');
            if ($spacePos !== false) {
                $after = substr($after, $spacePos);
            } else {
                $after = '';
            }
            
            $this->message = $before . '@' . $mention['name'] . ' ' . $after;
        }

        $this->showMentionDropdown = false;
        $this->filteredMentions = $this->mentions;
        $this->dispatch('focus-input');
    }

    /**
     * Generate mentions from contacts and emails
     */
    protected function generateMentions()
    {
        $user = Auth::user();

        // From Hubspot contacts
        // $contacts = $user->contacts->limit(50)->get();
        $contacts = $user->contacts->latest()->take(10)->get();
        foreach ($contacts as $contact) {
            if ($contact->email) {
                Mention::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'email' => $contact->email,
                    ],
                    [
                        'type' => 'contact',
                        'name' => trim($contact->first_name . ' ' . $contact->last_name),
                        'avatar_url' => null,
                        'metadata' => ['source' => 'hubspot', 'id' => $contact->id],
                    ]
                );
            }
        }

        // From email senders (unique emails)
        $emails = $user->emails
            ->select('from_email', 'from_name')
            ->distinct('from_email')
            ->limit(50)
            ->get();

        foreach ($emails as $email) {
            if ($email->from_email && !Mention::where('user_id', $user->id)->where('email', $email->from_email)->exists()) {
                Mention::create([
                    'user_id' => $user->id,
                    'type' => 'email',
                    'email' => $email->from_email,
                    'name' => $email->from_name ?? $email->from_email,
                    'avatar_url' => null,
                    'metadata' => ['source' => 'gmail'],
                ]);
            }
        }
    }

    protected function generateAvatar($name)
    {
        $initials = collect(explode(' ', $name))
            ->map(fn($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->join('');
        
        return "https://ui-avatars.com/api/?name={$initials}&size=40&background=random";
    }

    public function handleVoiceInput($text)
    {
        $this->message = $text;
    }

    public function changeContext($context)
    {
        $this->context = $context;
        
        if ($this->currentThreadId) {
            Thread::find($this->currentThreadId)->update(['context' => $context]);
        }
    }

    public function deleteThread($threadId)
    {
        $thread = Thread::find($threadId);
        
        if (!$thread || $thread->user_id !== Auth::id()) {
            return;
        }

        $threadTitle = $thread->title;
        $thread->delete();

        if ($this->currentThreadId === $threadId) {
            $this->loadOrCreateDefaultThread();
        }

        $this->loadThreads();
        session()->flash('success', "Deleted: {$threadTitle}");
    }

    public function sendMessage()
    {
        if (empty(trim($this->message))) {
            return;
        }

        $lastMessage = Message::where('user_id', Auth::id())
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($lastMessage && $lastMessage->created_at->diffInSeconds(now()) < 2) {
            session()->flash('error', 'Please wait before sending another message.');
            return;
        }

        $this->isProcessing = true;

        try {
            $thread = Thread::find($this->currentThreadId);
            
            if (!$thread) {
                throw new \Exception('Thread not found');
            }

            if ($thread->title === 'New conversation') {
                $thread->update([
                    'title' => substr($this->message, 0, 50) . (strlen($this->message) > 50 ? '...' : ''),
                ]);
            }

            Message::create([
                'user_id' => Auth::id(),
                'thread_id' => $thread->id,
                'role' => 'user',
                'content' => $this->message,
            ]);

            $agent = new AgentService(Auth::user());
            
            $messageWithContext = $this->message;
            if ($this->context !== 'all') {
                $contextInstructions = [
                    'emails' => 'Search only in emails.',
                    'contacts' => 'Search only in Hubspot contacts.',
                    'calendar' => 'Search only in calendar events.',
                ];
                $messageWithContext = ($contextInstructions[$this->context] ?? '') . ' ' . $this->message;
            }

            $response = $agent->chat($messageWithContext);

            Message::create([
                'user_id' => Auth::id(),
                'thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'metadata' => [
                    'tool_calls' => $response['tool_calls'] ?? [],
                    'context' => $this->context,
                ],
            ]);

            $thread->update(['last_message_at' => now()]);

            $this->message = '';
            $this->showMentionDropdown = false;
            $this->loadMessages();
            $this->loadThreads();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send message: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function loadThreads()
    {
        $this->threads = Thread::where('user_id', Auth::id())
            ->withCount('messages')
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function($thread) {
                // Get preview of last message
                $lastMsg = $thread->messages()->orderBy('created_at', 'desc')->first();
                
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'context' => $thread->context,
                    'last_message_preview' => $lastMsg ? substr($lastMsg->content, 0, 60) . '...' : 'No messages yet',
                    'last_message_at' => $thread->last_message_at ? $thread->last_message_at->format('M d, g:i A') : 'Just created',
                    'message_count' => $thread->messages_count,
                    'is_active' => $thread->id === $this->currentThreadId,
                ];
            })
            ->toArray();
    }

    public function loadMessages()
    {
        if (!$this->currentThreadId) {
            $this->messages = [];
            return;
        }

        $this->messages = Message::where('thread_id', $this->currentThreadId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'created_at' => $msg->created_at->format('g:i A'),
                    'created_at_full' => $msg->created_at->format('M d, Y g:i A'),
                    'tool_calls' => $msg->metadata['tool_calls'] ?? [],
                ];
            })
            ->toArray();
    }

    public function loadOrCreateDefaultThread()
    {
        // Try to get the most recent thread
        $thread = Thread::where('user_id', Auth::id())
            ->orderBy('last_message_at', 'desc')
            ->first();

        if (!$thread) {
            // No threads exist, create first one
            $thread = Thread::create([
                'user_id' => Auth::id(),
                'title' => 'New conversation',
                'context' => 'all',
                'last_message_at' => now(),
            ]);
        }

        $this->currentThreadId = $thread->id;
        $this->context = $thread->context;
        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.advanced-chat');
    }
}