<?php

namespace App\Services\SettingServices;

use App\Models\Project;
use App\Models\Setting;

class SettingService
{
    public function AdminViewService()
    {
        $projects = Project::latest()->get();
        $setting = Setting::first() ?? new Setting;

        return compact('projects', 'setting');
    }

    public function SettingUpdateService(array $data)
    {
        return Setting::updateOrCreate(
            ['id' => 1],
            $data
        );
    }
}
