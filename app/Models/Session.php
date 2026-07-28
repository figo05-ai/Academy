<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read \App\Models\Course $course
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property bool $user_completed
 * @property int $course_id
 * @property int $id
 * @property int $order_number
 */
class Session extends Model
{
    use HasFactory;

    protected $table = 'course_sessions'; // Added table name explicitly

    protected $fillable = [
        'course_id', 'category_name', 'title', 'description', 'drive_link', 'facebook_group_link', 'order_number'
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'session_user')->withPivot('is_completed')->withTimestamps();
    }
}
