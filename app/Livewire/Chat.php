<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\AgentService;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class Chat extends Component
{
    public $message = '';
    public $messages = [];
    public $isProcessing = false;

    public function mount()
    {
        $this->loadMessages();
    }

    public function loadMessages()
    {
        $this->messages = Message::where('user_id', Auth::id())
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get()
            ->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'created_at' => $msg->created_at->diffForHumans(),
                    'tool_calls' => $msg->metadata['tool_calls'] ?? [],
                ];
            })
            ->toArray();
    }

    public function sendMessage()
    {
        if (empty(trim($this->message))) {
            return;
        }

        $this->isProcessing = true;

        try {
            $agent = new AgentService(Auth::user());
            $response = $agent->chat($this->message);

            // Clear input
            $this->message = '';

            // Reload messages
            $this->loadMessages();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send message: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function clearChat()
    {
        Message::where('user_id', Auth::id())->delete();
        $this->messages = [];
        session()->flash('success', 'Chat cleared!');
    }

    public function render()
    {
        return view('livewire.chat');
    }
}