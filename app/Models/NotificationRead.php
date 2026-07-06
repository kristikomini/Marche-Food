<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRead extends Model
{
    protected $table = 'notification_reads';

    protected $fillable = ['notification_id', 'user_id', 'dismissed_at'];

    protected $casts = ['dismissed_at' => 'datetime'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }
}
