<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class Teacher extends Authenticatable
{
    // Tells Laravel auth which column holds the hash.
    protected $authPasswordName = 'password_hash';

    protected $fillable = ['name', 'username', 'password_hash', 'email', 'is_active'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['password_hash' => 'hashed', 'is_active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    // Learners enrolled this active school year in a section this teacher advises.
    public function learners(): Builder
    {
        return Learner::query()->whereHas('enrollments', fn (Builder $q) => $q
            ->whereHas('schoolYear', fn (Builder $y) => $y->where('status', 'active'))
            ->whereHas('section', fn (Builder $s) => $s->where('teacher_id', $this->id)));
    }

    // Shape the frontend expects for the signed-in teacher.
    public function toMeArray(): array
    {
        return [
            'teacherId' => $this->id,
            'username' => $this->username,
            'teacherName' => $this->name,
            // True while a class handed to this teacher waits for them to accept it.
            'handoverPending' => DB::table('teacher_handovers')->where(['to_teacher_id' => $this->id, 'status' => 'pending'])->exists(),
        ];
    }
}
