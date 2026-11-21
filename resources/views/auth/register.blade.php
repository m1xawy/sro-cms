@extends('layouts.guest')
@section('title', __('Register'))

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <h2 class="mt-5">{{ __('Register') }}</h2>

                        @if (!config('global.disable_register', false))
                            <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="username" class="form-label">{{ __('Username') }}</label>
                                <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required>

                                @error('username')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">{{ __('Email') }}</label>
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>

                                @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>

                            @if(config('services.whatsapp.enabled', false))
                            <div class="mb-3">
                                <label for="phone" class="form-label">{{ __('Phone Number') }}</label>
                                <input id="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" required>

                                @error('phone')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>
                            @endif

                            <div class="mb-3">
                                <label for="password" class="form-label">{{ __('Password') }}</label>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>

                                @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password-confirm" class="form-label">{{ __('Confirm Password') }}</label>
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
                            </div>

                            @if(config('captcha.enabled', false))
                                <!-- google recaptch -->
                                <div class="mb-3">
                                    {!! NoCaptcha::renderJs() !!}
                                    {!! NoCaptcha::display() !!}
                                    @error('g-recaptcha-response')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            @endif

                            @if(config('global.agree_terms', false))
                                <div class="form-check mb-3">
                                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" {{ old('terms') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="{{ route('pages.show', ['slug' => 'terms']) }}" target="_blank">terms and conditions</a>
                                    </label>

                                    @error('terms')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            @endif

                            <div class="mb-0">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Register') }}
                                </button>
                                <a class="btn btn-link" href="{{ route('login') }}">
                                    {{ __('Already registered?') }}
                                </a>
                            </div>
                        </form>
                        @else
                            <div class="alert alert-danger text-center" role="alert">
                                {{ __('Register page is disabled!') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@push('styles')
    @if(config('services.whatsapp.enabled', false))
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24/build/css/intlTelInput.css">
        <style>
            #phone.iti__input,
            .iti {
                width: 100%;
            }
            .iti__country-list {
                z-index: 1100;
            }
        </style>
    @endif
@endpush
@push('scripts')
    @if(config('services.whatsapp.enabled', false))
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24/build/js/intlTelInput.min.js"></script>
        <script>
            const itiInput = document.querySelector('#phone');
            const iti = window.intlTelInput(itiInput, {
                initialCountry: 'auto',
                geoIpLookup: (callback) => {
                    fetch('https://ipapi.co/json/')
                        .then((res) => res.json())
                        .then((data) => callback(data.country_code))
                        .catch(() => callback('us'));
                },
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24/build/js/utils.js',
            });
            itiInput.addEventListener('blur', () => {
                if (iti.isValidNumber()) {
                    itiInput.value = iti.getNumber();
                }
            });
            document.querySelector('form[action*="register"]').addEventListener('submit', () => {
                if (iti.isValidNumber()) {
                    itiInput.value = iti.getNumber();
                }
            });
        </script>
    @endif
    @if(config('global.referral.enabled', false))
        <script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js" referrerpolicy="no-referrer"></script>
        <script>
            (function () {
                if (typeof FingerprintJS === 'undefined') {
                    return;
                }

                var form = document.querySelector('form[action*="register"]');
                if (!form) {
                    return;
                }

                FingerprintJS.load()
                    .then(function (fp) { return fp.get(); })
                    .then(function (result) {
                        if (form.querySelector('input[name="fingerprint"]')) {
                            return;
                        }

                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'fingerprint';
                        input.value = result.visitorId;
                        form.appendChild(input);
                    })
                    .catch(function () {
                        // Optional anti-abuse signal; registration still works.
                    });
            })();
        </script>
    @endif
@endpush
