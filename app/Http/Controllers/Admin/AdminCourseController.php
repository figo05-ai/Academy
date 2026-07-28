<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\SessionResource;
use App\Models\Course;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class AdminCourseController extends Controller
{
    public function store(StoreCourseRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }

        $course = Course::create($data);

        return response()->json([
            'success' => 'تم إنشاء الكورس بنجاح.',
            'course' => new CourseResource($course->load('sessions'))
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($course->image_path && Storage::disk('public')->exists($course->image_path)) {
                Storage::disk('public')->delete($course->image_path);
            }
            $data['image_path'] = $request->file('image')->store('courses', 'public');
        }

        $course->update($data);

        return response()->json([
            'success' => 'تم تحديث الكورس بنجاح.',
            'course' => new CourseResource($course->fresh()->load('sessions'))
        ]);
    }

    public function destroy(Course $course)
    {
        if ($course->image_path && Storage::disk('public')->exists($course->image_path)) {
            Storage::disk('public')->delete($course->image_path);
        }

        $course->delete();

        return response()->json(['success' => 'تم حذف الكورس بنجاح.']);
    }

    public function getSessions(Course $course)
    {
        return SessionResource::collection($course->sessions()->orderBy('order_number')->get());
    }
}
