{{-- Renders one setting input from its definition: [$group, $type, $label, $default] --}}
@php([$group, $type, $label] = $def)
@php($value = old($key, $s[$key] ?? ''))
<div class="{{ in_array($type, ['textarea']) ? 'col-12' : 'col-md-6' }}">
    @if($type === 'boolean')
        <div class="form-check form-switch mt-md-4">
            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" @checked((bool) (int) $value)>
            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
        </div>
    @else
        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
        @switch($type)
            @case('textarea')
                <textarea name="{{ $key }}" id="{{ $key }}" class="form-control" rows="3">{{ $value }}</textarea>
                @break
            @case('number')
                <input type="number" step="0.01" min="0" name="{{ $key }}" id="{{ $key }}" class="form-control" value="{{ $value }}">
                @break
            @case('color')
                <div class="d-flex gap-2">
                    <input type="color" class="form-control form-control-color" value="{{ $value ?: '#1f6f5c' }}" oninput="document.getElementById('{{ $key }}').value = this.value">
                    <input type="text" name="{{ $key }}" id="{{ $key }}" class="form-control" value="{{ $value }}" pattern="#[0-9a-fA-F]{6}">
                </div>
                @break
            @case('password')
                <input type="password" name="{{ $key }}" id="{{ $key }}" class="form-control" autocomplete="new-password" placeholder="{{ $value ? 'Saved - leave blank to keep' : '' }}">
                @break
            @case('select')
                <select name="{{ $key }}" id="{{ $key }}" class="form-select">
                    @foreach(\App\Support\SettingDefinitions::SMS_PROVIDERS as $k => $optLabel)
                        <option value="{{ $k }}" @selected($value === $k)>{{ $optLabel }}</option>
                    @endforeach
                </select>
                @break
            @case('image')
                @php($url = \App\Support\Settings::image($key))
                <input type="file" name="{{ $key }}" id="{{ $key }}" class="form-control" accept="image/*" data-preview="#preview_{{ $key }}">
                <div class="mt-2 d-flex align-items-center gap-3">
                    <img id="preview_{{ $key }}" src="{{ $url }}" alt="" class="img-preview {{ $url ? '' : 'd-none' }}">
                    @if($url)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_{{ $key }}" id="remove_{{ $key }}" value="1">
                            <label class="form-check-label small" for="remove_{{ $key }}">Remove</label>
                        </div>
                    @endif
                </div>
                @break
            @default
                <input type="text" name="{{ $key }}" id="{{ $key }}" class="form-control" value="{{ $value }}">
        @endswitch
    @endif
</div>
