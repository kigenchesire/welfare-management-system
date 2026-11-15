{{-- Beneficiary name rows for registration forms. Slots follow the category limits. --}}
@php
    $old = old('beneficiaries', []);
    $slots = [
        ['parent', 'Parent 1'], ['parent', 'Parent 2'],
        ['parent_in_law', 'Parent-in-law 1'], ['parent_in_law', 'Parent-in-law 2'],
        ['spouse', 'Spouse'],
        ['child', 'Child 1'], ['child', 'Child 2'], ['child', 'Child 3'], ['child', 'Child 4'],
    ];
    $extraStart = count($slots);
@endphp
<div class="row g-2" id="beneficiaryRows">
    @foreach($slots as $i => [$cat, $label])
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text" style="min-width: 135px">{{ $label }}</span>
                <input type="hidden" name="beneficiaries[{{ $i }}][category]" value="{{ $cat }}">
                <input type="text" class="form-control" name="beneficiaries[{{ $i }}][name]" value="{{ $old[$i]['name'] ?? '' }}" maxlength="150" placeholder="Full name">
            </div>
        </div>
    @endforeach
    @foreach($old as $i => $row)
        @if($i >= $extraStart)
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text" style="min-width: 135px">Child</span>
                    <input type="hidden" name="beneficiaries[{{ $i }}][category]" value="child">
                    <input type="text" class="form-control" name="beneficiaries[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="150">
                </div>
            </div>
        @endif
    @endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addChild">+ Add another child</button>
<script>
    (function () {
        var next = {{ max($extraStart, count($old)) }};
        document.getElementById('addChild').addEventListener('click', function () {
            var col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = '<div class="input-group"><span class="input-group-text" style="min-width:135px">Child</span>' +
                '<input type="hidden" name="beneficiaries[' + next + '][category]" value="child">' +
                '<input type="text" class="form-control" name="beneficiaries[' + next + '][name]" maxlength="150" placeholder="Full name"></div>';
            document.getElementById('beneficiaryRows').appendChild(col);
            next++;
        });
    })();
</script>
