<?php

use App\Models\AppSettingModel;
use App\Models\GroupModel;
use App\Models\UserGroupModel;
use App\Models\UserModel;

if (! function_exists('current_user')) {
    function current_user(): ?array
    {
        $userId = session()->get('user_id');
        if (! $userId) {
            return null;
        }

        static $cached = null;
        if ($cached && (int) $cached['id'] === (int) $userId) {
            return $cached;
        }

        $model = new UserModel();
        $cached = $model->find($userId);

        return $cached ?: null;
    }
}

if (! function_exists('current_user_group_names')) {
    function current_user_group_names(): array
    {
        $user = current_user();
        if (! $user) {
            return [];
        }

        $userGroupModel = new UserGroupModel();
        $groupModel = new GroupModel();
        $groupIds = array_column($userGroupModel->where('user_id', $user['id'])->findAll(), 'group_id');
        if (empty($groupIds)) {
            return [];
        }

        $groups = $groupModel->whereIn('id', $groupIds)->findAll();

        return array_map(static fn(array $group): string => $group['name'], $groups);
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return in_array('admin', current_user_group_names(), true);
    }
}

if (! function_exists('app_settings')) {
    function app_settings(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $model = new AppSettingModel();
        $settings = $model->first();
        $cached = $settings ?: [
            'app_name'            => 'Confeiteira App',
            'title_color_enabled' => 0,
            'title_color'         => '#8b5cf6',
        ];

        return $cached;
    }
}
