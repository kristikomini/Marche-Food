<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps created_by / updated_by AND writes an append-only entry to `audit_logs`
 * for every create, update, (soft/force) delete and restore, capturing the
 * before→after values of each changed field. See App\Models\AuditLog.
 */
trait Auditable
{
    /**
     * Attributes never recorded as a "change" (noise / audit plumbing).
     * `deleted_at` is excluded because soft-delete/restore are captured by the
     * dedicated `deleted` / `restored` events — otherwise restore() (which calls
     * save()) would also emit a noisy `updated` diff.
     */
    protected static array $auditIgnore = ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at'];

    protected static function bootAuditable(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::created(fn ($model) => static::writeAuditLog($model, 'created'));
        static::updated(fn ($model) => static::writeAuditLog($model, 'updated'));

        static::deleted(function ($model) {
            $forced = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
            static::writeAuditLog($model, $forced ? 'force_deleted' : 'deleted');
        });

        // `restored` is provided only by the SoftDeletes trait — registering it on
        // a non-SoftDeletes model (e.g. Recall) throws during boot.
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => static::writeAuditLog($model, 'restored'));
        }
    }

    protected static function writeAuditLog($model, string $event): void
    {
        $changes = null;

        if ($event === 'updated') {
            $changes = static::auditableDiff($model);
            if (empty($changes)) {
                return; // nothing meaningful changed (e.g. only updated_by)
            }
        } elseif ($event === 'created') {
            $changes = collect($model->getAttributes())
                ->except(array_merge(['id', 'deleted_at'], static::$auditIgnore))
                ->all();
        }

        AuditLog::create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id'   => $model->getKey(),
            'event'          => $event,
            'user_id'        => Auth::id(),
            'changes'        => $changes,
            'etichetta'      => static::auditLabel($model),
        ]);
    }

    /**
     * before→after diff of the fields changed in the current save.
     *
     * @return array<string,array{da:mixed,a:mixed}>
     */
    protected static function auditableDiff($model): array
    {
        $out = [];
        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, static::$auditIgnore, true)) {
                continue;
            }
            $out[$key] = ['da' => $model->getOriginal($key), 'a' => $new];
        }

        return $out;
    }

    /** A human label snapshot so the log stays readable after a permanent delete. */
    protected static function auditLabel($model): ?string
    {
        foreach (['numero_documento', 'lotto_produzione', 'numero_bolla', 'componente', 'lotto'] as $attr) {
            if (! empty($model->getAttribute($attr))) {
                return (string) $model->getAttribute($attr);
            }
        }

        return null;
    }
}
