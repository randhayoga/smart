<?php

namespace App\Services;

use App\Models\AdmUser;
use App\Notifications\AppNotification;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     */
    public function sendToUser(
        AdmUser $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $user->notify(new AppNotification($title, $message, $type, $url, $extra));
    }

    /**
     * Send a notification to a collection or array of users.
     *
     * @param iterable<AdmUser> $users
     */
    public function sendToUsers(
        iterable $users,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Send a notification to all users holding a specific dynamic role.
     * Roles: 'admin' | 'ifs_manager' | 'manager' | 'user'
     */
    public function sendToRole(
        string $role,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $targetUsers = AdmUser::all()->filter(fn(AdmUser $u) => $u->role === $role);

        foreach ($targetUsers as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Send a notification to all users holding any of the specified dynamic roles.
     *
     * @param array<string> $roles
     */
    public function sendToMultipleRoles(
        array $roles,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $targetUsers = AdmUser::all()->filter(fn(AdmUser $u) => in_array($u->role, $roles));

        foreach ($targetUsers as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }
}
