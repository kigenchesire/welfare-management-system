@extends('layouts.app')
@section('title', 'Reports')
@section('subtitle', 'Every report can be filtered, searched, printed and exported to PDF or Excel.')

@section('content')
@php($groupTitles = ['members' => 'Member reports', 'welfare' => 'Welfare reports', 'financial' => 'Financial reports'])
<div class="row g-3">
    @foreach($reports as $group => $items)
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">{{ $groupTitles[$group] }}</div>
                <div class="list-group list-group-flush">
                    @foreach($items as $key => [$title, , $desc])
                        <a href="{{ route('reports.show', $key) }}" class="list-group-item list-group-item-action">
                            <div class="fw-semibold">{{ $title }}</div>
                            <div class="small text-muted">{{ $desc }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
