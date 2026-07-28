<?php

namespace App\Services\ProjectServices;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    public function storeService(array $data)
    {
        $imagePath = null;

        if (isset($data['image'])) {
            $imagePath = $data['image']->store('projects', 'public');
        }

        Project::create([
            'image_path' => $imagePath,
            'facebook_link' => $data['facebook_link'],
            'drive_link' => $data['drive_link'],
        ]);
    }

    public function DeleteServices(int $id)
    {
        $project = Project::findOrFail($id);

        if ($project->image_path && Storage::disk('public')->exists($project->image_path)) {
            Storage::disk('public')->delete($project->image_path);
        }

        $project->delete();
    }
}
