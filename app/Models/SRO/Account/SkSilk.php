<?php

namespace App\Models\SRO\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SkSilk extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'account';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dbo.SK_Silk';

    /**
     * The table primary Key
     *
     * @var string JID
     */
    protected $primaryKey = 'JID';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'JID',
        'silk_own',
        'silk_gift',
        'silk_point'
    ];

    public static function setSkSilk($jid, $type, $amount)
    {
        $silkTypes = [
            '0' => 'silk_own',
            '1' => 'silk_gift',
            '2' => 'silk_point'
        ];

        self::firstOrCreate(
            ['JID' => $jid],
            [
                'silk_own' => 0,
                'silk_gift' => 0,
                'silk_point' => 0
            ]
        );

        return self::where('JID', $jid)->increment($silkTypes[$type] ?? 'silk_own', $amount);
    }

    public static function setSkSilkLive($username, $amount, $pkgId = 0, $price = 0, $orderId = 'Website')
    {
        $result = DB::select("
            EXEC [" . DB::connection('account')->getDatabaseName() . "].[CGI].[CGI_WebPurchaseSilk]
                @OrderID = :orderID,
                @UserID = :userID,
                @PkgID = :pkgID,
                @NumSilk = :numSilk,
                @Price = :price
        ", [
            'orderID' => $orderId,
            'userID'  => $username,
            'pkgID'   => $pkgId,
            'numSilk' => $amount,
            'price'   => $price,
        ]);

        $status = $result[0]->Result ?? null;

        return $status === 'SUCCESS' ? true : $status;
    }

    public static function getSkSilk($jid, $type)
    {
        $silkTypes = [
            '0' => 'silk_own',
            '1' => 'silk_gift',
            '2' => 'silk_point'
        ];

        return (int) self::where('JID', $jid)->lockForUpdate()->value($silkTypes[$type]);
    }

    public static function updateSkSilk($jid, $type, $amount)
    {
        return DB::connection('account')->transaction(function () use ($jid, $type, $amount) {
            $oldAmount = self::getSkSilk($jid, $type);

            self::setSkSilk($jid, $type, $amount);

            $newAmount = $oldAmount + $amount;

            SkSilkBuyList::setSilkBuyList($jid, $amount, $newAmount, $type);
            SkSilkChangeByWeb::setSilkChange($jid, $newAmount, $amount, $type);

            Cache::forget("tb_user_silk_{$jid}");

            return $oldAmount + $amount;
        });
    }

    public static function sumSkSilk()
    {
        return Cache::remember('vsro_silk_sum', 600, function () {
            try {
                return self::selectRaw('SUM(CAST(silk_own AS BIGINT)) as total')->value('total');
            } catch (\Exception $e) {
                return 0;
            }
        });
    }

    protected static function booted()
    {
        static::saved(fn () => Cache::forget('vsro_silk_sum'));
        static::deleted(fn () => Cache::forget('vsro_silk_sum'));
    }
}
