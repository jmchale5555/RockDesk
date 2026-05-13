<?php

use Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUsernameIsNormalizedForLogin(): void
    {
        $user = new User;

        $this->assertSame('j.smith', $user->normalizeUsername('  J.Smith  '));
    }

    public function testUsernameAcceptsActiveDirectoryFriendlyCharacters(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidUsername('john.smith'));
        $this->assertTrue($user->isValidUsername('john-smith'));
        $this->assertTrue($user->isValidUsername('john_smith'));
    }

    public function testUsernameRejectsUnsafeOrTooShortValues(): void
    {
        $user = new User;

        $this->assertFalse($user->isValidUsername('js'));
        $this->assertFalse($user->isValidUsername('john smith'));
        $this->assertFalse($user->isValidUsername('john@example.com'));
    }

    public function testOnlyKnownRolesAreValid(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidRole('user'));
        $this->assertTrue($user->isValidRole('staff'));
        $this->assertTrue($user->isValidRole('admin'));
        $this->assertFalse($user->isValidRole('superadmin'));
    }

    public function testOnlyKnownAuthProvidersAreValid(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidAuthProvider('local'));
        $this->assertTrue($user->isValidAuthProvider('ldap'));
        $this->assertFalse($user->isValidAuthProvider('oauth'));
    }
}
