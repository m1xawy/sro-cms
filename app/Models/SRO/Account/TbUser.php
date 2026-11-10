<?php

namespace App\Models\SRO\Account;

use App\Models\Donate;
use App\Models\SRO\Portal\AphChangedSilk;
use App\Models\SRO\Portal\MuUser;
use App\Models\SRO\Shard\Char;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TbUser extends Model
{
    use HasFactory;

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
    protected $table = 'dbo.TB_User';

    /**
     * The table primary Key
     *
     * @var string
     */
    protected $primaryKey = 'JID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The fillable attributes for vSRO.
     *
     * @var array<int, string>
     */
    protected array $fillable_vsro = [
        'StrUserID',
        'Name',
        'password',
        'Status',
        'Email',
        'regtime',
        'reg_ip',
        'sec_primary',
        'sec_content',
        'AccPlayTime',
        'LatestUpdateTime_ToPlayTime',
    ];

    /**
     * The fillable attributes for iSRO.
     *
     * @var array<int, string>
     */
    protected array $fillable_isro = [
        'PortalJID',
        'StrUserID',
        'ServiceCompany',
        'password',
        'Active',
        'UserIP',
        'CountryCode',
        'VisitDate',
        'RegDate',
        'sec_primary',
        'sec_content',
        'sec_grade',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (config('global.server.version') === 'vSRO') {
            $this->fillable = $this->fillable_vsro;
        } else {
            $this->fillable = $this->fillable_isro;
        }
    }

    public static function setVSROAccount($jid, $username, $password, $email, $ip)
    {
        return self::create([
            'StrUserID' => strtolower($username),
            'Name' => $username,
            'password' => md5($password),
            'Status' => 1,
            'GMrank' => 0,
            'Email' => $email,
            'regtime' => now(),
            'reg_ip' => $ip,
            'sec_primary' => 3,
            'sec_content' => 3,
        ]);
    }

    public static function setISROAccount($jid, $username, $password, $email, $ip)
    {
        return self::create([
            'PortalJID' => $jid,
            'StrUserID' => $username,
            'ServiceCompany' => 11,
            'password' => md5($password),
            'Active' => 1,
            'UserIP' => $ip,
            'CountryCode' => 'EG',
            'VisitDate' => now(),
            'RegDate' => now(),
            'sec_primary' => 3,
            'sec_content' => 3,
            'sec_grade' => 0,
            'AccPlayTime' => 0,
            'LatestUpdateTime_ToPlayTime' => 0,
        ]);
    }

    public static function updateSilk($jid, $type, $amount)
    {
        if (config('global.server.version') === 'vSRO') {
            $type = in_array($type, [0, 1, 2]) ? $type : 0;
            SkSilk::updateSkSilk($jid, $type, $amount);
        } else {
            $type = in_array($type, [1, 3]) ? $type : 3;
            AphChangedSilk::updateChangedSilk($jid, $type, $amount);
        }
    }

    public function blockAccount(string $reason, int $durationHours, ?string $customReason = null)
    {
        $finalReason = $reason === 'Custom' ? $customReason : $reason;
        $punishment = Punishment::setPunishment($this->JID, $finalReason, now(), now()->copy()->addHours($durationHours));
        $blocked = BlockedUser::where('UserJID', $this->JID)->where('Type', 1)->first();

        if ($blocked) {
            $blocked->update([
                'SerialNo' => $punishment->SerialNo,
                'timeBegin' => now(),
                'timeEnd' => now()->copy()->addHours($durationHours),
            ]);
        } else {
            BlockedUser::setBlockedUser($this->JID, $this->StrUserID, $punishment->SerialNo, now(), now()->copy()->addHours($durationHours));
        }

        return $punishment;
    }

    public function unblockAccount()
    {
        $blocked = BlockedUser::where('UserJID', $this->JID)->where('Type', 1)->first();
        if ($blocked) {
            $blocked->update(['timeEnd' => now()]);

            return true;
        }

        return false;
    }

    public function activeBlock()
    {
        return $this->hasOne(BlockedUser::class, 'UserJID', 'JID')
            ->where('Type', 1)
            ->where('timeEnd', '>', now())
            ->with('punishment')
            ->latest('timeEnd');
    }

    public function user()
    {
        if (config('global.server.version') === 'vSRO') {
            return $this->belongsTo(User::class, 'JID', 'jid');
        } else {
            return $this->belongsTo(User::class, 'PortalJID', 'jid');
        }
    }

    public function muUser()
    {
        return $this->hasOne(MuUser::class, 'JID', 'PortalJID');
    }

    public function blockedUser()
    {
        return $this->hasOne(BlockedUser::class, 'UserJID', 'JID');
    }

    public function getShardUserAttribute()
    {
        return Cache::remember("shard_user_{$this->JID}", config('global.cache.account_info', 600), function () {
            return $this->shardUser()->get() ?? collect();
        });
    }

    public function getGetSilkAttribute()
    {
        return Cache::remember("tb_user_silk_{$this->JID}", config('global.cache.account_info', 600), function () {
            return $this->SkSilk()->first() ?? new SkSilk([
                'JID' => $this->JID,
                'silk_own' => 0,
                'silk_gift' => 0,
                'silk_point' => 0,
            ]);
        });
    }

    public function shardUser()
    {
        return $this->belongsToMany(Char::class, '_User', 'UserJID', 'CharID');
    }

    public function SkSilk()
    {
        return $this->hasOne(SkSilk::class, 'JID', 'JID');
    }

    public function donationLogs()
    {
        return $this->hasMany(Donate::class, 'jid', 'JID');
    }

    public function getSkSilkHistory()
    {
        return $this->hasMany(SkSilkBuyList::class, 'UserJID', 'JID');
    }

    public static function getTbUserCount()
    {
        return Cache::remember('tb_user_count', 600, fn () => self::count());
    }

    protected static function booted()
    {
        static::created(fn () => Cache::forget('tb_user_count'));
        static::deleted(fn () => Cache::forget('tb_user_count'));
    }

    public function secondaryPassword()
    {
        return $this->hasOne(SecondaryPassword::class, 'UserJID', 'JID');
    }

    public function certifyKey()
    {
        return $this->hasMany(WebItemCertifyKey::class, 'UserJID', 'JID');
    }
}
