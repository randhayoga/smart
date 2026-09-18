<?php

namespace App\Services\Auth;

use App\Models\Portal\CISession;
use App\Models\User;
use Illuminate\Support\Carbon;

class VerifySession
{
    /**
     * Verify CI Session and return the authenticatable User model from USER_HRIS.
     *
     * @param string $ciSession
     * @return User|false
     */
    public function checkAndGetUserByCiSession(string $ciSession): User|false
    {
        $model = CISession::where('id', $ciSession)->first();

        if (!$model) {
            return false;
        }

        // Validate session expiration against configured lifetime
        $sessionTime = Carbon::createFromTimestamp((int) $model->timestamp);
        $currentTime = Carbon::now();
        $lifetime = (int) config('auth.portal_sso.session_lifetime', env('SESSION_LIFETIME', 120));

        $isStillValid = $sessionTime->copy()->addMinutes($lifetime)->gt($currentTime);

        if (!$isStillValid) {
            return false;
        }

        // Decode CodeIgniter serialized PHP session
        $dataCiSession = $this->parseCiSession($model);

        if (!isset($dataCiSession['uname']) || empty($dataCiSession['uname'])) {
            return false;
        }

        $npk = (string) $dataCiSession['uname'];

        // Retrieve active employee directly from USER_HRIS (hrd_employee)
        $user = User::where('employee_id', $npk)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return false;
        }

        return $user;
    }

    /**
     * Decode CodeIgniter 3 session data using PHP native session_decode.
     *
     * @param CISession|string $data
     * @return array
     */
    public function parseCiSession($data): array
    {
        $sessionData = is_string($data) ? $data : ($data->data ?? '');

        if (empty($sessionData)) {
            return [];
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
        @session_decode($sessionData);
        $decoded = $_SESSION;

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        return $decoded;
    }
}
