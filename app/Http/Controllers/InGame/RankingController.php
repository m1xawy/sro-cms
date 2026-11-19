<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function ranking(Request $request)
    {
        $ranking = collect(config('ingame.ranking.custom', []))
            ->filter(fn ($item) => is_array($item) && ! empty($item['enabled']) && ! empty($item['query']))
            ->all();

        $data = [];
        $errors = [];
        foreach ($ranking as $key => $item) {
            [$data[$key], $errors[$key]] = $this->loadRankingData($key, $item);
        }

        return view('ingame.ranking.index', [
            'ranking' => $ranking,
            'data' => $data,
            'errors' => $errors,
            'topImage' => config('ranking.top_image', []),
        ]);
    }

    /**
     * Run a single custom ranking query against the shard connection.
     *
     * Successful results are cached briefly, query errors are never cached so
     * a fixed query becomes visible immediately after clearing/changing it.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ?string}
     */
    private function loadRankingData(string $key, array $item): array
    {
        $cacheKey = "ingame_ranking_{$key}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $rows = collect(DB::connection('shard')->select((string) ($item['query'] ?? '')));
            Cache::put($cacheKey, [$rows, null], 600);

            return [$rows, null];
        } catch (\Throwable $e) {
            report($e);

            return [collect(), $e->getMessage()];
        }
    }
}