<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use App\Models\SRO\Account\BattlePass;
use App\Models\SRO\Shard\Char;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class BattlePassController extends Controller
{
    public function index(Request $request)
    {
        return view('in-game.battle-pass.index');
    }

    public function tiers(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        try {
            $charname = Char::resolveCharname($request);
            if ($charname === null) {
                throw new \InvalidArgumentException('Unable to resolve character name (guard: '.strtolower(config('global.server.guard', 'isro')).').');
            }

            $char = Char::where('CharName16', $charname)->first();
            $charID = $char?->CharID;
            if ($charID === null) {
                throw new \InvalidArgumentException('Character not found: '.$charname);
            }

            $record = BattlePass::getOrCreateForChar($charID);
            $tiers = BattlePass::getAllTiers();
            $progress = BattlePass::getProgress($record->Points, $tiers);

            $instances = [];
            foreach ($tiers as $tier) {
                $instances[$tier['freeItem']['itemID']] = ['Data' => $tier['freeItem']['quantity']];
                $instances[$tier['vipItem']['itemID']] = ['Data' => $tier['vipItem']['quantity']];
            }
            $itemsInfo = app(InventoryService::class)->getItemInfoByRefItem(array_keys($instances), $instances);

            foreach ($tiers as &$tier) {
                $tier['freeClaimed'] = $record->hasClaimedItem($tier['freeItem']['itemID']);
                $tier['vipClaimed'] = $record->hasClaimedItem($tier['vipItem']['itemID']);
                $tier['canClaimFree'] = ($progress['currentLevel'] >= $tier['id']) && ! $tier['freeClaimed'];
                $tier['canClaimVIP'] = ($progress['currentLevel'] >= $tier['id']) && $record->IsPremium && ! $tier['vipClaimed'];
                $tier['vipLocked'] = ! $record->IsPremium;

                $freeInfo = $itemsInfo[$tier['freeItem']['itemID']] ?? null;
                $vipInfo = $itemsInfo[$tier['vipItem']['itemID']] ?? null;

                $tier['freeItem']['tooltipHtml'] = $freeInfo
                    ? $this->renderItemTooltip($freeInfo, (int) $char->JID, $tier['freeItem']['quantity'])
                    : null;
                $tier['vipItem']['tooltipHtml'] = $vipInfo
                    ? $this->renderItemTooltip($vipInfo, (int) $char->JID, $tier['vipItem']['quantity'])
                    : null;
            }
            unset($tier);

            return response()->json([
                'success' => true,
                'message' => 'Battle pass data loaded successfully',
                'character' => [
                    'charID' => $charID,
                    'charname' => $charname,
                    'jid' => $char->JID,
                ],
                'record' => [
                    'id' => $record->ID,
                    'charID' => $record->CharID,
                    'isPremium' => $record->IsPremium,
                    'points' => $record->Points,
                    'claimedItems' => $record->getClaimedItemsArray(),
                ],
                'stats' => [
                    'currentLevel' => $progress['currentLevel'],
                    'currentPoints' => $record->Points,
                    'maxLevel' => $progress['maxLevel'],
                    'progressPercent' => $progress['progressPercent'],
                    'isPremium' => $record->IsPremium,
                    'nextLevelPoints' => $progress['nextLevelPoints'],
                ],
                'tiers' => $tiers,
                'premiumEnabled' => config('ingame.battlepass.premium_enabled'),
                'premiumPrice' => (int) config('ingame.battlepass.premium_price'),
                'pointsPerPurchase' => (int) config('ingame.battlepass.points_per_purchase'),
                'silkPerPoint' => (int) config('ingame.battlepass.silk_per_point'),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function claim(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        try {
            $tierID = (int) $request->input('tier_id', 0);
            $claimType = $request->input('type', 'free');

            if ($tierID <= 0 || ! in_array($claimType, ['free', 'vip'], true)) {
                throw new \InvalidArgumentException('Invalid request');
            }

            $tier = BattlePass::getTierById($tierID);
            if ($tier === null) {
                throw new \InvalidArgumentException('Invalid request');
            }

            $charID = $this->resolveCharID($request);

            $record = BattlePass::getForChar($charID);
            $currentLevel = 0;
            if ($record) {
                $currentLevel = BattlePass::getProgress($record->Points, BattlePass::getAllTiers())['currentLevel'];
            }

            BattlePass::claimReward($charID, $tierID, $claimType, $currentLevel);

            return response()->json([
                'success' => true,
                'message' => 'Item claimed successfully!',
                'tierID' => $tierID,
                'claimType' => $claimType,
                'itemName' => ($claimType === 'vip') ? $tier['vipItem']['name'] : $tier['freeItem']['name'],
                'quantity' => ($claimType === 'vip') ? $tier['vipItem']['quantity'] : $tier['freeItem']['quantity'],
                'deliveryMethod' => strtolower(config('global.server.guard')),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function purchasePremium(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        try {
            $charID = $this->resolveCharID($request);
            $remainingSilk = BattlePass::purchasePremium($charID);

            return response()->json([
                'success' => true,
                'message' => 'Premium Pass purchased successfully!',
                'premiumPrice' => (int) config('ingame.battlepass.premium_price'),
                'remainingSilk' => $remainingSilk,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function purchasePoints(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        try {
            $qty = (int) $request->input('qty', 1);
            $charID = $this->resolveCharID($request);
            $remainingSilk = BattlePass::purchasePoints($charID, $qty);

            return response()->json([
                'success' => true,
                'message' => 'Points purchased successfully!',
                'pointsPurchased' => $qty * (int) config('ingame.battlepass.points_per_purchase'),
                'silkSpent' => $qty * (int) config('ingame.battlepass.silk_per_point'),
                'remainingSilk' => $remainingSilk,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function addPoints(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        if (! (bool) config('ingame.battlepass.allow_add_points', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Adding points is currently disabled.',
                'error' => 'Adding points is currently disabled.',
            ], 403);
        }

        if (! auth()->check() || ! (bool) (auth()->user()->role?->is_admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'error' => 'Forbidden.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'points' => 'required|integer|min:1|max:100',
            ]);
            $charID = $this->resolveCharID($request);

            $added = BattlePass::addPoints($charID, (int) $validated['points']);

            return response()->json([
                'success' => $added,
                'message' => $added ? 'Points added successfully!' : 'Failed to add points',
                'pointsAdded' => (int) $validated['points'],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getSilk(Request $request)
    {
        if (! (bool) config('ingame.battlepass.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Battle Pass is currently disabled.',
                'error' => 'Battle Pass is currently disabled.',
            ], 403);
        }

        try {
            $charID = $this->resolveCharID($request);
            $jid = Char::find($charID)?->JID;

            if (! $jid) {
                return response()->json(['success' => false, 'message' => 'Unable to get JID for this character'], 422);
            }

            $silk = BattlePass::getSilk($jid);

            return response()->json([
                'success' => true,
                'message' => 'Silk amount retrieved',
                'silk' => $silk,
                'jid' => $jid,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function resolveCharID(Request $request): int
    {
        $charname = Char::resolveCharname($request);
        if ($charname === null) {
            throw new \InvalidArgumentException('Unable to resolve character name (guard: '.strtolower(config('global.server.guard', 'isro')).').');
        }

        $char = Char::where('CharName16', $charname)->first();
        if (! $char) {
            throw new \InvalidArgumentException('Character not found: '.$charname);
        }

        if (auth()->check() && (int) $char->JID !== (int) auth()->user()->jid) {
            throw new \InvalidArgumentException('Forbidden.');
        }

        return $char->CharID;
    }

    private function errorResponse(\Throwable $e)
    {
        if ($e instanceof \InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => null,
            ], 422);
        }

        report($e);

        return response()->json([
            'success' => false,
            'message' => __('Something went wrong. Please try again.'),
            'error' => null,
        ], 422);
    }

    private function renderItemTooltip(object $itemInfo, int $jid, int $quantity): ?string
    {
        try {
            $itemInfo->ItemInfo->Data = $quantity;

            return view('pages.ranking.character.partials.inventory.item-blues-whites', [
                'item' => $itemInfo->ItemInfo,
                'data' => (object) ['jid' => $jid],
            ])->render();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
