<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

        if (!\Illuminate\Support\Facades\Schema::hasTable('jalan_porosdesa')) {
            \Illuminate\Support\Facades\Schema::create('jalan_porosdesa', function ($table) {
                $table->uuid('id')->primary();
                $table->integer('kode_ruas')->nullable();
                $table->string('nama_ruas')->nullable();
                $table->string('desa')->nullable();
                $table->string('kecamatan')->nullable();
                $table->decimal('panjang', 10, 2)->nullable();
                $table->decimal('lebar', 5, 2)->nullable();
                $table->string('perkerasan')->nullable();
                $table->string('kondisi')->nullable();
                $table->string('status_awal')->nullable();
                $table->string('status_eksisting')->nullable();
                $table->string('sumber_data')->nullable();
                $table->integer('id_desa')->nullable();
                $table->integer('id_kecamatan')->nullable();
                $table->text('geom')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_user_creation_triggers_audit_log(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin_test@example.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret')]
        );

        $this->actingAs($admin);

        $initialCount = AuditLog::count();

        $user = User::create([
            'name' => 'John Auditor',
            'email' => 'john_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'users',
            'event' => 'created',
            'auditable_id' => $user->getKey(),
        ]);

        $latestLog = AuditLog::latest('id')->first();
        $this->assertNotNull($latestLog);
        $this->assertArrayNotHasKey('password', $latestLog->after ?? []);
    }

    public function test_admin_can_access_audit_logs_api(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
        );

        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'audit-logs-view', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'data',
                'current_page',
                'total',
            ],
            'options' => [
                'users',
                'modules',
                'events',
            ],
        ]);
    }

    public function test_jalan_poros_desa_creation_with_raw_expression_triggers_audit_log_without_error(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
        );

        $this->actingAs($admin);

        $ruas = new \App\Models\JalanPorosDesa();
        $ruas->id = (string) \Illuminate\Support\Str::uuid();
        $ruas->nama_ruas = 'Jalan Poros Desa Tes';
        $ruas->kode_ruas = 999;
        $ruas->geom = \Illuminate\Support\Facades\DB::raw("'LINESTRING(0 0, 1 1)'");
        $ruas->save();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'jalan-poros-desa',
            'event' => 'created',
            'auditable_id' => $ruas->id,
            'target_label' => 'Jalan Poros Desa Tes',
        ]);

        $log = AuditLog::where('auditable_id', $ruas->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Data Geometri', $log->after['geom'] ?? '');
    }

    public function test_auth_login_and_logout_triggers_audit_logs(): void
    {
        $user = User::create([
            'name' => 'Auth Tester',
            'email' => 'auth_test@example.com',
            'password' => bcrypt('password123'),
            'status' => true,
        ]);

        // 1. Failed login
        $responseFailed = $this->postJson('/api/v1/login', [
            'email' => 'auth_test@example.com',
            'password' => 'wrongpassword',
        ]);
        $responseFailed->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'auth',
            'event' => 'login.failed',
        ]);

        // 2. Successful login
        $responseSuccess = $this->postJson('/api/v1/login', [
            'email' => 'auth_test@example.com',
            'password' => 'password123',
        ]);
        $responseSuccess->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'auth',
            'event' => 'login',
            'user_id' => $user->id,
        ]);

        // 3. Logout
        $responseLogout = $this->actingAs($user)->postJson('/api/v1/logout');
        $responseLogout->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'auth',
            'event' => 'logout',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'module' => 'users',
            'event' => 'updated',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_account_profile_and_password_changes_trigger_security_audit_logs(): void
    {
        $user = User::create([
            'name' => 'Profile User',
            'email' => 'profile_user@example.com',
            'password' => bcrypt('oldpassword123'),
            'status' => true,
        ]);

        // 1. Profile update
        $resProfile = $this->actingAs($user)->postJson('/api/v1/account/update', [
            'name' => 'Profile User Updated',
            'email' => 'profile_user@example.com',
        ]);
        $resProfile->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'security',
            'event' => 'profile.updated',
            'user_id' => $user->id,
        ]);

        // 2. Password change
        $resPassword = $this->actingAs($user)->postJson('/api/v1/account/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $resPassword->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'security',
            'event' => 'password.changed',
            'user_id' => $user->id,
        ]);

        // Verify password is never leaked into before or after
        $passwordLog = AuditLog::where('event', 'password.changed')->first();
        $this->assertNotNull($passwordLog);
        $this->assertNull($passwordLog->before);
        $this->assertNull($passwordLog->after);
    }

    public function test_role_crud_triggers_audit_logs(): void
    {
        $admin = User::create([
            'name' => 'Admin Role Tester',
            'email' => 'admin_role@example.com',
            'password' => bcrypt('secret'),
            'status' => true,
        ]);

        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'roles.manage', 'guard_name' => 'web']);
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);
        $admin->assignRole($adminRole);

        // 1. Create Role
        $resStore = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'editor-test',
        ]);
        $resStore->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'roles',
            'event' => 'created',
            'target_label' => 'editor-test',
        ]);

        $roleId = $resStore->json('role.id');

        // 2. Update Role
        $resUpdate = $this->actingAs($admin)->putJson("/api/v1/admin/roles/{$roleId}", [
            'name' => 'editor-updated',
        ]);
        $resUpdate->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'roles',
            'event' => 'updated',
            'target_label' => 'editor-updated',
        ]);

        // 3. Delete Role
        $resDelete = $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$roleId}");
        $resDelete->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'roles',
            'event' => 'deleted',
            'target_label' => 'editor-updated',
        ]);
    }
}
