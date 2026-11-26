<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Error extends Model
{
    protected $fillable = [
        'subject',
        'message',
        'jid',
        'ip',
        'created_at',
    ];

    protected static function booted()
    {
        static::saved(fn () => Cache::forget('error_log_sum'));
        static::deleted(fn () => Cache::forget('error_log_sum'));
    }

    public static function log(array $data = [])
    {
        return self::create([
            'subject' => $data['subject'] ?? '',
            'message' => $data['message'] ?? '',
            'jid' => $data['jid'] ?? null,
            'ip' => $data['ip'] ?? null,
            'created_at' => now(),
        ]);
    }
}
