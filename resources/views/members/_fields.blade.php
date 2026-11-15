{{-- Shared member detail fields (public registration, office registration, edit). --}}
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="full_name">Full name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $member->full_name ?? '') }}" required maxlength="150">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="national_id">National ID number <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id', $member->national_id ?? '') }}" required maxlength="30">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Mobile number <span class="text-danger">*</span></label>
        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $member->phone ?? '') }}" required maxlength="20" placeholder="07XX XXX XXX">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email address <span class="text-muted small">(optional)</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $member->email ?? '') }}" maxlength="150">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="residential_address">Residential address</label>
        <input type="text" class="form-control @error('residential_address') is-invalid @enderror" id="residential_address" name="residential_address" value="{{ old('residential_address', $member->residential_address ?? '') }}" maxlength="200">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="alternative_contact">Alternative contact</label>
        <input type="text" class="form-control @error('alternative_contact') is-invalid @enderror" id="alternative_contact" name="alternative_contact" value="{{ old('alternative_contact', $member->alternative_contact ?? '') }}" maxlength="60" placeholder="Name and phone of next of kin">
    </div>
    @isset($withDate)
        <div class="col-md-6">
            <label class="form-label" for="registration_date">Date of registration <span class="text-danger">*</span></label>
            <input type="date" class="form-control @error('registration_date') is-invalid @enderror" id="registration_date" name="registration_date" value="{{ old('registration_date', isset($member) && $member?->registration_date ? $member->registration_date->format('Y-m-d') : date('Y-m-d')) }}" required>
        </div>
    @endisset
</div>
