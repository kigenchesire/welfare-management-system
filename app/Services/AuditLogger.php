<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token', 'sms_api_key'];

    public static function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $description = null): AuditLog
    {
        $user = auth()->user();
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'auditable_type' => $subject ? get_class($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 250) : null,
            'old_values' => $old ? self::clean($old) : null,
            'new_values' => $new ? self::clean($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null,
            'created_at' => now(),
        ]);
    }

    /** Log only the attributes that actually changed on a model (call before save()). */
    public static function logChanges(string $action, Model $model, ?string $description = null): void
    {
        $dirty = Arr::except($model->getDirty(), ['updated_at']);
        if (! $dirty) {
            return;
        }
        $old = array_intersect_key($model->getOriginal(), $dirty);
        self::log($action, $model, $old, $dirty, $description);
    }

    private static function clean(array $values): array
    {
        foreach (self::HIDDEN as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '********';
            }
        }

        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $values);
    }
}
