<?php

use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

/**
 * @internal
 */
final class AuthorizationPermissionsTest extends CIUnitTestCase
{
    public function testRoleMatrixSeparatesAdministrativeCapabilities(): void
    {
        $config = new AuthGroups();
        $hasPermission = static fn (string $group, string $permission): bool => PermissionMatcher::matches(
            $permission,
            $config->matrix[$group] ?? [],
        );

        $this->assertTrue($hasPermission('superadmin', 'media.manage'));
        $this->assertTrue($hasPermission('superadmin', 'users.manage-admins'));
        $this->assertTrue($hasPermission('admin', 'content.manage'));
        $this->assertTrue($hasPermission('admin', 'users.delete'));
        $this->assertFalse($hasPermission('admin', 'users.manage-admins'));
        $this->assertTrue($hasPermission('developer', 'admin.access'));
        $this->assertTrue($hasPermission('developer', 'users.edit'));
        $this->assertFalse($hasPermission('developer', 'content.manage'));
        $this->assertFalse($hasPermission('developer', 'users.delete'));
        $this->assertFalse($hasPermission('user', 'admin.access'));
        $this->assertTrue($hasPermission('beta', 'beta.access'));
        $this->assertFalse($hasPermission('beta', 'admin.access'));
    }
}