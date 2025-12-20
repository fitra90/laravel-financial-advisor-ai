@extends('layouts.app')

@section('content')
<div class="px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600">Welcome back, {{ Auth::user()->name }}!</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Google Connection Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Google Account</h3>
            @if(Auth::user()->google_token)
                <div class="flex items-center text-green-600">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Connected
                </div>
                <p class="text-sm text-gray-500 mt-2">{{ Auth::user()->email }}</p>
                <p class="text-xs text-gray-400 mt-1">Gmail & Calendar access enabled</p>
            @else
                <div class="flex items-center text-yellow-600">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Not connected
                </div>
                <a href="{{ route('auth.google') }}" class="mt-3 inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    Connect Google Account
                </a>
            @endif
        </div>

        <!-- Hubspot Connection Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Hubspot CRM</h3>
            
            <div id="hubspot-status">
                @if(Auth::user()->hubspot_token)
                    <div class="flex items-center text-green-600">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Connected
                    </div>
                    <p class="text-xs text-gray-400 mt-1">CRM access enabled</p>
                @else
                    <div class="flex items-center text-yellow-600">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Not connected
                    </div>
                    <button 
                        onclick="connectHubspot()" 
                        id="hubspot-connect-btn"
                        class="mt-3 inline-block px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 text-sm">
                        Connect Hubspot
                    </button>
                @endif
            </div>

            <div id="hubspot-loading" class="hidden">
                <div class="flex items-center text-blue-600">
                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Connecting...
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Total Messages</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->messages()->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Emails Synced</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->emails()->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Hubspot Contacts</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->contacts()->count() }}</div>
        </div>
    </div>

    <!-- CHAT INTERFACE - REPLACE THE OLD SECTION -->
    <div class="bg-white rounded-lg shadow overflow-hidden" style="height: 600px;">
        <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-semibold">AI Assistant Chat</h2>
            <div class="text-sm text-gray-300">
                Powered by Gemini
            </div>
        </div>
        
        @livewire('chat')
    </div>
</div>

<script>
    let hubspotPopup = null;

    function connectHubspot() {
        document.getElementById('hubspot-status').classList.add('hidden');
        document.getElementById('hubspot-loading').classList.remove('hidden');

        const width = 600;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        hubspotPopup = window.open(
            '{{ route("auth.hubspot") }}',
            'Hubspot OAuth',
            `width=${width},height=${height},left=${left},top=${top}`
        );

        if (!hubspotPopup || hubspotPopup.closed) {
            alert('Popup blocked! Please allow popups.');
            document.getElementById('hubspot-status').classList.remove('hidden');
            document.getElementById('hubspot-loading').classList.add('hidden');
        }
    }

    window.addEventListener('message', function(event) {
        if (event.data.type === 'HUBSPOT_CONNECTED') {
            if (hubspotPopup && !hubspotPopup.closed) {
                hubspotPopup.close();
            }
            setTimeout(() => window.location.reload(), 500);
        } else if (event.data.type === 'HUBSPOT_ERROR') {
            alert('Failed: ' + event.data.error);
            document.getElementById('hubspot-status').classList.remove('hidden');
            document.getElementById('hubspot-loading').classList.add('hidden');
        }
    });
</script>
@endsection