<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditLogService
{
    public function log(
        string $event,
        string $module,
        Model|array|null $auditable,
        string $description,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        ?Authenticatable $actor = null
    ): AuditLog {
        $request = request();
        $resolvedActor = $actor ?? auth()->user();

        return AuditLog::create([
            'user_id' => $resolvedActor?->getAuthIdentifier(),
            'event' => $event,
            'module' => $module,
            'auditable_type' => $auditable instanceof Model ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable instanceof Model ? $auditable->getKey() : null,
            'target_label' => $this->resolveTargetLabel($auditable),
            'description' => $description,
            'before' => $this->sanitizePayload($before),
            'after' => $this->sanitizePayload($after),
            'meta' => $this->sanitizePayload($meta),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function sanitizePayload(?array $payload): ?array
    {
        if (blank($payload)) {
            return null;
        }

        $spatialFields = ['geom', 'geometry', 'coordinates', 'geojson', 'wkt', 'line_geometry', 'polygon_geometry'];
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if ($value instanceof \Illuminate\Contracts\Database\Query\Expression || (is_object($value) && str_ends_with(get_class($value), 'Expression'))) {
                $sanitized[$key] = in_array($lowerKey, $spatialFields, true)
                    ? '[Data Geometri PostGIS]'
                    : '[Database Expression]';
                continue;
            }

            if (in_array($lowerKey, $spatialFields, true)) {
                if (is_string($value) && strlen($value) > 100) {
                    $sanitized[$key] = '[Data Geometri: ' . strlen($value) . ' bytes]';
                    continue;
                }
                if (is_array($value)) {
                    $count = count($value, COUNT_RECURSIVE);
                    $sanitized[$key] = "[Array Koordinat: {$count} elemen]";
                    continue;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $this->normalizePayload($sanitized);
    }

    public function only(array $source, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }

    public function normalizePayload(?array $payload): ?array
    {
        if (blank($payload)) {
            return null;
        }

        $normalized = [];

        foreach ($payload as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return blank($normalized) ? null : $normalized;
    }

    public function maskAccountNumber(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: $value;
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', max($length, 1));
        }

        return str_repeat('*', $length - 4).substr($digits, -4);
    }

    public function credentialState(?string $before, ?string $after): ?string
    {
        $hadBefore = filled($before);
        $hasAfter = filled($after);

        if (! $hadBefore && ! $hasAfter) {
            return null;
        }

        if (! $hadBefore && $hasAfter) {
            return 'configured';
        }

        if ($hadBefore && ! $hasAfter) {
            return 'removed';
        }

        if ($before !== $after) {
            return 'updated';
        }

        return 'unchanged';
    }

    public function summarizeItems(iterable $items, callable $resolver): array
    {
        return collect($items)
            ->map($resolver)
            ->values()
            ->all();
    }

    public function roleNames($roles): array
    {
        return collect($roles)
            ->map(fn ($role) => is_string($role) ? $role : $role->name)
            ->filter()
            ->values()
            ->all();
    }

    public function permissionNames($permissions): array
    {
        return collect($permissions)
            ->map(fn ($permission) => is_string($permission) ? $permission : $permission->name)
            ->filter()
            ->values()
            ->all();
    }

    private function resolveTargetLabel(Model|array|null $auditable): ?string
    {
        if ($auditable instanceof Model) {
            foreach (['nama_ruas', 'nama_desa', 'nama_kecamatan', 'nama_jalan', 'title', 'name', 'code', 'invoice'] as $attribute) {
                $value = $auditable->getAttribute($attribute);

                if (filled($value) && is_scalar($value)) {
                    return (string) $value;
                }
            }

            return class_basename($auditable).' #'.$auditable->getKey();
        }

        if (is_array($auditable)) {
            foreach (['target_label', 'nama_ruas', 'nama_desa', 'nama_kecamatan', 'nama_jalan', 'name', 'code', 'invoice'] as $key) {
                $value = $auditable[$key] ?? null;
                if (filled($value) && is_scalar($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($item) => $this->normalizeValue($item))->values()->all();
        }

        if ($value instanceof Model) {
            return [
                'id' => $value->getKey(),
                'type' => class_basename($value),
                'label' => $this->resolveTargetLabel($value),
            ];
        }

        if ($value instanceof \Illuminate\Contracts\Database\Query\Expression || (is_object($value) && str_ends_with(get_class($value), 'Expression'))) {
            return '[Database Expression]';
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_bool($value) || is_null($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value)) {
            if ($value instanceof \Stringable || method_exists($value, '__toString')) {
                return Str::limit((string) $value, 1000, '');
            }
            return '[' . class_basename($value) . ']';
        }

        return Str::limit((string) $value, 1000, '');
    }
}
