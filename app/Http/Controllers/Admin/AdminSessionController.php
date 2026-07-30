<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Session\ReorderSessionsRequest;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Http\Requests\Session\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Course;
use App\Models\Session;
use Illuminate\Routing\Controller;

class AdminSessionController extends Controller
{
    public function store(StoreSessionRequest $request, Course $course)
    {
        $data = $request->validated();

        $data['order_number'] = ($course->sessions()->max('order_number') ?? 0) + 1;

        $session = $course->sessions()->create($data);

        return response()->json([
            'success' => 'تمت إضافة المحاضرة بنجاح.',
            'session' => new SessionResource($session),
        ]);
    }

    public function update(UpdateSessionRequest $request, Session $session)
    {
        $data = $request->validated();

        $session->update($data);

        return response()->json([
            'success' => 'تم تحديث المحاضرة بنجاح.',
            'session' => new SessionResource($session->fresh()),
        ]);
    }

    public function destroy(Session $session)
    {
        $session->delete();
        return response()->json(['success' => 'تم حذف المحاضرة بنجاح.']);
    }

    public function reorder(ReorderSessionsRequest $request)
    {
        $validated = $request->validated();

        foreach ($validated['order'] as $index => $sessionId) {
            Session::where('id', $sessionId)->update(['order_number' => $index + 1]);
        }

        return response()->json(['success' => 'تم إعادة ترتيب المحاضرات بنجاح.']);
    }
}
