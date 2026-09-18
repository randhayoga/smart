<?php

namespace Tests\Unit\Models;

use App\Models\AdmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_adm_user_can_be_created_with_auto_generated_id(): void
    {
        $employeeId = '987654';

        $user = User::factory()->create([
            'employee_id' => $employeeId,
            'employee_name' => 'Adm Test Employee',
        ]);

        $admUser = AdmUser::create([
            'login_name' => $employeeId,
            'name' => 'Adm Test Employee',
            'password' => md5('SecretTestPass123!'),
            'employee_id' => $employeeId,
            'active' => 1,
        ]);

        $this->assertNotNull($admUser->id_adm_user);
        $this->assertTrue($admUser->active);
        $this->assertEquals($employeeId, $admUser->login_name);
        $this->assertEquals(md5('SecretTestPass123!'), $admUser->password);

        // Verify relationship
        $this->assertInstanceOf(User::class, $admUser->employee);
        $this->assertEquals($user->id, $admUser->employee->id);

        // Verify reverse relationship
        $user->refresh();
        $this->assertInstanceOf(AdmUser::class, $user->admUser);
        $this->assertEquals($admUser->id_adm_user, $user->admUser->id_adm_user);
        $this->assertEquals(md5('SecretTestPass123!'), $user->getAuthPassword());
    }

    public function test_adm_user_can_be_created_via_factory(): void
    {
        $admUser = AdmUser::factory()->create();

        $this->assertNotNull($admUser->id_adm_user);
        $this->assertNotNull($admUser->login_name);
        $this->assertEquals(md5('password'), $admUser->password);
        $this->assertInstanceOf(User::class, $admUser->employee);
    }
}

