@php
    use App\Support\Settings;
    $logo = Settings::image('logo');
    $hero = Settings::image('hero_image');
    $repPhoto = Settings::image('rep_photo');
    $lines = fn ($key) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($s[$key] ?? '')))));
    $how = [
        ['how_membership', 'Membership'],
        ['how_registration', 'Registration'],
        ['how_cases', 'Welfare cases'],
        ['how_contributions', 'Contributions'],
        ['how_prepayments', 'Prepayments'],
        ['how_tracking', 'Payment tracking'],
    ];
    $socials = array_filter([
        'facebook' => $s['facebook_url'],
        'twitter-x' => $s['twitter_url'],
        'instagram' => $s['instagram_url'],
        'whatsapp' => $s['whatsapp_number'] ? 'https://wa.me/'.preg_replace('/\D/', '', $s['whatsapp_number']) : null,
    ]);
    $rates = [
        'parent' => 'Parent', 'parent_in_law' => 'Parent-in-law', 'self' => 'Member', 'spouse' => 'Spouse', 'child' => 'Child',
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <meta name="description" content="{{ $s['description'] }}">
    <style>
        html { scroll-behavior: smooth; scroll-padding-top: 70px; }
        body { background: #fff; }
        .site-nav { background: #fff; border-bottom: 1px solid #eef1f0; }
        .site-nav .navbar-brand img { height: 40px; width: auto; }
        .site-nav .nav-link { color: #33413d; font-weight: 500; }
        .site-nav .nav-link:hover { color: var(--brand); }
        .hero { background: linear-gradient(135deg, var(--brand-soft), #fff 70%); padding: 4.5rem 0 4rem; }
        .hero h1 { font-weight: 700; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1.15; }
        .hero-img { width: 100%; max-height: 380px; object-fit: cover; border-radius: 1rem; }
        .hero-placeholder { height: 320px; border-radius: 1rem; background: var(--brand); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; font-weight: 700; }
        section.block { padding: 4rem 0; }
        .section-label { color: var(--brand); text-transform: uppercase; font-size: .78rem; letter-spacing: .08em; font-weight: 700; }
        .section-title { font-weight: 700; font-size: 1.75rem; margin-bottom: 1rem; }
        .step-num { width: 34px; height: 34px; border-radius: 50%; background: var(--brand-soft); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .rep-photo { width: 220px; height: 220px; object-fit: cover; border-radius: 50%; border: 6px solid var(--brand-soft); }
        .gallery img { width: 100%; height: 200px; object-fit: cover; border-radius: .6rem; }
        .site-footer { background: #17201e; color: #c6d0cd; }
        .site-footer a { color: #e3ebe8; text-decoration: none; }
        .site-footer a:hover { color: #fff; }
        .value-pill { display: inline-block; background: var(--brand-soft); color: var(--brand); border-radius: 2rem; padding: .35rem .9rem; margin: 0 .35rem .5rem 0; font-weight: 500; }
    </style>
</head>
<body>

{{-- A. Header --}}
<nav class="navbar navbar-expand-lg site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="#top">
            @if($logo)<img src="{{ $logo }}" alt="{{ $s['group_name'] }} logo">@endif
            <span>{{ $s['group_name'] }}</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="siteNav">
            <ul class="navbar-nav ms-auto me-lg-3">
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#how">How it works</a></li>
                <li class="nav-item"><a class="nav-link" href="#leadership">Leadership</a></li>
                <li class="nav-item"><a class="nav-link" href="#register">Join</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">My dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Member login</a>
            @endauth
        </div>
    </div>
</nav>

{{-- B. Hero --}}
<header class="hero" id="top">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="section-label mb-2">{{ $s['group_name'] }}</div>
                <h1 class="mb-3">{{ $s['hero_heading'] }}</h1>
                <p class="lead text-muted mb-4">{{ $s['hero_description'] }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#register" class="btn btn-primary btn-lg">Become a member</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">Member login</a>
                </div>
            </div>
            <div class="col-lg-6">
                @if($hero)
                    <img src="{{ $hero }}" alt="{{ $s['group_name'] }}" class="hero-img shadow-sm">
                @elseif($repPhoto)
                    <img src="{{ $repPhoto }}" alt="{{ $s['rep_name'] }}" class="hero-img shadow-sm">
                @else
                    <div class="hero-placeholder">{{ $s['short_name'] ?: mb_substr($s['group_name'], 0, 2) }}</div>
                @endif
            </div>
        </div>
    </div>
</header>

{{-- C. About --}}
<section class="block" id="about">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6">
                <div class="section-label">About us</div>
                <h2 class="section-title">{{ $s['welcome_message'] }}</h2>
                <p class="text-muted" style="white-space: pre-line">{{ $s['about_us'] }}</p>
                @if($s['registration_info'])<p class="small text-muted mb-0">{{ $s['registration_info'] }}</p>@endif
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="card h-100"><div class="card-body">
                            <h3 class="h6 fw-bold text-brand">Our mission</h3>
                            <p class="mb-0 text-muted">{{ $s['mission'] }}</p>
                        </div></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card h-100"><div class="card-body">
                            <h3 class="h6 fw-bold text-brand">Our vision</h3>
                            <p class="mb-0 text-muted">{{ $s['vision'] }}</p>
                        </div></div>
                    </div>
                    @if($objectives = $lines('objectives'))
                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h3 class="h6 fw-bold text-brand">Purpose and objectives</h3>
                                <ul class="mb-0 text-muted ps-3">
                                    @foreach($objectives as $o)<li>{{ $o }}</li>@endforeach
                                </ul>
                            </div></div>
                        </div>
                    @endif
                    @if($values = $lines('values'))
                        <div class="col-12">
                            <h3 class="h6 fw-bold mb-2">Our values</h3>
                            @foreach($values as $v)<span class="value-pill">{{ $v }}</span>@endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- D. How it works --}}
<section class="block bg-light" id="how">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-label">How the welfare works</div>
            <h2 class="section-title">Simple, fair and transparent</h2>
        </div>
        <div class="row g-3">
            @foreach($how as $i => [$key, $title])
                @if($s[$key])
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100"><div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="step-num">{{ $i + 1 }}</span>
                                <h3 class="h6 fw-bold mb-0">{{ $title }}</h3>
                            </div>
                            <p class="text-muted mb-0">{{ $s[$key] }}</p>
                        </div></div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body">
                    <h3 class="h6 fw-bold mb-3">Contribution per member</h3>
                    <table class="table table-sm mb-0">
                        @foreach($rates as $cat => $label)
                            <tr><td>{{ $label }} case</td><td class="text-end amount fw-semibold">{{ money(Settings::contributionFor($cat)) }}</td></tr>
                        @endforeach
                    </table>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body">
                    <h3 class="h6 fw-bold mb-3">How to pay: {{ $s['payment_method'] }}</h3>
                    <p class="mb-1">Paybill number: <strong>{{ $s['paybill_number'] }}</strong></p>
                    <p class="mb-2">Account: <strong>{{ $s['account_instructions'] }}</strong></p>
                    <ol class="text-muted mb-0 ps-3">
                        @foreach($lines('payment_instructions') as $step)<li>{{ $step }}</li>@endforeach
                    </ol>
                </div></div>
            </div>
        </div>
    </div>
</section>

{{-- E. Leadership --}}
<section class="block" id="leadership">
    <div class="container">
        <div class="row align-items-center g-5 justify-content-center">
            <div class="col-md-4 text-center">
                @if($repPhoto)
                    <img src="{{ $repPhoto }}" alt="{{ $s['rep_name'] }}" class="rep-photo">
                @else
                    <div class="rep-photo d-inline-flex align-items-center justify-content-center bg-brand-soft text-brand display-4 fw-bold">{{ mb_substr($s['rep_name'], 0, 1) }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="section-label">Welfare leadership</div>
                <h2 class="section-title mb-1">{{ $s['rep_name'] }}</h2>
                <div class="text-brand fw-semibold mb-3">{{ $s['rep_designation'] }}</div>
                <p class="text-muted mb-0">{{ $s['rep_bio'] }}</p>
            </div>
        </div>

        @if($images->count())
            <div class="row g-3 gallery mt-4">
                @foreach($images as $img)
                    <div class="col-6 col-md-4 col-lg-3">
                        <img src="{{ Storage::disk('public')->url($img->path) }}" alt="{{ $img->caption }}" loading="lazy">
                        @if($img->caption)<div class="small text-muted mt-1">{{ $img->caption }}</div>@endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- Registration --}}
<section class="block bg-light" id="register">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="text-center mb-4">
                    <div class="section-label">Join us</div>
                    <h2 class="section-title">Member registration</h2>
                    <p class="text-muted">Fill in your details. The Secretary will review your application and send your login details by SMS.</p>
                </div>

                @if(session('registered'))
                    <div class="alert alert-success">{{ session('registered') }}</div>
                @endif
                @include('partials.alerts')

                <div class="card"><div class="card-body p-4">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        @include('partials.submission')
                        @include('members._fields', ['member' => null])

                        <h3 class="h6 fw-bold mt-4 mb-1">Beneficiaries</h3>
                        <p class="small text-muted">Only names are needed. Up to 2 parents, 2 parents-in-law, 1 spouse and any number of children.</p>
                        @include('members._beneficiary_rows', ['categories' => $categories])

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="agree" id="agree" value="1" required>
                            <label class="form-check-label" for="agree">I confirm the information above is correct and agree to the group's rules.</label>
                        </div>
                        <button class="btn btn-primary btn-lg mt-3">Submit registration</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</section>

{{-- F. Contact --}}
<section class="block" id="contact">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-label">Contact</div>
            <h2 class="section-title">Get in touch</h2>
        </div>
        <div class="row g-3 justify-content-center text-center">
            <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body">
                <i class="bi bi-telephone fs-4 text-brand"></i>
                <div class="fw-semibold mt-2">Phone</div>
                <a href="tel:{{ preg_replace('/\s+/', '', $s['phone']) }}">{{ $s['phone'] }}</a>
            </div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body">
                <i class="bi bi-envelope fs-4 text-brand"></i>
                <div class="fw-semibold mt-2">Email</div>
                <a href="mailto:{{ $s['email'] }}">{{ $s['email'] }}</a>
            </div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body">
                <i class="bi bi-geo-alt fs-4 text-brand"></i>
                <div class="fw-semibold mt-2">Address</div>
                <div class="text-muted">{{ $s['address'] }}</div>
                @if($s['contact_hours'])<div class="small text-muted mt-1">{{ $s['contact_hours'] }}</div>@endif
            </div></div></div>
            @if($socials)
                <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body">
                    <i class="bi bi-share fs-4 text-brand"></i>
                    <div class="fw-semibold mt-2">Follow us</div>
                    <div class="d-flex justify-content-center gap-3 fs-5 mt-1">
                        @foreach($socials as $icon => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $icon }}"><i class="bi bi-{{ $icon }}"></i></a>
                        @endforeach
                    </div>
                </div></div></div>
            @endif
        </div>
    </div>
</section>

{{-- G. Footer --}}
<footer class="site-footer pt-5 pb-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @if($logo)<img src="{{ $logo }}" alt="" style="height:36px">@endif
                    <span class="fw-bold text-white">{{ $s['group_name'] }}</span>
                </div>
                <p class="small mb-0">{{ $s['description'] }}</p>
                @if($s['footer_text'])<p class="small mt-2 mb-0">{{ $s['footer_text'] }}</p>@endif
            </div>
            <div class="col-6 col-lg-3">
                <div class="fw-semibold text-white mb-2">Quick links</div>
                <ul class="list-unstyled small mb-0">
                    <li><a href="#about">About us</a></li>
                    <li><a href="#how">How it works</a></li>
                    <li><a href="#register">Register</a></li>
                    <li><a href="{{ route('login') }}">Member login</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <div class="fw-semibold text-white mb-2">Contact</div>
                <ul class="list-unstyled small mb-0">
                    <li>{{ $s['phone'] }}</li>
                    <li>{{ $s['email'] }}</li>
                    <li>{{ $s['address'] }}</li>
                    @if($s['website_url'])<li><a href="{{ $s['website_url'] }}">{{ $s['website_url'] }}</a></li>@endif
                </ul>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <div class="small text-center">&copy; {{ date('Y') }} {{ $s['group_name'] }}. All rights reserved.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}?v=2"></script>
<script>
    // Close the mobile menu after choosing a section.
    document.querySelectorAll('#siteNav .nav-link').forEach(function (a) {
        a.addEventListener('click', function () {
            var el = document.getElementById('siteNav');
            if (el.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(el).hide();
        });
    });
</script>
</body>
</html>
