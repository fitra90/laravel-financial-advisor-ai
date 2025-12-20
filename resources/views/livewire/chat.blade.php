<div class="flex flex-col h-full">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    {{-- Chat Messages --}}
    <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
        @forelse($messages as $msg)
            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-3xl {{ $msg['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }} rounded-lg px-4 py-3">
                    <div class="text-sm">
                        {{ $msg['content'] }}
                    </div>
                    
                    {{-- Show tool calls if any --}}
                    @if(!empty($msg['tool_calls']))
                        <div class="mt-2 pt-2 border-t border-gray-300 text-xs opacity-75">
                            <div class="font-semibold mb-1">Tools used:</div>
                            @foreach($msg['tool_calls'] as $tool)
                                <div>• {{ $tool['tool'] }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="text-xs mt-1 opacity-75">
                        {{ $msg['created_at'] }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-gray-500 py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <p class="text-lg font-medium">Start a conversation!</p>
                <p class="text-sm mt-2">Ask me anything about your emails or contacts.</p>
            </div>
        @endforelse

        {{-- Processing indicator --}}
        @if($isProcessing)
            <div class="flex justify-start">
                <div class="bg-gray-100 rounded-lg px-4 py-3">
                    <div class="flex items-center space-x-2">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                        </div>
                        <span class="text-sm text-gray-600">Thinking...</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Input Area --}}
    <div class="border-t bg-white p-4">
        <form wire:submit.prevent="sendMessage" class="flex space-x-4">
            <input 
                wire:model="message" 
                type="text" 
                placeholder="Ask me anything about your emails or contacts..."
                class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @if($isProcessing) disabled @endif
            >
            
            <button 
                type="submit" 
                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                @if($isProcessing) disabled @endif
            >
                <svg wire:loading.remove class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <svg wire:loading class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>

            <button 
                type="button"
                wire:click="clearChat"
                class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                @if($isProcessing) disabled @endif
            >
                Clear
            </button>
        </form>
    </div>

    {{-- Auto-scroll script --}}
    <script>
        document.addEventListener('livewire:update', () => {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });

        // Initial scroll
        window.addEventListener('load', () => {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    </script>
</div>