<?php

namespace App\Helpers;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class SpatialHelper
{
    /**
     * Memeriksa apakah koneksi database yang digunakan adalah SQLite.
     */
    public static function isSqlite(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'sqlite';
    }

    /**
     * Mengembalikan operator LIKE yang sesuai dengan driver (ilike untuk PostgreSQL, like untuk SQLite/MySQL).
     */
    public static function likeOperator(?string $connection = null): string
    {
        return static::isSqlite($connection) ? 'like' : 'ilike';
    }

    /**
     * Mengembalikan ekspresi SQL untuk menghitung panjang kolom geometri dalam satuan meter.
     */
    public static function lengthExpression(string $column = 'geom', ?string $connection = null): string
    {
        if (static::isSqlite($connection)) {
            return "ROUND(ST_Length({$column}), 2)";
        }

        return "ROUND((ST_Length({$column}::geography))::numeric, 2)";
    }

    /**
     * Mengembalikan ekspresi SQL SELECT kolom panjang dengan alias (default: panjang_meter).
     */
    public static function lengthColumn(string $column = 'geom', string $alias = 'panjang_meter', ?string $connection = null): string
    {
        $expr = static::lengthExpression($column, $connection);

        return "{$expr} as {$alias}";
    }

    /**
     * Mengembalikan ekspresi SQL agregat total panjang dalam satuan meter.
     */
    public static function sumLengthMeterExpression(string $column = 'geom', ?string $connection = null): string
    {
        if (static::isSqlite($connection)) {
            return "COALESCE(ROUND(SUM(ST_Length({$column})), 2), 0)";
        }

        return "COALESCE(ROUND(SUM(ST_Length({$column}::geography))::numeric, 2), 0)";
    }

    /**
     * Mengembalikan ekspresi SQL agregat total panjang dalam satuan kilometer.
     */
    public static function sumLengthKmExpression(string $column = 'geom', ?string $connection = null): string
    {
        if (static::isSqlite($connection)) {
            return "COALESCE(ROUND(SUM(ST_Length({$column})) / 1000, 3), 0)";
        }

        return "COALESCE(ROUND((SUM(ST_Length({$column}::geography)) / 1000)::numeric, 3), 0)";
    }

    /**
     * Menghitung panjang dari GeoJSON string / array secara akurat via database query.
     */
    public static function calculateGeoJsonLength(string|array $geometry, float $fallback = 0.0, ?string $connection = null): float
    {
        $geoJsonString = is_string($geometry) ? $geometry : json_encode($geometry);
        $escaped = str_replace("'", "''", $geoJsonString);

        if (static::isSqlite($connection)) {
            $sql = "SELECT ROUND(ST_Length(ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('{$escaped}')), 4326)), 2) AS len";
        } else {
            $sql = "SELECT ROUND((ST_Length(ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('{$escaped}')), 4326)::geography))::numeric, 2) AS len";
        }

        $calc = DB::connection($connection)->selectOne($sql);

        return $calc && isset($calc->len) ? (float) $calc->len : $fallback;
    }

    /**
     * Membuat DB::raw Expression aman untuk konversi GeoJSON ke PostGIS MultiLineString SRID 4326.
     */
    public static function geomFromGeoJsonRaw(string|array $geometry, int $srid = 4326, ?string $connection = null): Expression
    {
        $geoJsonString = is_string($geometry) ? $geometry : json_encode($geometry);
        $pdo = DB::connection($connection)->getPdo();

        return DB::raw("ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($geoJsonString) . "), {$srid}))");
    }
}
