@extends('admin.layouts.app')
@section('title', __('Web Apps'))

@section('content')
    <div>

        {{-- Page Header --}}
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">{{ __('Web Apps') }}</h1>
            <form action="{{ route('admin.settings.clear-cache') }}" method="POST"
                  onsubmit="return confirm('{{ __('Are you sure you want to clear all caches?') }}')">
                @csrf
                <button type="submit" class="btn btn-danger">{{ __('Clear All Cache') }}</button>
            </form>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-ranking" type="button" role="tab">{{ __('Ranking') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-battlepass" type="button" role="tab">{{ __('Battle Pass') }}</button>
            </li>
        </ul>

        <form method="POST" action="{{ route('admin.settings.update') }}" id="battlePassForm" onsubmit="serializeBattlePass(); serializeInGameRanking()">
            @csrf

            <div class="tab-content">

                {{-- ===================== RANKING ===================== --}}
                <div class="tab-pane fade show active" id="tab-ranking" role="tabpanel">

                    <div class="alert alert-info">
                        <strong>{{ __('In-Game Ranking URL:') }}</strong>
                        <code>{{ route('in-game.ranking') }}</code>
                    </div>

                    <div class="mb-4">
                        <strong>{{ __('Web Interface Setup Guide') }}</strong>
                        <ul>
                            <li>MaxiGuard: <a href="https://docs.google.com/document/d/1ywAYnDzTpjn5SeCv8x0Sxtt_DnLTptOWoHy5gOnK4Cg/view#heading=h.4mspqsvgq5vi" target="_blank">https://docs.google.com/document/d/1ywAYnDzTpjn5SeCv8x0Sxtt_DnLTptOWoHy5gOnK4Cg/view#heading=h.4mspqsvgq5vi</a></li>
                            <li>vPlus: <a href="https://vsroplus.com/helpcenter/features/webinterface" target="_blank">https://vsroplus.com/helpcenter/features/webinterface</a></li>
                            <li>Vanguard: <a href="https://vanguard-r.online/wiki/article/63" target="_blank">https://vanguard-r.online/wiki/article/63</a></li>
                            <li>iSRO: In media.pk2/type.txt set <code>WebMallAddr="yourdomain.com/gateway"</code></li>
                        </ul>
                    </div>

                    <div class="alert alert-info">
                        <p class="fw-semibold mb-1">{{ __('Custom Ranking Guide') }}</p>
                        <p class="small mb-1">{{ __('Each enabled ranking becomes a tab inside the game client. The tab key is derived from its name.') }}</p>
                        <p class="small mb-0">{{ __('Return') }} <code>RefObjID</code> {{ __('to show the race icon.') }}</p>
                    </div>

                    <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="addInGameRankCard()">{{ __('+ Add Ranking') }}</button>

                    <div id="inGameRankingList">
                        @foreach($ranking['custom'] ?? [] as $rk => $rankItem)
                            <div class="card mb-3 ingame-ranking-item">
                                <div class="card-header fw-semibold">{{ $rankItem['name'] ?? $rk }}</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" data-ir="enabled"
                                                {{ ! empty($rankItem['enabled']) ? 'checked' : '' }}>
                                            <label class="form-check-label">{{ __('Enabled') }}</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Name') }}</label>
                                        <input type="text" class="form-control" data-ir="name" value="{{ $rankItem['name'] ?? '' }}" placeholder="{{ __('Label shown on the tab') }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Query') }}</label>
                                        <textarea class="form-control font-monospace" data-ir="query" rows="6">{{ $rankItem['query'] ?? '' }}</textarea>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            onclick="this.closest('.ingame-ranking-item').remove()">{{ __('Remove') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <input type="hidden" id="ingame_ranking" name="ingame_ranking">

                    <button type="submit" class="btn btn-primary mt-3">{{ __('Save Settings') }}</button>
                </div>

                {{-- ===================== BATTLE PASS ===================== --}}
                <div class="tab-pane fade" id="tab-battlepass" role="tabpanel">

                    <div class="alert alert-info">
                        <strong>{{ __('Battle Pass URL:') }}</strong>
                        <code>{{ route('in-game.battlepass') }}</code>
                    </div>

                    <div class="mb-4">
                        <strong>{{ __('Web Interface Setup Guide') }}</strong>
                        <ul>
                            <li>MaxiGuard: <a href="https://docs.google.com/document/d/1ywAYnDzTpjn5SeCv8x0Sxtt_DnLTptOWoHy5gOnK4Cg/view#heading=h.4mspqsvgq5vi" target="_blank">https://docs.google.com/document/d/1ywAYnDzTpjn5SeCv8x0Sxtt_DnLTptOWoHy5gOnK4Cg/view#heading=h.4mspqsvgq5vi</a></li>
                            <li>vPlus: <a href="https://vsroplus.com/helpcenter/features/webinterface" target="_blank">https://vsroplus.com/helpcenter/features/webinterface</a></li>
                            <li>Vanguard: <a href="https://vanguard-r.online/wiki/article/63" target="_blank">https://vanguard-r.online/wiki/article/63</a></li>
                            <li>iSRO: In media.pk2/type.txt set <code>WebMallAddr="yourdomain.com/gateway"</code></li>
                        </ul>
                    </div>

                    <h5 class="fw-semibold mb-3">{{ __('Battle Pass Settings') }}</h5>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bp_enabled" data-bp="enabled"
                                   {!! ($battlepass['enabled'] ?? true) == true ? 'checked' : '' !!}>
                            <label class="form-check-label fw-semibold" for="bp_enabled">{{ __('Battle Pass Enabled') }}</label>
                            <div class="form-text">{{ __('When disabled, the in-game battle pass page and its API show an error and all routes are blocked.') }}</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Premium Pass Enabled') }}</label>
                        <select class="form-select" data-bp="premium_enabled">
                            <option value="true"  {{ ($battlepass['premium_enabled'] ?? true) == true ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="false" {{ ($battlepass['premium_enabled'] ?? true) == false ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Premium Pass Price (Silk)') }}</label>
                        <input type="number" class="form-control" data-bp="premium_price"
                               value="{{ $battlepass['premium_price'] ?? 500 }}" min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Points Per Purchase') }}</label>
                        <input type="number" class="form-control" data-bp="points_per_purchase"
                               value="{{ $battlepass['points_per_purchase'] ?? 1 }}" min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Silk Per Point') }}</label>
                        <input type="number" class="form-control" data-bp="silk_per_point"
                               value="{{ $battlepass['silk_per_point'] ?? 1 }}" min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Silk Type') }}</label>
                        @php
                            $isVSRV = config('global.server.version') === 'vSRO';
                            $silkType = $battlepass['silk_type'] ?? null;
                            $silkType = $silkType === '' || $silkType === null
                                ? ($isVSRV ? '0' : '3')
                                : (string) $silkType;
                        @endphp
                        <select class="form-select" data-bp="silk_type">
                            @if ($isVSRV)
                                <option value="0" {{ $silkType === '0' ? 'selected' : '' }}>{{ __('vSRO - silk_own (0)') }}</option>
                                <option value="1" {{ $silkType === '1' ? 'selected' : '' }}>{{ __('vSRO - silk_gift (1)') }}</option>
                                <option value="2" {{ $silkType === '2' ? 'selected' : '' }}>{{ __('vSRO - silk_point (2)') }}</option>
                            @else
                                <option value="3" {{ $silkType === '3' ? 'selected' : '' }}>{{ __('iSRO - PremiumSilk (3)') }}</option>
                                <option value="1" {{ $silkType === '1' ? 'selected' : '' }}>{{ __('iSRO - Silk (1)') }}</option>
                            @endif
                        </select>
                        <div class="form-text">
                            {{ $isVSRV
                                ? __('Server: vSRO — 0 = silk_own, 1 = silk_gift, 2 = silk_point. Default is silk_own.')
                                : __('Server: iSRO — 3 = PremiumSilk, 1 = Silk. Default is PremiumSilk.') }}
                        </div>
                    </div>

                    <hr>

                    <h5 class="fw-semibold mb-3">{{ __('Tiers') }}</h5>
                    <p class="text-muted small mb-3">
                        {{ __('Type an item code name to search _RefObjCommon — the icon and name are shown once matched. Order = tier level, renumbered automatically on save.') }}
                    </p>
                    <p class="text-muted small mb-3">
                        {{ __('Default points: 10, 25, 50, 100, 150, 200, 300, 500, 1000, 1500, then +500 per level.') }}
                    </p>

                    <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="addTierRow()">{{ __('+ Add Tier') }}</button>

                    <table class="table table-bordered align-middle" id="tierRows">
                        <thead>
                        <tr>
                            <th style="width: 40px;">{{ __('Lv') }}</th>
                            <th style="width: 120px;">{{ __('Points') }}</th>
                            <th>{{ __('Free Item') }}</th>
                            <th>{{ __('VIP Item') }}</th>
                            <th style="width: 80px;">{{ __('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <input type="hidden" id="battlepass" name="battlepass">

                    <button type="submit" class="btn btn-primary mt-3">{{ __('Save Settings') }}</button>
                </div>

            </div>
        </form>
    </div>
@endsection

@push('scripts')
<style>
    .bp-suggest { position: relative; }
    .bp-suggest > ul {
        position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 1055;
        margin: 0; padding: 0; list-style: none; background: #fff; border: 1px solid #dee2e6;
        border-radius: .25rem; max-height: 200px; overflow-y: auto; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
    }
    .bp-suggest li {
        padding: .3rem .5rem; cursor: pointer; font-size: .78rem; border-bottom: 1px solid #f1f3f5;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .bp-suggest li:hover, .bp-suggest li.active { background: #e9ecef; }
    .bp-suggest li img { width: 20px; height: 20px; object-fit: contain; margin-right: .35rem; vertical-align: middle; }
</style>

<script>
    var LOOKUP_URL = "{{ route('admin.settings.item-lookup') }}";
    var ITEM_IMG_BASE = "{{ asset('images/sro') }}/";
    var TIERS = @json($battlepass['tiers'] ?? []);

    var CANON_POINTS = [10, 25, 50, 100, 150, 200, 300, 500, 1000, 1500];

    var pointsForLevel = function (level) {
        if (level <= CANON_POINTS.length) return CANON_POINTS[level - 1];
        return CANON_POINTS[CANON_POINTS.length - 1] + (level - CANON_POINTS.length) * 500;
    };

    var escAttr = function (s) {
        return (s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };

    var itemCellHTML = function (kind, code, id, qty) {
        return '<td>'
            + '<input type="hidden" data-fi="' + kind + '_id" value="' + (parseInt(id, 10) || 0) + '">'
            + '<div class="d-flex align-items-center gap-2 mb-1">'
            + '<img data-bp-pic="' + kind + '" src="" alt="" style="width:28px;height:28px;object-fit:contain" class="border rounded d-none">'
            + '<input type="text" class="form-control form-control-sm font-monospace" data-fi="' + kind + '_code" value="' + escAttr(code) + '" placeholder="Item code name" autocomplete="off">'
            + '</div>'
            + '<div class="d-flex align-items-center gap-2">'
            + '<span class="text-muted small" style="font-size:11px;" data-bp-name="' + kind + '"></span>'
            + '<span class="ms-auto d-inline-flex align-items-center gap-1"><span class="text-muted" style="font-size:11px;">{{ __('Qty') }}</span>'
            + '<input type="number" class="form-control form-control-sm" style="width:64px" data-fi="' + kind + '_qty" value="' + (parseInt(qty, 10) || 1) + '" min="1"></span>'
            + '</div>'
            + '<div class="bp-suggest" data-bp-suggest="' + kind + '"></div>'
            + '</td>';
    };

    var tierRowHTML = function (points, freeCode, freeId, freeQty, vipCode, vipId, vipQty) {
        return '<tr class="tier-row">'
            + '<td class="tier-number"></td>'
            + '<td><input type="number" class="form-control form-control-sm" data-fi="points" value="' + (parseInt(points, 10) || 0) + '" min="0"></td>'
            + itemCellHTML('free', freeCode, freeId, freeQty)
            + itemCellHTML('vip', vipCode, vipId, vipQty)
            + '<td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'tr\').remove()">{{ __('Remove') }}</button></td>'
            + '</tr>';
    };

    var renumber = function () {
        var rows = document.querySelectorAll('#tierRows tbody tr.tier-row');
        for (var i = 0; i < rows.length; i++) {
            rows[i].querySelector('.tier-number').textContent = i + 1;
        }
    };

    var addTierRow = function () {
        var tbody = document.querySelector('#tierRows tbody');
        var level = tbody.querySelectorAll('tr.tier-row').length + 1;
        var next = pointsForLevel(level);
        var tr = document.createElement('tr');
        tr.className = 'tier-row';
        tr.innerHTML = tierRowHTML(next, '', 0, 1, '', 0, 1);
        tbody.appendChild(tr);
        renumber();
    };

    var closeSuggest = function (box) {
        if (box) box.innerHTML = '';
    };

    var fillItem = function (row, kind, item) {
        var code = row.querySelector('[data-fi="' + kind + '_code"]');
        var hid  = row.querySelector('[data-fi="' + kind + '_id"]');
        var img  = row.querySelector('[data-bp-pic="' + kind + '"]');
        var name = row.querySelector('[data-bp-name="' + kind + '"]');

        code.value = item.codeName;
        hid.value = item.id;
        name.textContent = item.name;
        if (item.icon) {
            img.src = ITEM_IMG_BASE + item.icon + '.png';
            img.classList.remove('d-none');
        } else {
            img.classList.add('d-none');
        }
    };

    var renderSuggestions = function (box, kind, items) {
        if (!items.length) {
            box.innerHTML = '<ul><li class="text-muted">{{ __('No items found.') }}</li></ul>';
            return;
        }
        box.innerHTML = '<ul>' + items.map(function (it) {
            return '<li data-bp-sel data-kind="' + kind + '" data-id="' + it.id + '"'
                + ' data-code="' + escAttr(it.codeName) + '" data-name="' + escAttr(it.name) + '" data-icon="' + escAttr(it.icon) + '">'
                + (it.icon ? '<img src="' + ITEM_IMG_BASE + escAttr(it.icon) + '.png" alt="">' : '')
                + escAttr(it.codeName) + ' <span class="text-muted">(' + escAttr(it.name) + ')</span></li>';
        }).join('') + '</ul>';
    };

    var doLookup = function (input) {
        var q = input.value.trim();
        var box = input.closest('td').querySelector('[data-bp-suggest]');
        if (q.length < 1) { closeSuggest(box); return; }

        fetch(LOOKUP_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (items) { renderSuggestions(box, input.dataset.fi.replace('_code', ''), items || []); })
            .catch(function () { closeSuggest(box); });
    };

    var debounce = function (fn, wait) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    };
    var debouncedLookup = debounce(doLookup, 300);

    document.addEventListener('input', function (e) {
        if (e.target.dataset.fi === 'free_code' || e.target.dataset.fi === 'vip_code') {
            debouncedLookup(e.target);
        }
    });

    document.addEventListener('click', function (e) {
        var li = e.target.closest ? e.target.closest('[data-bp-sel]') : null;
        if (li) {
            var row = li.closest('tr.tier-row');
            fillItem(row, li.dataset.kind, {
                id: li.dataset.id,
                codeName: li.dataset.code,
                name: li.dataset.name,
                icon: li.dataset.icon,
            });
            closeSuggest(li.closest('td').querySelector('[data-bp-suggest]'));
            return;
        }

        if (!e.target.closest('.bp-suggest')) {
            document.querySelectorAll('.bp-suggest').forEach(function (box) { box.innerHTML = ''; });
        }
    });

    var serializeBattlePass = function () {
        var bp = {};
        document.querySelectorAll('#battlePassForm [data-bp]').forEach(function (el) {
            var key = el.dataset.bp;
            if (el.type === 'checkbox') { bp[key] = !!el.checked; return; }
            if (key === 'premium_enabled') { bp[key] = el.value === 'true'; return; }
            if (el.type === 'number') { bp[key] = el.value === '' ? 0 : parseInt(el.value, 10) || 0; return; }
            bp[key] = el.value;
        });

        var tbody = document.querySelector('#tierRows tbody');
        var tiers = [];
        tbody.querySelectorAll('tr.tier-row').forEach(function (tr, idx) {
            tr.querySelector('.tier-number').textContent = idx + 1;
            tiers.push({
                id: idx + 1,
                points: parseInt(tr.querySelector('[data-fi="points"]').value, 10) || 0,
                free_item: {
                    id: parseInt(tr.querySelector('[data-fi="free_id"]').value, 10) || 0,
                    qty: parseInt(tr.querySelector('[data-fi="free_qty"]').value, 10) || 1,
                },
                vip_item: {
                    id: parseInt(tr.querySelector('[data-fi="vip_id"]').value, 10) || 0,
                    qty: parseInt(tr.querySelector('[data-fi="vip_qty"]').value, 10) || 1,
                },
            });
        });
        bp.tiers = tiers;

        document.getElementById('battlepass').value = JSON.stringify(bp);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var tbody = document.querySelector('#tierRows tbody');

        if (!TIERS.length) {
            addTierRow();
        } else {
            TIERS.forEach(function (t) {
                var tr = document.createElement('tr');
                tr.className = 'tier-row';
                tr.innerHTML = tierRowHTML(t.points, '', t.free_item.id, t.free_item.qty, '', t.vip_item.id, t.vip_item.qty);
                tbody.appendChild(tr);
            });
            renumber();
        }

        tbody.querySelectorAll('tr.tier-row').forEach(function (row) {
            ['free', 'vip'].forEach(function (kind) {
                var id = row.querySelector('[data-fi="' + kind + '_id"]').value;
                if (!id) return;
                fetch(LOOKUP_URL + '?id=' + id, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (items) { if (items && items[0]) fillItem(row, kind, items[0]); })
                    .catch(function () {});
            });
        });
    });
</script>

<script>
    // ─── In-Game Ranking ─────────────────────────────────────────────────────────────
    function slugify(s) {
        return (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function addInGameRankCard() {
        const container = document.getElementById('inGameRankingList');
        const card = document.createElement('div');
        card.className = 'card mb-3 ingame-ranking-item';
        card.innerHTML = `
            <div class="card-header fw-semibold">{{ __('New Ranking') }}</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" data-ir="enabled">
                        <label class="form-check-label">{{ __('Enabled') }}</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Name') }}</label>
                    <input type="text" class="form-control" data-ir="name" placeholder="{{ __('Label shown on the tab, e.g. Top Gold') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Query') }}</label>
                    <textarea class="form-control font-monospace" data-ir="query" rows="6"></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.ingame-ranking-item').remove()">{{ __('Remove') }}</button>
            </div>`;
        container.appendChild(card);
    }

    function serializeInGameRanking() {
        const custom = {};
        document.querySelectorAll('.ingame-ranking-item').forEach(card => {
            const name = card.querySelector('[data-ir="name"]')?.value.trim();
            if (!name) return;
            const key = slugify(name);
            if (!key) return;
            custom[key] = {
                enabled: card.querySelector('[data-ir="enabled"]')?.checked ?? false,
                name: name,
                query: card.querySelector('[data-ir="query"]')?.value ?? '',
            };
        });
        document.getElementById('ingame_ranking').value = JSON.stringify({ custom });
    }
</script>
@endpush
