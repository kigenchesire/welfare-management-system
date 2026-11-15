{{-- One-time token checked by PreventDuplicateSubmission middleware. --}}
<input type="hidden" name="_submission" value="{{ \Illuminate\Support\Str::uuid() }}">
