<?php

use PHPUnit\Framework\TestCase;

final class AuthHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['USER']);
    }

    public function testCurrentUserHelpersReturnSessionUserDetails(): void
    {
        $_SESSION['USER'] = (object)[
            'id' => 42,
            'role' => 'staff',
        ];

        $this->assertSame(42, current_user_id());
        $this->assertSame('staff', current_user_role());
        $this->assertSame($_SESSION['USER'], current_user());
    }

    public function testGuestRoleIsReturnedWithoutSessionUser(): void
    {
        $this->assertNull(current_user_id());
        $this->assertSame('guest', current_user_role());
    }

    public function testHasRoleAcceptsSingleRoleOrRoleList(): void
    {
        $staff = (object)['role' => 'staff'];

        $this->assertTrue(has_role('staff', $staff));
        $this->assertTrue(has_role(['staff', 'admin'], $staff));
        $this->assertFalse(has_role('admin', $staff));
    }

    public function testStaffOrAdminHelperMatchesOnlyOperationalRoles(): void
    {
        $this->assertFalse(is_staff_or_admin((object)['role' => 'user']));
        $this->assertTrue(is_staff_or_admin((object)['role' => 'staff']));
        $this->assertTrue(is_staff_or_admin((object)['role' => 'admin']));
    }

    public function testAdminHelperMatchesOnlyAdminRole(): void
    {
        $this->assertFalse(is_admin((object)['role' => 'staff']));
        $this->assertTrue(is_admin((object)['role' => 'admin']));
    }

    public function testStaffAndAdminCanAccessAnyTicket(): void
    {
        $ticket = (object)['user_id' => 10];

        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 20, 'role' => 'staff']));
        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 30, 'role' => 'admin']));
    }

    public function testNormalUserCanOnlyAccessOwnTicket(): void
    {
        $ticket = (object)['user_id' => 10];

        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 10, 'role' => 'user']));
        $this->assertFalse(can_access_ticket($ticket, (object)['id' => 11, 'role' => 'user']));
    }

    public function testFinalActiveAdminIsProtected(): void
    {
        $activeAdmin = (object)['role' => 'admin', 'is_active' => 1];
        $inactiveAdmin = (object)['role' => 'admin', 'is_active' => 0];
        $staff = (object)['role' => 'staff', 'is_active' => 1];

        $this->assertTrue(is_final_active_admin($activeAdmin, 1));
        $this->assertFalse(is_final_active_admin($activeAdmin, 2));
        $this->assertFalse(is_final_active_admin($inactiveAdmin, 1));
        $this->assertFalse(is_final_active_admin($staff, 1));
    }
}
