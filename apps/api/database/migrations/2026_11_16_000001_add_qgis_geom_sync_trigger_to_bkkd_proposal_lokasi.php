<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Trigger BEFORE INSERT / UPDATE OF geom: hitung ulang panjang_gis_m otomatis & tandai 'belum_dianalisa' bila edit dilakukan langsung (misalnya via QGIS)
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fn_bkkd_proposal_lokasi_geom_sync()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.id IS NULL THEN
            NEW.id := gen_random_uuid();
        END IF;
        IF NEW.created_at IS NULL THEN
            NEW.created_at := NOW();
        END IF;
        IF NEW.updated_at IS NULL THEN
            NEW.updated_at := NOW();
        END IF;
        IF NEW.geom IS NOT NULL THEN
            NEW.panjang_gis_m := ROUND(ST_Length(NEW.geom::geography)::numeric, 2);
        END IF;
        IF NEW.status_analisa_spasial IS NULL OR NEW.status_analisa_spasial = '' THEN
            NEW.status_analisa_spasial := 'belum_dianalisa';
        END IF;
        RETURN NEW;
    END IF;

    IF TG_OP = 'UPDATE' AND (NEW.geom IS DISTINCT FROM OLD.geom) THEN
        IF NEW.geom IS NOT NULL THEN
            NEW.panjang_gis_m := ROUND(ST_Length(NEW.geom::geography)::numeric, 2);
        ELSE
            NEW.panjang_gis_m := 0;
        END IF;

        -- Jika update langsung pada geom (tanpa mengubah ringkasan_analisa / status_analisa_spasial lewat API), tandai perlu analisa ulang
        IF (NEW.ringkasan_analisa::text IS NOT DISTINCT FROM OLD.ringkasan_analisa::text)
           AND (NEW.status_analisa_spasial IS NOT DISTINCT FROM OLD.status_analisa_spasial) THEN
            NEW.status_analisa_spasial := 'belum_dianalisa';
        END IF;

        NEW.updated_at := NOW();
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bkkd_proposal_lokasi_geom_sync ON bkkd_proposal_lokasi;
CREATE TRIGGER trg_bkkd_proposal_lokasi_geom_sync
BEFORE INSERT OR UPDATE OF geom ON bkkd_proposal_lokasi
FOR EACH ROW
EXECUTE FUNCTION fn_bkkd_proposal_lokasi_geom_sync();

CREATE OR REPLACE FUNCTION fn_bkkd_proposal_parent_sync_from_lokasi()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.id_proposal IS NOT NULL AND NEW.status_analisa_spasial = 'belum_dianalisa' THEN
        UPDATE bkkd_proposal
        SET
            jumlah_lokasi = COALESCE((SELECT COUNT(*) FROM bkkd_proposal_lokasi WHERE id_proposal = NEW.id_proposal), 0),
            total_panjang_gis = COALESCE((SELECT ROUND(SUM(panjang_gis_m)::numeric, 2) FROM bkkd_proposal_lokasi WHERE id_proposal = NEW.id_proposal), 0),
            status_analisa_spasial = 'belum_dianalisa',
            updated_at = NOW()
        WHERE id = NEW.id_proposal;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bkkd_proposal_parent_sync_from_lokasi ON bkkd_proposal_lokasi;
CREATE TRIGGER trg_bkkd_proposal_parent_sync_from_lokasi
AFTER INSERT OR UPDATE OF geom ON bkkd_proposal_lokasi
FOR EACH ROW
EXECUTE FUNCTION fn_bkkd_proposal_parent_sync_from_lokasi();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_bkkd_proposal_parent_sync_from_lokasi ON bkkd_proposal_lokasi;
DROP FUNCTION IF EXISTS fn_bkkd_proposal_parent_sync_from_lokasi();
DROP TRIGGER IF EXISTS trg_bkkd_proposal_lokasi_geom_sync ON bkkd_proposal_lokasi;
DROP FUNCTION IF EXISTS fn_bkkd_proposal_lokasi_geom_sync();
SQL);
    }
};
