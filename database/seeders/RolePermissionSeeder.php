<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles, permissions, dan user default.
     *
     * Roles: super_admin, admin
     * Permissions: sesuai matriks tugas di plan.md bagian 2.1
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Create Permissions ────────────────────────────────────
        $permissions = [
            // Dashboard (F1)
            'dashboard.view',

            // User Management (F13)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Student Management (F2)
            'students.view',
            'students.create',
            'students.edit',
            'students.delete',

            // Class Management (F3)
            'classes.view',
            'classes.create',
            'classes.edit',
            'classes.delete',

            // Scheduler (F4)
            'schedules.view',
            'schedules.create',
            'schedules.edit',
            'schedules.approve',

            // Attendance (F5)
            'attendances.view',
            'attendances.create',
            'attendances.edit',

            // Payment (F6)
            'payments.view',
            'payments.create',
            'payments.edit',
            'payments.void',

            // Financial Tracking (F7)
            'financials.view',
            'financials.create',
            'financials.edit',

            // Student Report (F8)
            'reports.view',
            'reports.create',
            'reports.edit',

            // Inventory (F10)
            'inventory.view',
            'inventory.create',
            'inventory.edit',

            // Analytics (F11)
            'analytics.view',

            // Export (F12)
            'export.reports',

            // Activity Log (F15)
            'activity-log.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ─── Create Roles & Assign Permissions ─────────────────────

        // Super Admin — Full Access
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions($permissions); // All permissions

        // Admin — Operasional (tanpa user management & activity log & void payment)
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $adminPermissions = collect($permissions)->reject(function ($p) {
            return str_starts_with($p, 'users.') ||
                   $p === 'activity-log.view' ||
                   $p === 'payments.void' ||
                   $p === 'students.delete';
        })->toArray();
        $admin->syncPermissions($adminPermissions);

        // ─── Create Default Users ──────────────────────────────────

        // Super Admin
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@tarakanart.com'],
            [
                'full_name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );
        $superAdminUser->assignRole('super_admin');

        // Admin
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@tarakanart.com'],
            [
                'full_name' => 'Admin Operasional',
                'username' => 'admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
        $adminUser->assignRole('admin');
    }
}
