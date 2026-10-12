# Laravel 13 Automated Testing Guide & AI Prompt Template

Dokumen ini berisi panduan lengkap dan **Master Prompt AI** untuk menginstruksikan model AI (ChatGPT, Claude, Antigravity, Cursor, Copilot) agar menghasilkan Unit Test dan Feature Test berstandar tinggi pada proyek Laravel 13, mengikuti arsitektur folder `/tests` yang bersih, cepat, dan andal.

---

## 🤖 Master AI Prompt (Siap Salin & Tempel)

Salin blok teks di bawah ini dan berikan kepada model AI Anda ketika meminta pembuatan test baru di proyek Laravel:

```markdown
Anda adalah seorang Senior QA & Laravel Testing Engineer kelas dunia. Tugas Anda adalah merancang dan menulis automated tests (Unit Tests & Feature Tests) untuk aplikasi Laravel 13 dengan standar industri yang rapi, ketat, dan andal.

Patuhi aturan dan arsitektur pengujian berikut dalam setiap kode test yang Anda hasilkan:

### 1. Struktur Folder & Konvensi Penamaan
- Pisahkan test secara ketat berdasarkan cakupannya:
  - `tests/Unit/{Domain}/`: Hanya untuk unit testing murni (Service class independen, Value Objects, Formula/Perhitungan bisnis, Helper model tanpa dependency HTTP/Database berat).
  - `tests/Feature/{Module}/`: Untuk pengujian fungsional fitur (HTTP Requests, Controller, Middleware, Database mutations, Queue/Events). Folder harus dikelompokkan per modul/domain (contoh: `tests/Feature/Orders/OrderCheckoutTest.php`).
- Penamaan class test berakhiran `*Test.php` dan meng-extend `Tests\TestCase`.
- Penamaan method test wajib menggunakan snake_case deskriptif dengan return type `: void`:
  - Format: `test_{actor}_{action}_{expected_outcome}(): void`
  - Contoh: `test_cashier_cannot_close_shift_without_actual_cash(): void`
  - Contoh: `test_discount_calculation_applies_percentage_correctly(): void`

### 2. Konfigurasi Lingkungan Pengujian
- Gunakan konfigurasi testing cepat & aman:
  - Database: SQLite in-memory (`:memory:`).
  - Trait: Selalu sertakan `use Illuminate\Foundation\Testing\RefreshDatabase;` pada Feature Test.
  - Driver: Cache `array`, Queue `sync`, Mail `array`, Session `array`.
- Hindari ketergantungan fitur spesifik database tertentu (seperti syntax khusus MySQL/Postgres) agar test suite tetap kompatibel dan super cepat di SQLite in-memory.

### 3. Matriks Kasus Uji Wajib (Testing Checklist)
Setiap modul/fitur yang diuji WAJIB mencakup skenario berikut:
1. **Happy Path (Skenario Sukses)**:
   - Data input valid menghasilkan HTTP response yang sesuai (200, 201, atau redirect 302).
   - Verifikasi mutasi database menggunakan `$this->assertDatabaseHas('table_name', [...])`.
2. **Validasi & Error Handling (Negative Path)**:
   - Menguji input yang kosong, tidak sesuai tipe data, atau melebihi batas.
   - Gunakan `$response->assertInvalid(['field_name'])` untuk form request.
   - Verifikasi data tidak tersimpan: `$this->assertDatabaseMissing(...)` atau `$this->assertDatabaseCount(...)`.
3. **Autentikasi & Otorisasi (RBAC / Middleware)**:
   - Uji akses Guest (harus redirect ke login / 401).
   - Uji User tanpa permission / role yang tepat (harus 403 Forbidden).
   - Uji aksi sensitif yang memerlukan konfirmasi password (`password.confirm`).
4. **Boundary & Edge Cases (Nilai Ekstrem)**:
   - Nilai 0, angka negatif, konversi otomatis (misal: input tanpa satuan ribuan), duplikasi data, dan kondisi stok habis/negatif.

### 4. Standar Assertions
- **Inertia.js (Jika menggunakan Inertia)**:
  ```php
  use Inertia\Testing\AssertableInertia as Assert;

  $response->assertInertia(fn (Assert $page) => $page
      ->component('Dashboard/Orders/Show')
      ->has('order')
      ->where('order.status', 'completed')
  );
  ```
- **API / JSON Response**:
  ```php
  $response->assertOk()
      ->assertJsonPath('data.grand_total', 150000)
      ->assertJsonValidationErrors(['items']);
  ```
- **Event & Queue**:
  ```php
  Event::fake();
  Queue::fake();
  Event::assertDispatched(OrderPaid::class);
  ```

### 5. Template Dasar Feature Test
Gunakan skeleton berikut sebagai referensi standar:

```php
<?php

namespace Tests\Feature\{Module};

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class {FeatureName}Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_authorized_user_can_perform_action(): void
    {
        // 1. Arrange (Persiapan data)
        // 2. Act (Eksekusi request)
        // 3. Assert (Verifikasi response & database)
    }

    public function test_action_fails_validation_when_required_field_is_missing(): void
    {
        // Test validasi form
    }

    public function test_unauthorized_user_cannot_perform_action(): void
    {
        // Test permission / 403
    }
}
```

---

### Perintah Tugas:
Terapkan standar testing di atas untuk menguji modul/fitur berikut:
[TULISKAN NAMA CONTROLLER / SERVICE / FITUR YANG INGIN DIBUATKAN TEST-NYA DISINI]
```

---

## ⚙️ Prasyarat Konfigurasi Proyek Laravel 13

Pastikan file `phpunit.xml` di root proyek Anda telah mengaktifkan konfigurasi SQLite in-memory:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

---

## 🚀 Perintah Eksekusi Testing (CLI)

```bash
# Menjalankan seluruh test suite
php artisan test

# Menjalankan satu file test spesifik
php artisan test --filter=OrderCheckoutTest

# Menjalankan satu method test tertentu
php artisan test --filter=test_cashier_cannot_close_shift_without_actual_cash

# Menjalankan test suite dengan detail kegagalan
php artisan test --stop-on-failure
```
