@extends('layouts.app')
@section('title', 'Notifications')

@section('actions')
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
        <button class="btn btn-outline-secondary">Mark all as read</button>
    </form>
@endsection

@section('content')
<div class="card">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            <a href="{{ route('notifications.read', $n) }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'notification-unread' }}">
                <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold">{{ $n->title }}</span>
                    <span class="small text-muted text-nowrap">{{ $n->created_at->format('d M Y H:i') }}</span>
                </div>
                <div class="small text-muted">{{ $n->message }}</div>
            </a>
        @empty
            <div class="empty-state">You have no notifications.</div>
        @endforelse
    </div>
    @if($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif
</div>
@endsection
