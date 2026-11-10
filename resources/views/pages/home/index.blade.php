@extends('layouts.full')
@section('title', __('Home'))

@section('breadcrumb')
    @include('partials.carousel')
@stop

@section('content')
    </div>
    <section class="bg-body-tertiary text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-3 col-6 py-4">
                    <div class="display-5 fw-bold text-body-emphasis">{{ App\Models\SRO\Shard\Char::getCharCount() }}+</div>
                    <p class="text-body-secondary fw-bold text-uppercase small mt-1">Total Players</p>
                </div>
                <div class="col-lg-3 col-6 py-4">
                    <div class="display-5 fw-bold text-body-emphasis">{{ App\Models\SRO\Account\TbUser::getTbUserCount() }}+</div>
                    <p class="text-body-secondary fw-bold text-uppercase small mt-1">Registered Accounts</p>
                </div>
            </div>
        </div>
    </section>

    @if(config('widgets.server_info.enabled'))
        <section class="bg-body text-center py-5">
            <div class="container">
                <h2 class="fw-normal lh-1 mb-3">Server <span>Features</span></h2>
                <p class="lead text-body-secondary mx-auto mb-5 w-75">Our private server offers an enhanced Silkroad experience with carefully balanced gameplay, new content, and quality-of-life improvements.</p>
                <div class="d-flex justify-content-center gap-5 mb-5 text-body-secondary">
                    <div class="fw-bold"><span class="text-primary">{{ $onlineCounter->onlinePlayer + $onlineCounter->fakePlayer }}</span> Players Online</div>
                    <div class="fw-bold"><span class="text-primary" id="idTimerClock">{{ date('H:i:s') }}</span> Server Time</div>
                </div>
                @php
                    $chunks = collect(config('widgets.server_info.data'))->chunk(4);
                @endphp
                <div id="featuresCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        @foreach($chunks as $key => $chunk)
                            <div class="carousel-item @if($loop->first) active @endif">
                                <div class="row justify-content-center g-4">
                                    @foreach($chunk as $row)
                                        <div class="col-lg-3 col-6">
                                            <div class="p-4">
                                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 mb-3" style="width: 64px; height: 64px;">
                                                    <span class="text-primary">{!! $row['icon'] !!}</span>
                                                </div>
                                                <h5 class="fw-bold">{{ $row['name'] }}</h5>
                                                <p class="small text-body-secondary mb-0">{{ $row['value'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button class="carousel-control-prev opacity-0" type="button" data-bs-target="#featuresCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon bg-dark rounded-1" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next opacity-0" type="button" data-bs-target="#featuresCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon bg-dark rounded-1" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </section>
    @endif

    @if(config('widgets.discord.enabled'))
        <section class="bg-body-tertiary text-center py-5">
            <div class="container">
                <h2 class="fw-normal lh-1 mb-3">Our <span>Discord</span></h2>
                <p class="lead text-body-secondary mx-auto mb-5 w-75">Stay updated with the latest events, updates, and announcements from our server team.</p>
                <div class="row justify-content-center">
                    <div class="col-12">
                        <widgetbot
                            server="{{ config('widgets.discord')['server_id'] }}"
                            channel="{{ config('widgets.discord')['channel_id'] }}"
                            width="100%"
                            height="400"
                        ></widgetbot>
                        <script src="https://cdn.jsdelivr.net/npm/@widgetbot/html-embed"></script>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="bg-body text-center py-5">
        <div class="container">
            <h2 class="fw-normal lh-1 mb-3">Latest <span>News</span></h2>
            <p class="lead text-body-secondary mx-auto mb-5 w-75">Stay updated with the latest events, updates, and announcements from our server team.</p>

            <div class="row g-4">
                @forelse($data->take(3) as $row)
                    <div class="col-lg-4">
                        <div class="card h-100 shadow-sm text-start">
                            @if ($row->image)
                                <img src="{{ $row->image }}" class="card-img-top" alt="">
                            @endif
                            <div class="card-body">
                                <div class="small mb-2">
                                    @switch($row->category)
                                        @case('news')
                                            <span class="badge bg-warning text-dark">News</span>
                                            @break
                                        @case('update')
                                            <span class="badge bg-primary">Update</span>
                                            @break
                                        @case('event')
                                            <span class="badge bg-success">Event</span>
                                            @break
                                        @default
                                            <span class="badge bg-warning text-dark">News</span>
                                    @endswitch
                                    {{ $row->published_at->format("M j, Y") }}
                                </div>
                                <h3 class="h5">{{ \Illuminate\Support\Str::words(strip_tags($row->title), 5, '...') }}</h3>
                                <p class="card-text text-body-secondary">{{ \Illuminate\Support\Str::words(strip_tags($row->content), 20, '...') }}</p>
                                <a href="{{ route('news.show', ['slug' => $row->slug]) }}" class="btn btn-outline-primary btn-sm">Read More &rarr;</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-danger text-center mb-0" role="alert">{{ __('No Posts Available!') }}</div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bg-body-tertiary text-center py-5">
        <div class="container">
            <h2 class="fw-normal lh-1 mb-3">Begin Your <span>Adventure</span></h2>
            <p class="lead text-body-secondary mx-auto mb-4 w-75">Join thousands of players already enjoying our enhanced Silkroad Online experience. Create your account now and become part of our thriving community.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                @auth
                    <a href="{{ route('account') }}" class="btn btn-primary fw-bold px-4 py-3">Account Panel</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary fw-bold px-4 py-3">Create Account</a>
                @endauth
                <a href="{{ route('download') }}" class="btn btn-outline-primary fw-bold px-4 py-3">Download Client</a>
            </div>
            <p class="text-body-secondary mt-3 small">Client Size: 2.1 GB &bull; Compatible with Windows 7/8/10/11</p>
        </div>
    </section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous">
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('idTimerClock');
            if (!el) return;

            var serverTime = new Date();
            var parts = '{{ date('H:i:s') }}'.split(':');
            serverTime.setHours(parts[0], parts[1], parts[2], 0);
            var offset = serverTime - new Date();

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function tick() {
                var now = new Date(new Date() + offset);
                el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            }

            tick();
            setInterval(tick, 1000);
        });
    </script>
@endpush
