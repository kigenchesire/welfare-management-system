@extends('layouts.app')
@section('title', 'Website management')
@section('subtitle', 'Content for the public homepage.')

@section('actions')
    {{-- Public website disabled for now:
    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-primary">View website</a> --}}
@endsection

@section('content')
@php
    $sections = [
        'Hero section' => ['hero_heading', 'hero_description', 'hero_image'],
        'About us' => ['welcome_message', 'about_us', 'objectives', 'mission', 'vision', 'values'],
        'How the welfare works' => ['how_membership', 'how_registration', 'how_cases', 'how_contributions', 'how_prepayments', 'how_tracking'],
        'Leadership / representative' => ['rep_name', 'rep_designation', 'rep_bio', 'rep_photo'],
        'Contact, social media & footer' => ['contact_hours', 'facebook_url', 'twitter_url', 'instagram_url', 'whatsapp_number', 'footer_text'],
    ];
@endphp
<form method="POST" action="{{ route('admin.website.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @foreach($sections as $title => $keys)
        <div class="card mb-3">
            <div class="card-header">{{ $title }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($keys as $key)
                        @include('admin._setting_field', ['key' => $key, 'def' => $definitions[$key]])
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
    <p class="small text-muted">Group name, logo, colours, phone, email and address are edited in System Settings &rsaquo; General.</p>
    <button class="btn btn-primary mb-4">Save website content</button>
</form>

<div class="card">
    <div class="card-header">Additional photographs</div>
    <div class="card-body border-bottom">
        <form method="POST" action="{{ route('admin.website.images.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf @include('partials.submission')
            <div class="col-md-5">
                <label class="form-label small">Photo (JPG, PNG or WEBP, max 3 MB)</label>
                <input type="file" name="image" class="form-control" accept="image/*" required data-preview="#newImgPreview">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Caption</label>
                <input type="text" name="caption" class="form-control" maxlength="150">
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Upload</button></div>
            <div class="col-12"><img id="newImgPreview" class="img-preview d-none" alt=""></div>
        </form>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($images as $img)
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded p-2 h-100">
                        <img src="{{ Storage::disk('public')->url($img->path) }}" alt="" class="w-100 rounded mb-2" style="height: 140px; object-fit: cover;">
                        <form method="POST" action="{{ route('admin.website.images.update', $img) }}">
                            @csrf @method('PUT')
                            <input type="text" name="caption" value="{{ $img->caption }}" class="form-control form-control-sm mb-1" placeholder="Caption">
                            <div class="d-flex gap-1 align-items-center mb-1">
                                <input type="number" name="sort_order" value="{{ $img->sort_order }}" min="0" class="form-control form-control-sm" style="width: 70px" title="Order">
                                <div class="form-check form-check-inline small mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="vis{{ $img->id }}" @checked($img->is_visible)>
                                    <label class="form-check-label" for="vis{{ $img->id }}">Visible</label>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-light w-100">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.website.images.destroy', $img) }}" data-confirm="Delete this photograph?" class="mt-1">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-light text-danger w-100">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-12 empty-state">No additional photographs uploaded.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
