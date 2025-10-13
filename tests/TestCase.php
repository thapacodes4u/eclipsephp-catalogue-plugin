<?php

namespace Tests;

use Filament\Facades\Filament;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Workbench\App\Models\Site;
use Workbench\App\Models\User;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    protected ?User $superAdmin = null;

    protected ?User $user = null;

    protected ?Site $site = null;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('filament-shield.register_role_policy', false);
    }

    protected function setUp(): void
    {
        // Always show errors when testing
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Increase memory limit to 512M
        ini_set('memory_limit', '512M');

        parent::setUp();

        // Disable Scout during tests to prevent indexing operations
        // This speeds up tests and avoids external dependencies on search services
        config(['scout.driver' => null]);

        $this->withoutVite();

        // Ensure we have at least one site for testing
        if (Site::count() === 0) {
            Site::factory()->create();
        }
    }

    /**
     * Run database migrations
     */
    protected function migrate(): self
    {
        $this->artisan('migrate');

        return $this;
    }

    /**
     * Set up default "super admin" user (without tenant)
     */
    protected function setUpSuperAdmin(): self
    {
        $this->superAdmin = User::factory()->create();

        // Assign super admin role and give all permissions
        $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $this->superAdmin->assignRole($superAdminRole);
            // Give all permissions to super admin role
            $permissions = \Spatie\Permission\Models\Permission::all();
            $superAdminRole->syncPermissions($permissions);
        }

        $this->actingAs($this->superAdmin);

        return $this;
    }

    /**
     * Set up default "super admin" user and tenant
     */
    protected function setUpSuperAdminAndTenant(): self
    {
        $this->setUpSuperAdmin();

        $site = Site::first();

        $this->superAdmin->sites()->attach($site);

        Filament::setTenant($site);

        return $this;
    }

    /**
     * Set up a common user with no roles or permissions
     */
    protected function setUpCommonUser(): self
    {
        $this->user = User::factory()->create();

        $this->actingAs($this->user);

        return $this;
    }

    /**
     * Create permissions for all resources
     */
    protected function createPermissions(): self
    {
        $resources = [
            'category',
            'group',
            'measure_unit',
            'price_list',
            'product',
            'product_status',
            'product_type',
            'property',
            'property_value',
            'role',
            'tax_class',
        ];

        $permissions = [
            'view_any',
            'view',
            'create',
            'update',
            'restore',
            'restore_any',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
        ];

        foreach ($resources as $resource) {
            foreach ($permissions as $permission) {
                \Spatie\Permission\Models\Permission::firstOrCreate([
                    'name' => $permission.'_'.$resource,
                    'guard_name' => 'web',
                ]);
            }
        }

        return $this;
    }

    /**
     * Create roles
     */
    protected function createRoles(): self
    {
        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'panel_user',
            'guard_name' => 'web',
        ]);

        return $this;
    }

    public function ignorePackageDiscoveriesFrom()
    {
        return [
            // A list of packages that should not be auto-discovered when running tests
            'laravel/telescope',
        ];
    }
}
