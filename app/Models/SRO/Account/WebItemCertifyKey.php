<?php

namespace App\Models\SRO\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WebItemCertifyKey extends Model
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
    protected $table = 'dbo.WEB_ITEM_CERTIFYKEY';

    /**
     * The table primary Key
     *
     * @var string JID
     */
    protected $primaryKey = 'UserJID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'idx',
        'UserJID',
        'Certifykey',
        'ShardID',
        'reg_date',
        'CharLevel'
    ];

    public static function getCertifyKey($JID)
    {
        return self::where('UserJID', $JID)->orderByDesc('reg_date')->limit(1)->first();
    }

    public static function resolveCharname(Request $request): ?string
    {
        $jid = $request->query('jid');
        $key = $request->query('key');

        if (! is_string($jid) || ! is_string($key) || $jid === '' || $key === '') {
            return null;
        }

        $certifyKeyRecord = self::getCertifyKey($jid);
        if (!$certifyKeyRecord) {
            return null;
        }

        $generatedKey = strtoupper(md5($jid . $certifyKeyRecord->Certifykey . config('global.server.saltKey')));
        if (! hash_equals($generatedKey, strtoupper($key))) {
            return null;
        }

        return DB::connection('shard')
            ->table(DB::raw('_Char as c'))
            ->join(DB::raw('_User as u'), 'c.CharID', '=', 'u.CharID')
            ->where('u.UserJID', $jid)
            ->orderByDesc('c.LastLogout')
            ->value('c.CharName16');
    }

    public function tbUser()
    {
        return $this->belongsTo(TbUser::class, 'UserJID', 'JID');
    }
}
