@extends('layouts.app')
@section('title', 'Audit entry #'.$log->id)

@section('actions')
    <a href="{{ route('admin.audit') }}" class="btn btn-light">Back to audit logs</a>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <dl class="dl-grid">
            <dt>Date</dt><dd>{{ $log->created_at->format('d M Y H:i:s') }}</dd>
            <dt>User</dt><dd>{{ $log->user_name }}</dd>
            <dt>Action</dt><dd><code>{{ $log->action }}</code></dd>
            <dt>Record</dt><dd>{{ $log->subject_label }}</dd>
            <dt>Description</dt><dd>{{ $log->description ?: '-' }}</dd>
            <dt>IP address</dt><dd>{{ $log->ip_address ?: '-' }}</dd>
            <dt>Browser</dt><dd class="small">{{ $log->user_agent ?: '-' }}</dd>
        </dl>
    </div>
</div>
@php($keys = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))))
@if($keys)
<div class="card">
    <div class="card-header">Changes</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Field</th><th>Previous value</th><th>New value</th></tr></thead>
            <tbody>
            @foreach($keys as $k)
                @php($fmt = fn ($v) => is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v))
                <tr>
                    <td><code>{{ $k }}</code></td>
                    <td class="small" style="white-space: pre-wrap;">{{ array_key_exists($k, $log->old_values ?? []) ? $fmt($log->old_values[$k]) : '' }}</td>
                    <td class="small" style="white-space: pre-wrap;">{{ array_key_exists($k, $log->new_values ?? []) ? $fmt($log->new_values[$k]) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
