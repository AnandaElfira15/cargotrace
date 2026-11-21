@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline" type="submit">Mark all as read</button></form>
    </div>
    <div class="space-y-2">
        @forelse ($notifications as $n)
            <div class="card flex items-start justify-between gap-4 {{ $n->isRead() ? '' : 'border-l-4 border-l-navy-800' }}">
                <div>
                    <p class="font-semibold {{ $n->isRead() ? 'text-navy-600' : 'text-navy-900' }}">{{ $n->title }}</p>
                    <p class="mt-0.5 text-sm text-navy-600">{{ $n->message }}</p>
                    <p class="mt-1 text-xs text-navy-400">{{ $n->created_at->format('Y-m-d H:i') }}</p>
                </div>
                <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf
                    <button class="btn btn-outline btn-sm whitespace-nowrap" type="submit">{{ $n->shipment_id ? 'Open' : 'Mark read' }}</button>
                </form>
            </div>
        @empty
            <div class="card text-center text-sm text-navy-500">You have no notifications.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
