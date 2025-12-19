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
                <p class="text-xs text-gray-400 mt-1">
                    Gmail & Calendar access enabled
                </p>
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
            @if(Auth::user()->hubspot_token)
                <div class="flex items-center text-green-600">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Connected
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    CRM access enabled
                </p>
            @else
                <div class="flex items-center text-yellow-600">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Not connected
                </div>
                <a href="{{ route('auth.hubspot') }}" class="mt-3 inline-block px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 text-sm">
                    Connect Hubspot
                </a>
            @endif
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Total Messages</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->messages()->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Active Tasks</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->tasks()->whereIn('status', ['pending', 'in_progress'])->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Ongoing Instructions</div>
            <div class="text-2xl font-bold text-gray-900">{{ Auth::user()->instructions()->where('is_active', true)->count() }}</div>
        </div>
    </div>

    <!-- Chat Interface Placeholder -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-800 text-white px-6 py-4">
            <h2 class="text-xl font-semibold">AI Assistant Chat</h2>
        </div>
        <div class="p-6">
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Chat Coming Soon!</h3>
                <p class="text-gray-500">
                    The AI chat interface will be ready on Day 2.<br>
                    For now, make sure both Google and Hubspot are connected above.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection