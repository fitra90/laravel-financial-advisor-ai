<div class="flex flex-col h-full bg-white">

    {{-- Context Selector Bar --}}
    <div class="flex items-center justify-between px-6 py-4 border-b bg-gray-50 shrink-0">
        <div class="flex items-center space-x-4">
            {{-- Thread History Button --}}
            <button 
                wire:click="$toggle('showThreadHistory')"
                class="p-2 hover:bg-gray-200 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Context Selector --}}
            <div class="relative">
                <select 
                    wire:model.live="context" 
                    wire:change="changeContext($event.target.value)"
                    class="px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white">
                    <option value="all">All Context</option>
                    <option value="emails">📧 Emails Only</option>
                    <option value="contacts">👥 Contacts Only</option>
                    <option value="calendar">📅 Calendar Only</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>

            <span class="text-sm text-gray-500">
                Context: <span class="font-semibold">{{ ucfirst($context) }}</span>
            </span>
        </div>

        {{-- New Thread Button --}}
        <button 
            wire:click="newThread"
            class="flex items-center space-x-2 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>New thread</span>
        </button>
    </div>

    <div class="flex flex-1 overflow-hidden">
        {{-- Thread History Sidebar --}}
        @if($showThreadHistory)
        <div class="w-80 border-r bg-gray-50 overflow-y-auto">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Conversations</h3>
                    <button 
                        wire:click="newThread"
                        class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-2">
                    @forelse($threads as $thread)
                        <div 
                            class="relative group p-3 bg-white rounded-lg cursor-pointer hover:bg-blue-50 transition 
                            {{ $thread['is_active'] ? 'ring-2 ring-blue-500 bg-blue-50' : '' }}">
                            
                            <div 
                                wire:click="switchThread({{ $thread['id'] }})"
                                class="flex-1 min-w-0">
                                {{-- Thread Title --}}
                                <div class="flex items-start justify-between">
                                    <h4 class="font-semibold text-sm truncate flex-1 pr-2">
                                        {{ $thread['title'] }}
                                    </h4>
                                    
                                    {{-- Message Count Badge --}}
                                    {{-- <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">
                                        {{ $thread['message_count'] }}
                                    </span> --}}
                                </div>
                                
                                {{-- Last Message Preview --}}
                                <p class="text-xs text-gray-500 truncate mt-1">
                                    {{ $thread['last_message_preview'] }}
                                </p>
                                
                                {{-- Metadata --}}
                                <div class="flex items-center space-x-2 mt-2 text-xs text-gray-400">
                                    {{-- Context Badge --}}
                                    @if($thread['context'] !== 'all')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">
                                            {{ ucfirst($thread['context']) }}
                                        </span>
                                    @endif
                                    
                                    <span>{{ $thread['last_message_at'] }}</span>
                                </div>
                            </div>

                            {{-- Delete Button --}}
                            <button 
                                wire:click.stop="deleteThread({{ $thread['id'] }})"
                                wire:confirm="Delete this conversation? This cannot be undone."
                                class="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p class="text-sm text-gray-500">No conversations yet</p>
                            <button 
                                wire:click="newThread"
                                class="mt-2 text-sm text-blue-600 hover:text-blue-700">
                                Start your first conversation
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        {{-- Main Chat Area --}}
        <div class="flex-1 flex flex-col">
            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
                @forelse($messages as $msg)
                    <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-3xl {{ $msg['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }} rounded-2xl px-4 py-3 shadow-sm">
                            <div class="text-sm whitespace-pre-wrap">{{ $msg['content'] }}</div>
                            
                            @if(!empty($msg['tool_calls']))
                                <div class="mt-2 pt-2 border-t {{ $msg['role'] === 'user' ? 'border-blue-500' : 'border-gray-300' }} text-xs opacity-75">
                                    <div class="font-semibold mb-1">🔧 Tools used:</div>
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
                        <p class="text-sm mt-2">Ask me anything about your emails, contacts, or calendar.</p>
                    </div>
                @endforelse

                {{-- Processing Indicator --}}
                @if($isProcessing)
                    <div class="flex justify-start">
                        <div class="bg-gray-100 rounded-2xl px-4 py-3">
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

            {{-- Mentions Dropdown --}}
            @if($showMentions)
            <div class="mx-6 mb-2 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                @foreach($mentions as $mention)
                    <button 
                        wire:click="insertMention({{ $mention['id'] }})"
                        class="w-full flex items-center space-x-3 px-4 py-2 hover:bg-gray-50 transition">
                        <img 
                            src="{{ $mention['avatar'] }}" 
                            alt="{{ $mention['name'] }}"
                            class="w-8 h-8 rounded-full">
                        <div class="text-left">
                            <div class="font-medium text-sm">{{ $mention['name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $mention['email'] }}</div>
                        </div>
                    </button>
                @endforeach
            </div>
            @endif

            {{-- Input Area --}}
        <div class="border-t bg-white p-4 shrink-0">
            {{-- Inline Mention Dropdown (shows above input) --}}
            @if($showMentionDropdown && !empty($filteredMentions))
            <div class="mb-2 bg-white border border-gray-300 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                <div class="px-3 py-2 bg-gray-50 border-b text-xs text-gray-500">
                    Type to filter, press Enter or click to select
                </div>
                @foreach(array_slice($filteredMentions, 0, 10) as $mention)
                    <button 
                        wire:click="selectMention({{ $mention['id'] }})"
                        type="button"
                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-blue-50 transition border-b border-gray-100 last:border-b-0">
                        <img 
                            src="{{ $mention['avatar'] }}" 
                            alt="{{ $mention['name'] }}"
                            class="w-10 h-10 rounded-full flex-shrink-0">
                        <div class="text-left flex-1 min-w-0">
                            <div class="font-medium text-sm truncate">{{ $mention['name'] }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $mention['email'] }}</div>
                            <div class="text-xs text-gray-400">{{ ucfirst($mention['type']) }}</div>
                        </div>
                    </button>
                @endforeach
                
                @if(count($filteredMentions) > 10)
                <div class="px-4 py-2 text-xs text-gray-500 text-center bg-gray-50">
                    Showing 10 of {{ count($filteredMentions) }} matches
                </div>
                @endif
            </div>
            @endif

            <form wire:submit.prevent="sendMessage" class="flex items-end space-x-3">
                {{-- Text Input with @ detection --}}
                <div class="flex-1 relative">
                    <textarea 
                        wire:model.live="message"
                        rows="1"
                        placeholder="Type @ to mention people, emails, or contacts..."
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        style="min-height: 48px; max-height: 200px;"
                        @if($isProcessing) disabled @endif
                        x-data="{
                            resize() {
                                $el.style.height = '48px';
                                $el.style.height = $el.scrollHeight + 'px';
                            },
                            handleKeydown(e) {
                                // Handle @ mention dropdown navigation
                                if (@js($showMentionDropdown)) {
                                    if (e.key === 'Escape') {
                                        @this.showMentionDropdown = false;
                                        e.preventDefault();
                                    }
                                }
                            }
                        }"
                        x-init="
                            $watch('$wire.message', () => resize());
                            Livewire.on('focus-input', () => {
                                $el.focus();
                            });
                        "
                        @input="resize()"
                        @keydown="handleKeydown($event)"
                    ></textarea>

                    {{-- Hint text --}}
                    <div class="absolute bottom-full left-0 mb-1 text-xs text-gray-400 pointer-events-none">
                        <span class="bg-white px-1">💡 Type <kbd class="px-1 py-0.5 bg-gray-100 rounded text-xs">@</kbd> to mention</span>
                    </div>
                </div>

                {{-- Voice Input Button --}}
                <button 
                    type="button"
                    id="voiceButton"
                    class="p-3 text-gray-500 hover:bg-gray-100 rounded-lg transition"
                    @if($isProcessing) disabled @endif>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                </button>

                {{-- Send Button --}}
                <button
                    type="submit" 
                    style="margin-bottom: 10px;"
                    class="p-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    @if($isProcessing) disabled @endif>
                    <svg wire:loading.remove="" wire:target="sendMessage" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <svg wire:loading wire:target="sendMessage" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </form>

            {{-- Context Info --}}
            <div class="text-xs text-gray-500 mt-2 text-center">
                Context: <span class="font-semibold">{{ ucfirst($context) }}</span>
                @if($context !== 'all')
                    <span class="ml-2">• Searching in {{ $context }} only</span>
                @endif
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
 
    <script>
        // Auto-scroll
        function scrollToBottom() {
            const messages = document.getElementById('chat-messages');
            if (messages) {
                messages.scrollTop = messages.scrollHeight;
            }
        }

        document.addEventListener('livewire:initialized', () => {
            scrollToBottom();
        });

        document.addEventListener('livewire:update', () => {
            setTimeout(scrollToBottom, 100);
        });

        // Voice Recognition
        const voiceButton = document.getElementById('voiceButton');
        
        if (voiceButton && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'en-US';

            recognition.onstart = function() {
                voiceButton.classList.add('bg-red-100', 'text-red-600');
                voiceButton.querySelector('svg').classList.add('animate-pulse');
            };

            recognition.onend = function() {
                voiceButton.classList.remove('bg-red-100', 'text-red-600');
                voiceButton.querySelector('svg').classList.remove('animate-pulse');
            };

            recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                @this.call('handleVoiceInput', transcript);
            };

            recognition.onerror = function(event) {
                console.error('Speech recognition error', event.error);
            };

            voiceButton.addEventListener('click', function() {
                recognition.start();
            });
        } else if (voiceButton) {
            voiceButton.style.display = 'none';
        }
    </script>
    @endpush
</div>