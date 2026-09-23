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
     * Extract Portal CI session ID from incoming HTTP request.
     * Checks raw cookie first ('ci_session'), then query/input parameter ('ciSession' / 'ci_session').
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function extractPortalSessionId(\Illuminate\Http\Request $request): ?string
    {
        $cookie = $request->cookie('ci_session') ?: $request->cookies->get('ci_session');

        if (!empty($cookie)) {
            return (string) $cookie;
        }

        $param = $request->query('ciSession')
            ?: $request->query('ci_session')
            ?: $request->input('ciSession')
            ?: $request->input('ci_session');

        return !empty($param) ? (string) $param : null;
    }

    /**
     * Decode CodeIgniter 3 session data using PHP native session_decode with regex fallback.
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

        $decoded = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (@session_decode($sessionData)) {
                $decoded = $_SESSION;
            }
            @session_write_close();
        }

        // Robust fallback: If session_decode failed or produced no uname,
        // extract 'uname' directly from the CodeIgniter session serialized payload using regex.
        if (empty($decoded['uname']) && preg_match('/(?:^|;)uname\|s:[0-9]+:"([^"]+)";/', $sessionData, $match)) {
            $decoded['uname'] = $match[1];
        }

        if (empty($decoded['isLogin']) && preg_match('/(?:^|;)isLogin\|b:([01]);/', $sessionData, $match)) {
            $decoded['isLogin'] = (bool) $match[1];
        }

        if (empty($decoded['org_code']) && preg_match('/(?:^|;)org_code\|s:[0-9]+:"([^"]+)";/', $sessionData, $match)) {
            $decoded['org_code'] = $match[1];
        }

        return $decoded;
    }
}
