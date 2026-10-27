<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\Illuminate\Support\Facades\Schema::hasTable('bataswilayah_kecamatan')) {
            \Illuminate\Support\Facades\Schema::create('bataswilayah_kecamatan', function ($table) {
                $table->increments('id');
                $table->string('nama_kecamatan')->nullable();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('bataswilayah_desa')) {
            \Illuminate\Support\Facades\Schema::create('bataswilayah_desa', function ($table) {
                $table->bigIncrements('id');
                $table->string('nama_desa')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_guest_cannot_access_active_users(): void
    {
        $response = $this->getJson('/api/v1/admin/users/active');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_active_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/users/active');
        $response->assertStatus(403);
    }

    public function test_admin_can_retrieve_active_users_list_with_device_details(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Operator',
            'email' => 'admin_active@test.com',
        ]);

        $permission = Permission::firstOrCreate(['name' => 'users-view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $activeUser = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@test.com',
        ]);
        $activeUser->assignRole($role);

        $inactiveUser = User::factory()->create([
            'name' => 'Siti Aminah',
            'email' => 'siti@test.com',
        ]);
        $inactiveUser->assignRole($role);

        // Session aktif untuk Budi (1 menit yang lalu)
        Session::create([
            'id' => 'session_budi_123',
            'user_id' => $activeUser->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'dummy_payload',
            'last_activity' => now()->subMinute()->timestamp,
        ]);

        // Session kedaluwarsa untuk Siti (30 menit yang lalu)
        Session::create([
            'id' => 'session_siti_456',
            'user_id' => $inactiveUser->id,
            'ip_address' => '192.168.1.60',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => 'dummy_payload',
            'last_activity' => now()->subMinutes(30)->timestamp,
        ]);

        // Session admin sendiri (baru saja)
        Session::create([
            'id' => 'session_admin_789',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'payload' => 'dummy_payload',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users/active?threshold=5');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'threshold_minutes' => 5,
            'total_active' => 2,
        ]);

        $data = $response->json();
        $userIds = collect($data['users'])->pluck('id')->all();

        $this->assertContains($admin->id, $userIds);
        $this->assertContains($activeUser->id, $userIds);
        $this->assertNotContains($inactiveUser->id, $userIds);

        // Cek info detail session
        $budiRecord = collect($data['users'])->firstWhere('id', $activeUser->id);
        $this->assertNotNull($budiRecord);
        $this->assertEquals('192.168.1.50', $budiRecord['latest_session']['ip_address']);
        $this->assertNotNull($budiRecord['latest_session']['device_name']);
        $this->assertNotNull($budiRecord['latest_session']['last_activity_human']);
    }

    public function test_users_index_includes_online_flag_and_filters_online_only(): void
    {
        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
        ]);

        $permission = Permission::firstOrCreate(['name' => 'users-view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $onlineUser = User::factory()->create([
            'name' => 'User Online',
            'email' => 'online@test.com',
        ]);

        $offlineUser = User::factory()->create([
            'name' => 'User Offline',
            'email' => 'offline@test.com',
        ]);

        Session::create([
            'id' => 'session_online_1',
            'user_id' => $onlineUser->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestBrowser',
            'payload' => 'payload',
            'last_activity' => now()->subMinutes(2)->timestamp,
        ]);

        // Cek users list biasa: memuat field is_online
        $responseAll = $this->actingAs($admin)->getJson('/api/v1/admin/users');
        $responseAll->assertStatus(200);

        $items = $responseAll->json('users.data');
        $onlineRecord = collect($items)->firstWhere('id', $onlineUser->id);
        $offlineRecord = collect($items)->firstWhere('id', $offlineUser->id);

        $this->assertTrue($onlineRecord['is_online']);
        $this->assertFalse($offlineRecord['is_online']);

        // Cek filter online_only=1
        $responseOnlineOnly = $this->actingAs($admin)->getJson('/api/v1/admin/users?online_only=1');
        $responseOnlineOnly->assertStatus(200);

        $filteredIds = collect($responseOnlineOnly->json('users.data'))->pluck('id')->all();
        $this->assertContains($onlineUser->id, $filteredIds);
        $this->assertNotContains($offlineUser->id, $filteredIds);
    }
}
