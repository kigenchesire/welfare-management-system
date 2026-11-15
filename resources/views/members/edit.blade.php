@extends('layouts.app')
@section('title', 'Edit member')
@section('subtitle', $member->full_name)

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('members.update', $member) }}">
            @csrf @method('PUT')
            @include('members._fields', ['member' => $member, 'withDate' => true])
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save changes</button>
                <a href="{{ route('members.show', $member) }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
