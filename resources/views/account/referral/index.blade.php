@extends('layouts.app')
@section('title', __('Referral'))

@section('sidebar')
    @include('account.sidebar')
@stop

@section('content')
    <section class="card">
        <div class="card-body">
            @if(config('global.referral.enabled'))
            @if ($invite)
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="mb-3">
                    <label for="inviteLink" class="form-label">Your Invite Link</label>
                    <div class="input-group">
                        <input type="text" id="inviteLink" class="form-control" value="{{ url('/register?invite=' . $invite->code) }}" readonly>
                        <button class="btn btn-outline-primary" id="inviteButton" type="button" onclick="copyInviteLink()">Copy</button>
                    </div>
                    <script>
                        function copyInviteLink() {
                            const input = document.getElementById("inviteLink");
                            const button = document.getElementById("inviteButton");

                            input.select();
                            input.setSelectionRange(0, 99999); // for mobile

                            const done = () => {
                                button.textContent = "Copied!";
                                setTimeout(() => { button.textContent = "Copy"; }, 2000);
                            };

                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(input.value).then(done);
                            } else {
                                try {
                                    document.execCommand("copy");
                                    done();
                                } catch (e) {
                                    button.textContent = "Press Ctrl+C";
                                }
                            }
                        }
                    </script>
                </div>

                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="mb-0">Total Invite Points: <span class="">{{ $totalPoints }}</span></h5>
                        <p class="mt-0 text-muted">Minimum {{ $minimumRedeem }} points to redeem</p>
                        @if ($totalPoints >= $minimumRedeem)
                            <form method="POST" action="{{ route('account.referral.redeem') }}">
                                @csrf
                                <button class="btn btn-primary">Redeem Points</button>
                            </form>
                        @else
                            <button class="btn btn-secondary" disabled>Redeem Points</button>
                        @endif
                    </div>
                </div>

                <div class="mt-5">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Registered At</th>
                                <th>Points</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if ($usedInvites->isNotEmpty())
                                @foreach ($usedInvites as $key => $row)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->invitedUser->username ?? 'Unknown' }}</td>
                                        <td>{{ $row->invitedUser?->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                        <td>{{ $row->points }}</td>
                                        <td>
                                            @if($row->ip == 'CHEATING')
                                                <span class="text-danger">Cheating</span>
                                            @else
                                                <span class="text-success">Success</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="text-center" colspan="5">No invited users yet.</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="alert alert-warning">
                    No invite code found for this user.
                </div>
            @endif
            @else
                <div class="alert alert-danger text-center" role="alert">
                    {{ __('Referral is disabled!') }}
                </div>
            @endif
        </div>
    </section>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js" referrerpolicy="no-referrer"></script>
    <script>
        (function () {
            if (typeof FingerprintJS === 'undefined') {
                return;
            }

            var token = document.querySelector('meta[name="csrf-token"]');
            if (!token) {
                return;
            }

            FingerprintJS.load()
                .then(function (fp) { return fp.get(); })
                .then(function (result) {
                    return fetch('{{ route('account.referral.fingerprint') }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token.getAttribute('content')
                        },
                        body: JSON.stringify({ fingerprint: result.visitorId })
                    });
                })
                .catch(function () {
                    // Fingerprinting is optional; ignore CDN/network failures.
                });
        })();
    </script>
@endpush
