<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single append-only audit entry. Deliberately NOT Auditable itself (no
 * recursion) and write-once — the app never updates or deletes these rows.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // only created_at

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'changes',
        'etichetta',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
