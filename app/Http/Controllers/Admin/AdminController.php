<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use App\Models\Course;
use App\Http\Requests\ProjectRequests\storeProjectRequests;
use App\Http\Requests\SettingRequest\UpdateSettingRequest;
use App\Services\ProjectServices\ProjectService;
use App\Models\Project; 
use App\Services\SettingServices\SettingService;


class AdminController extends Controller
{
    public function index(SettingService $settingService)
    {
        $data = $settingService->AdminViewService();
        $data['courses'] = Course::with('sessions')->latest()->get();
        $data['categories'] = \App\Models\Session::whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->distinct()
            ->pluck('category_name');

        return view('Admin.admin', $data);
    }

    public function updateSettings(UpdateSettingRequest $request, SettingService $settingService)
    {
        $clean = $request->validated();
        $data = $settingService->SettingUpdateService($clean);

        return back()->with('success', 'Update Success');
    }

    public function storeProject(storeProjectRequests $request, ProjectService $projectService)
    {
        $clean = $request->validated();
        $projectService->storeService($clean);

        return back()->with('success', 'Store Success');
    }

    public function destroyProject(Project $project, ProjectService $projectService)
    {
        $projectService->DeleteServices($project->id);

        return back()->with('success', 'Delete Success');
    }
}
