<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SRO\Shard\RefObjCommon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('admin.settings.index', $this->viewContext());
    }

    public function general(): \Illuminate\View\View
    {
        return view('admin.settings.general', $this->viewContext());
    }

    public function widgets(): \Illuminate\View\View
    {
        $context = $this->viewContext();
        $widgets = $context['widgets'];

        // The event schedule is a full snapshot once saved from the panel, so it
        // must not inherit config-file events the admin removed. Config defaults
        // are only used until the schedule has actually been saved (see helper).
        $widgets['event_schedule'] = $this->effectiveEventSchedule($widgets);

        $context['limitWidgets'] = [
            ['id' => 'global_history', 'label' => 'Global History'],
            ['id' => 'unique_history', 'label' => 'Unique History'],
            ['id' => 'top_player', 'label' => 'Top Player'],
            ['id' => 'top_guild', 'label' => 'Top Guild'],
            ['id' => 'item_plus', 'label' => 'Item Plus'],
            ['id' => 'item_drop', 'label' => 'Item Drop'],
            ['id' => 'pvp_kill', 'label' => 'PvP Kill'],
            ['id' => 'job_kill', 'label' => 'Job Kill'],
        ];

        $context['discord'] = $widgets['discord'] ?? ['enabled' => false, 'server_id' => '', 'channel_id' => '', 'theme' => 'dark'];
        $context['eventSchedule'] = $widgets['event_schedule'] ?? ['enabled' => false, 'names' => [], 'custom' => []];
        $context['fortressWar'] = $widgets['fortress_war'] ?? ['enabled' => false, 'names' => []];
        $context['serverInfo'] = $widgets['server_info'] ?? ['enabled' => false, 'data' => []];
        $context['customWidgets'] = $widgets['custom'] ?? [];

        return view('admin.settings.widgets', $context);
    }

    public function donate(): \Illuminate\View\View
    {
        $context = $this->viewContext();
        $context['gateways'] = config('donate', []);

        $custom = config('donate.custom', []);
        $context['donateCustomDefaults'] = [
            'name' => $custom['name'] ?? 'Custom Donate',
            'currency' => $custom['currency'] ?? 'USD',
            'image' => $custom['image'] ?? 'images/custom.png',
        ];

        return view('admin.settings.donate', $context);
    }

    public function ranking(): \Illuminate\View\View
    {
        return view('admin.settings.ranking', $this->viewContext());
    }

    public function webApps(): \Illuminate\View\View
    {
        $data = Setting::cached()->toArray();

        return view('admin.settings.webapps', [
            'battlepass' => $this->mergeJsonSetting($data, 'battlepass', config('ingame.battlepass', [])),
            'ranking' => $this->mergeJsonSetting($data, 'ingame_ranking', config('ingame.ranking', [])),
        ]);
    }

    /**
     * Lookup items by code name in _RefObjCommon (admin panel item search).
     * Accepts ?q= (partial code-name search) or ?id= (exact RefObjCommon ID).
     */
    public function itemLookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $inventoryService = app(\App\Services\InventoryService::class);

        $rows = [];
        $id = (int) $request->query('id');
        if ($id > 0) {
            $row = RefObjCommon::where('ID', $id)->first();
            if ($row) {
                $rows[] = $row;
            }
        } else {
            $q = trim((string) $request->query('q', ''));
            if ($q === '') {
                return response()->json([]);
            }

            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);

            $rows = RefObjCommon::whereRaw("CodeName128 LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('dbo._RefObjItem')
                        ->whereColumn('dbo._RefObjCommon.Link', 'ID');
                })
                ->orderByRaw('CASE WHEN CodeName128 = ? THEN 0 ELSE 1 END', [$q])
                ->orderBy('CodeName128')
                ->limit(20)
                ->get()
                ->all();
        }

        $ids = array_map(fn ($row) => (int) $row->ID, $rows);
        $itemsInfo = $inventoryService->getItemInfoByRefItem($ids);

        $items = [];
        foreach ($rows as $row) {
            $infoItem = $itemsInfo[(int) $row->ID] ?? null;

            $items[] = [
                'id' => (int) $row->ID,
                'codeName' => (string) $row->CodeName128,
                'name' => $infoItem?->ItemInfo->ItemName ?? 'Unknown Item',
                'icon' => $infoItem ? preg_replace('/\.png$/i', '', (string) $infoItem->ImgPath) : '',
            ];
        }

        return response()->json($items);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role?->is_admin, 403);

        // The donate 'custom' gateway and the widgets 'custom' sub-key share the same
        // POST field name "custom". To eliminate the collision permanently, the widgets
        // form submits it as "widgets_custom" (see blade) and we map it back here.
        $widgetKeys = [
            'discord', 'global_history', 'unique_history', 'top_player', 'top_guild',
            'item_plus', 'item_drop', 'pvp_kill', 'job_kill',
            'server_info', 'event_schedule', 'fortress_war',
            'widgets_custom',   // renamed from 'custom' to avoid donate key collision
        ];

        $donateKeys = array_keys(config('donate', []));   // includes 'custom' (gateway)

        // Load existing blobs so partial saves don't wipe other sub-keys
        $donate = $this->getJsonSetting('donate', config('donate', []));
        $widgets = $this->getJsonSetting('widgets', config('widgets', []));
        $history = $this->getJsonSetting('history', config('global.logs', []));
        $server = $this->getJsonSetting('server', config('global.server', []));
        $battlepass = $this->getJsonSetting('battlepass', config('ingame.battlepass', []));
        $ingameRanking = $this->getJsonSetting('ingame_ranking', config('ingame.ranking', []));

        $toSave = [];

        foreach ($request->except('_token') as $key => $value) {
            if (in_array($key, $donateKeys, true)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $donate[$key] = array_merge($donate[$key] ?? [], $decoded);
                }

                continue;
            }

            if (in_array($key, $widgetKeys, true)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    // 'widgets_custom' is the renamed field — store it under the real key 'custom'
                    $storeKey = $key === 'widgets_custom' ? 'custom' : $key;
                    $widgets[$storeKey] = $decoded;
                }

                continue;
            }

            if ($key === 'history') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $history = $decoded;
                }

                continue;
            }

            if ($key === 'server') {
                if (is_array($value)) {
                    $server = array_merge($server, $value);
                } else {
                    $decoded = json_decode((string) $value, true);
                    if (is_array($decoded)) {
                        $server = array_merge($server, $decoded);
                    }
                }

                continue;
            }

            if ($key === 'battlepass') {
                if (is_array($value) || is_string($value)) {
                    $decoded = is_array($value) ? $value : json_decode((string) $value, true);
                    if (is_array($decoded)) {
                        // Re-key tiers by position so ids stay unique/sequential after row adds/removes
                        if (isset($decoded['tiers']) && is_array($decoded['tiers'])) {
                            $decoded['tiers'] = $this->normalizeTiers($decoded['tiers']);
                        }
                        $battlepass = array_merge($battlepass, $decoded);
                    }
                }

                continue;
            }

            if ($key === 'ingame_ranking') {
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);
                if (is_array($decoded) && array_key_exists('custom', $decoded)) {
                    $ingameRanking['custom'] = $decoded['custom'];
                }

                continue;
            }

            // Scalar fields (General tab direct name= attributes) and other JSON blobs
            $toSave[$key] = is_array($value) ? json_encode($value) : $value;
        }

        $toSave['donate'] = json_encode($donate);
        $toSave['widgets'] = json_encode($widgets);
        $toSave['history'] = json_encode($history);
        $toSave['server'] = json_encode($server);
        $toSave['battlepass'] = json_encode($battlepass);
        $toSave['ingame_ranking'] = json_encode($ingameRanking);

        Setting::saveMany($toSave);
        Setting::flushCache();
        Cache::forget('battle_pass_tiers');

        // Drop previously cached in-game ranking results so edits apply immediately.
        foreach (array_keys($ingameRanking['custom'] ?? []) as $rankingKey) {
            Cache::forget("ingame_ranking_{$rankingKey}");
        }

        return back()->with('success', __('Settings updated successfully.'));
    }

    /**
     * Sanitize submitted battle pass tiers into a clean indexed list:
     * positional order defines level, ids are re-numbered 1..N.
     */
    private function normalizeTiers(array $tiers): array
    {
        $clean = [];
        $position = 0;

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $position++;

            $clean[] = [
                'id' => $position,
                'points' => max(0, (int) ($tier['points'] ?? 0)),
                'free_item' => [
                    'id' => max(0, (int) ($tier['free_item']['id'] ?? 0)),
                    'qty' => max(1, (int) ($tier['free_item']['qty'] ?? 1)),
                ],
                'vip_item' => [
                    'id' => max(0, (int) ($tier['vip_item']['id'] ?? 0)),
                    'qty' => max(1, (int) ($tier['vip_item']['qty'] ?? 1)),
                ],
            ];
        }

        return $clean;
    }

    public function clearCache(): RedirectResponse
    {
        abort_unless(auth()->user()?->role?->is_admin, 403);

        Artisan::call('optimize:clear');
        Setting::flushCache();

        return back()->with('success', __('All caches cleared successfully.'));
    }

    /*
    |--------------------------------------------------------------------------
    | View Context
    |--------------------------------------------------------------------------
    */

    private function viewContext(): array
    {
        $data = Setting::cached()->toArray();

        return [
            'settings' => $this->mergeScalarSettings($data, config('global', [])),
            'themes' => $this->loadThemes(),
            'languages' => config('global.languages', []),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'appUrl' => config('app.url'),
            'appName' => config('app.name'),

            'server' => $this->mergeJsonSetting($data, 'server', config('global.server', [])),
            'referral' => $this->mergeJsonSetting($data, 'referral', config('global.referral', [])),
            'tickets' => $this->mergeJsonSetting($data, 'tickets', config('global.tickets', [])),
            'sliders' => $this->mergeJsonSetting($data, 'sliders', config('global.slider', [])),
            'footer' => $this->mergeJsonSetting($data, 'footer', config('global.footer', [])),
            'mail' => $this->mergeJsonSetting($data, 'mail', []),
            'captcha' => $this->mergeJsonSetting($data, 'captcha', config('captcha', [])),
            'whatsapp' => $this->mergeJsonSetting($data, 'whatsapp', config('services.whatsapp', [])),
            'vote' => $this->mergeJsonSetting($data, 'vote', config('vote', [])),
            'widgets' => $this->mergeJsonSetting($data, 'widgets', config('widgets', [])),
            'ranking' => $this->mergeJsonSetting($data, 'ranking', config('ranking', [])),
            'history' => $this->mergeJsonSetting($data, 'history', config('global.logs', [])),
            'cache' => $this->mergeJsonSetting($data, 'cache', config('global.cache', [])),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Merge scalar settings stored as individual DB rows (General tab plain name= fields).
     */
    private function mergeScalarSettings(array $data, array $defaults): array
    {
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $data)) {
                $defaults[$key] = $data[$key];
            }
        }

        return $defaults;
    }

    /**
     * Decode a JSON blob from the cached settings array.
     *
     * If the key has never been saved to the DB we fall back to $defaults.
     * Once a value exists in the DB we trust it completely — no merging —
     * so that rows deleted by the user are not re-injected from config defaults.
     */
    private function mergeJsonSetting(array $data, string $key, array $defaults = []): array
    {
        $raw = $data[$key] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : $defaults;
    }

    /**
     * The DB-saved event schedule is authoritative once it has been populated,
     * so events the admin removes are not re-injected from the config file.
     * Only while it has never been saved (both names and custom are empty) do
     * we fall back to the built-in config-file events.
     */
    private function effectiveEventSchedule(array $widgets): array
    {
        $saved = $widgets['event_schedule'] ?? null;

        if (! is_array($saved)) {
            return config('widgets.event_schedule', ['enabled' => false, 'names' => [], 'custom' => []]);
        }

        $names = $saved['names'] ?? [];
        $custom = $saved['custom'] ?? [];

        if (empty($names) && empty($custom)) {
            return array_replace_recursive(config('widgets.event_schedule', []), $saved);
        }

        return $saved;
    }

    /**
     * Load a JSON blob directly from the DB/cache.
     */
    private function getJsonSetting(string $key, array $default = []): array
    {
        $value = Setting::get($key, json_encode($default));
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function loadThemes(): array
    {
        $path = resource_path('themes');

        if (! is_dir($path)) {
            return [];
        }

        return collect(scandir($path))
            ->reject(fn ($item) => in_array($item, ['.', '..']) || ! is_dir($path.'/'.$item))
            ->values()
            ->all();
    }
}
