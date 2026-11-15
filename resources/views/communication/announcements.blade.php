@extends('layouts.app')
@section('title', 'Announcements')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">New announcement</div>
            <div class="card-body">
                <form method="POST" action="{{ route('announcements.store') }}" data-confirm="Send this announcement to all active members?">
                    @csrf @include('partials.submission')
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="body">Message</label>
                        <textarea name="body" id="body" rows="5" class="form-control" required maxlength="600">{{ old('body') }}</textarea>
                        <div class="form-text">Keep it short if sending by SMS (about 160 characters per SMS).</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms" value="1">
                        <label class="form-check-label" for="send_sms">Also send by SMS</label>
                    </div>
                    <button class="btn btn-primary">Send announcement</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Sent announcements</div>
            <div class="list-group list-group-flush">
                @forelse($announcements as $a)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold">{{ $a->title }}</span>
                            <span class="small text-muted text-nowrap">{{ $a->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <div class="small">{{ $a->body }}</div>
                        <div class="small text-muted mt-1">{{ $a->recipients }} member(s) &middot; {{ $a->sent_sms ? 'Dashboard + SMS' : 'Dashboard only' }} &middot; by {{ $a->creator?->name }}</div>
                    </div>
                @empty
                    <div class="empty-state">No announcements yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
