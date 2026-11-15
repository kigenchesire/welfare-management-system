<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Member;
use App\Models\User;

/** Internal (dashboard) notifications. */
class NotificationService
{
    public static function toUser(?User $user, string $type, string $title, string $message, ?string $url = null): void
    {
        if (! $user) {
            return;
        }
        AppNotification::create(compact('type', 'title', 'message', 'url') + ['user_id' => $user->id]);
    }

    public static function toMember(Member $member, string $type, string $title, string $message, ?string $url = null): void
    {
        self::toUser($member->user, $type, $title, $message, $url);
    }

    /** Notify every active user holding any of the given roles. */
    public static function toRoles(array $roles, string $type, string $title, string $message, ?string $url = null): void
    {
        $now = now();
        $rows = User::role($roles)->where('is_active', true)->pluck('id')->unique()
            ->map(fn ($id) => [
                'user_id' => $id, 'type' => $type, 'title' => $title, 'message' => $message,
                'url' => $url, 'created_at' => $now, 'updated_at' => $now,
            ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            AppNotification::insert($chunk);
        }
    }

    /** Notify all active members (used for new welfare cases and announcements). */
    public static function toAllMembers(string $type, string $title, string $message, ?string $url = null): void
    {
        $now = now();
        Member::active()->whereNotNull('user_id')->select('user_id')->chunk(500, function ($members) use ($type, $title, $message, $url, $now) {
            AppNotification::insert($members->map(fn ($m) => [
                'user_id' => $m->user_id, 'type' => $type, 'title' => $title, 'message' => $message,
                'url' => $url, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }
}
