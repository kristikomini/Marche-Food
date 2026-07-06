<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'chiave', 'livello', 'titolo', 'messaggio', 'url', 'signature',
    ];

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id');
    }
}
