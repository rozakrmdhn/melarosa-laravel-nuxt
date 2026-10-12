<?php

namespace Tests\Unit\Models;

use App\Models\JalanPorosDesa;
use Tests\TestCase;

class JalanPorosDesaModelTest extends TestCase
{
    public function test_model_converts_to_valid_geojson_feature_array(): void
    {
        $ruas = new JalanPorosDesa();
        $ruas->id = '01a12f8d-ac8f-7393-b136-cd55414d3d0c';
        $ruas->kode_ruas = 105;
        $ruas->nama_ruas = 'Jalan Poros Unit Test';
        $ruas->desa = 'Desa Sukorejo';
        $ruas->kecamatan = 'Kecamatan Bojonegoro';
        $ruas->panjang = 1500.5;
        $ruas->lebar = 4.0;
        $ruas->perkerasan = 'Aspal';
        $ruas->kondisi = 'Baik';
        $ruas->status_awal = 'Jalan Desa';
        $ruas->status_eksisting = 'Jalan Kabupaten';
        $ruas->sumber_data = 'Dinas Bina Marga';
        $ruas->id_desa = 3522010001;
        $ruas->id_kecamatan = 3522010;
        $ruas->geojson = json_encode([
            'type' => 'LineString',
            'coordinates' => [
                [111.8901, -7.1501],
                [111.8950, -7.1550],
            ],
        ]);
        $ruas->centroid = json_encode([
            'type' => 'Point',
            'coordinates' => [111.8925, -7.1525],
        ]);

        $feature = $ruas->toGeoJsonFeature();

        $this->assertEquals('Feature', $feature['type']);
        $this->assertEquals('01a12f8d-ac8f-7393-b136-cd55414d3d0c', $feature['id']);
        $this->assertIsObject($feature['geometry']);
        $this->assertEquals('LineString', $feature['geometry']->type);

        $properties = $feature['properties'];
        $this->assertEquals(105, $properties['kode_ruas']);
        $this->assertEquals('Jalan Poros Unit Test', $properties['nama_ruas']);
        $this->assertEquals('Aspal', $properties['perkerasan']);
        $this->assertEquals('Baik', $properties['kondisi']);
        $this->assertEquals(1500.5, $properties['panjang']);
        $this->assertIsObject($properties['centroid']);
        $this->assertEquals('Point', $properties['centroid']->type);
    }

    public function test_model_casts_attributes_correctly(): void
    {
        $ruas = new JalanPorosDesa([
            'kode_ruas'    => '101',
            'panjang'      => '1250.75',
            'lebar'        => '4.5',
            'id_desa'      => '3522010001',
            'id_kecamatan' => '3522010',
        ]);

        $this->assertIsInt($ruas->kode_ruas);
        $this->assertEquals(101, $ruas->kode_ruas);

        $this->assertIsFloat($ruas->panjang);
        $this->assertEquals(1250.75, $ruas->panjang);

        $this->assertIsFloat($ruas->lebar);
        $this->assertEquals(4.5, $ruas->lebar);

        $this->assertIsInt($ruas->id_desa);
        $this->assertIsInt($ruas->id_kecamatan);
    }
}
