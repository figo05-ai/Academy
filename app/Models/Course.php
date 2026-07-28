<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Session> $sessions
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property string|null $image_path
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'price', 'image_path'
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')->withPivot('status')->withTimestamps();
    }
}
