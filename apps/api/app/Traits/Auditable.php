<?php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::dispatchAuditEvent('created', $model, null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $dirtyKeys = array_keys($model->getDirty());
            $before = array_intersect_key($model->getOriginal(), array_flip($dirtyKeys));
            $after = $model->getChanges();

            unset(
                $before['updated_at'],
                $after['updated_at'],
                $before['remember_token'],
                $after['remember_token']
            );
            if (empty($after)) {
                return;
            }

            self::dispatchAuditEvent('updated', $model, $before, $after);
        });

        static::deleted(function (Model $model) {
            self::dispatchAuditEvent('deleted', $model, $model->getOriginal(), null);
        });
    }

    protected static function dispatchAuditEvent(string $event, Model $model, ?array $before, ?array $after): void
    {
        $module = property_exists($model, 'auditModule')
            ? $model->auditModule
            : strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', class_basename($model)));

        $hidden = array_flip($model->getHidden());
        if ($before) {
            $before = array_diff_key($before, $hidden);
        }
        if ($after) {
            $after = array_diff_key($after, $hidden);
        }

        $rawLabel = $model->getAttribute('nama_desa')
            ?? $model->getAttribute('nama_kecamatan')
            ?? $model->getAttribute('nama_ruas')
            ?? $model->getAttribute('nama_jalan')
            ?? $model->getAttribute('name')
            ?? ('#'.$model->getKey());

        $label = is_scalar($rawLabel) ? (string) $rawLabel : ('#'.$model->getKey());

        $formattedModule = str_replace('-', ' ', $module);
        $description = sprintf('%s data %s "%s"', ucfirst($event), $formattedModule, $label);

        app(AuditLogService::class)->log(
            event: $event,
            module: $module,
            auditable: $model,
            description: $description,
            before: $before,
            after: $after
        );
    }
}
