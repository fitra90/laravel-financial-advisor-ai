@extends('layouts.app')

@section('content')
<div class="px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Ongoing Instructions</h1>
        <p class="text-gray-600">Set rules for your AI assistant to follow automatically</p>
    </div>

    <!-- Add New Instruction -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Add New Instruction</h2>
        
        <form method="POST" action="{{ route('instructions.store') }}">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Instruction
                </label>
                <textarea 
                    name="instruction" 
                    rows="3" 
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Example: When someone emails me that is not in Hubspot, create a contact in Hubspot with a note about the email."
                    required
                >{{ old('instruction') }}</textarea>
                @error('instruction')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Trigger On
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="triggers[]" value="new_email" checked class="rounded">
                        <span class="ml-2 text-sm">New Email</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="triggers[]" value="new_contact" class="rounded">
                        <span class="ml-2 text-sm">New Contact</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="triggers[]" value="calendar_event" class="rounded">
                        <span class="ml-2 text-sm">Calendar Event</span>
                    </label>
                </div>
            </div>

            <button 
                type="submit" 
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Add Instruction
            </button>
        </form>
    </div>

    <!-- Active Instructions -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-semibold">Active Instructions ({{ $instructions->where('is_active', true)->count() }})</h2>
        </div>

        <div class="divide-y">
            @forelse($instructions as $instruction)
                <div class="p-6 {{ !$instruction->is_active ? 'bg-gray-50 opacity-60' : '' }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-gray-900 mb-2">{{ $instruction->instruction }}</p>
                            
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <span>
                                    Triggers: 
                                    @foreach($instruction->triggers ?? ['new_email'] as $trigger)
                                        <span class="inline-block bg-gray-200 rounded px-2 py-1 text-xs">{{ $trigger }}</span>
                                    @endforeach
                                </span>
                                <span>Added {{ $instruction->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 ml-4">
                            <!-- Toggle -->
                            <form method="POST" action="{{ route('instructions.toggle', $instruction) }}">
                                @csrf
                                <button 
                                    type="submit"
                                    class="px-3 py-1 text-sm rounded {{ $instruction->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $instruction->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>

                            <!-- Delete -->
                            <form method="POST" action="{{ route('instructions.destroy', $instruction) }}" onsubmit="return confirm('Are you sure?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No instructions yet. Add your first instruction above!</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Example Instructions -->
    <div class="mt-6 bg-blue-50 rounded-lg p-6">
        <h3 class="font-semibold text-blue-900 mb-2">💡 Example Instructions:</h3>
        <ul class="space-y-2 text-sm text-blue-800">
            <li>• When someone emails me that is not in Hubspot, create a contact in Hubspot with a note about the email.</li>
            <li>• When I create a contact in Hubspot, send them an email welcoming them as a client.</li>
            <li>• When I receive an email asking about meeting times, check my calendar and respond with my availability.</li>
            <li>• If someone emails asking about our services, send them our standard services overview.</li>
        </ul>
    </div>
</div>
@endsection