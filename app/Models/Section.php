<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Section extends Model
{
    public $timestamps = false;

    protected $fillable = ['school_year_id', 'teacher_id', 'name', 'grade_level', 'choice', 'is_active'];

    public const NAME_LOCKED_MESSAGE = 'This section has learner or classroom records, so its name can no longer be changed.';

    public const GRADE_LEVEL = 'Kindergarten';

    // The only sections a school year can have, and the session whose timetable each starts with.
    public const CHOICES = ['Joy' => 'morning', 'Faith' => 'afternoon'];

    public const SET_UP_MESSAGE = "This school year's sections are already set up and can't be changed.";

    // Default Kindergarten timetables per session: module code => [start, end, ISO weekdays].
    private const TIMETABLES = [
        'morning' => [
            'reading' => ['08:00', '09:00', [1, 3, 5]],
            'language' => ['09:15', '10:00', [1, 3, 5]],
            'writing' => ['10:00', '11:15', [1, 3, 5]],
            'mapeh' => ['08:00', '09:30', [2, 4]],
            'math' => ['09:45', '11:15', [2, 4]],
        ],
        'afternoon' => [
            'reading' => ['13:00', '14:00', [1, 3, 5]],
            'language' => ['14:15', '15:00', [1, 3, 5]],
            'writing' => ['15:00', '16:15', [1, 3, 5]],
            'mapeh' => ['13:00', '14:30', [2, 4]],
            'math' => ['14:45', '16:15', [2, 4]],
        ],
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    // "joy" or " Joy " -> "Joy"; null for anything that isn't one of the fixed choices.
    public static function choiceFromName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }
        $name = ucfirst(strtolower(trim($name)));

        return array_key_exists($name, self::CHOICES) ? $name : null;
    }

    // Creates a school year's picked sections, each with all Kindergarten modules and its
    // session's default timetable. A year's sections are set once: if it already has any,
    // nothing is created. The year row lock makes a racing second activation see the first one's rows.
    public static function setUp(SchoolYear $year, Teacher $teacher, array $names): void
    {
        DB::transaction(function () use ($year, $teacher, $names) {
            SchoolYear::whereKey($year->id)->lockForUpdate()->first();
            if (self::where('school_year_id', $year->id)->exists()) {
                throw ValidationException::withMessages(['sections' => self::SET_UP_MESSAGE]);
            }

            $modules = DB::table('modules')->where(['grade_level' => self::GRADE_LEVEL, 'is_active' => true])->get();
            foreach ($names as $name) {
                $timetable = self::TIMETABLES[self::CHOICES[$name]];
                $section = self::create([
                    'school_year_id' => $year->id,
                    'teacher_id' => $teacher->id,
                    'name' => $name,
                    'grade_level' => self::GRADE_LEVEL,
                    'choice' => $name,
                ]);

                foreach ($modules as $module) {
                    $sectionModuleId = DB::table('section_modules')->insertGetId([
                        'section_id' => $section->id,
                        'module_id' => $module->id,
                        'teacher_id' => $teacher->id,
                    ]);

                    if (! isset($timetable[$module->code])) {
                        continue;
                    }
                    [$start, $end, $days] = $timetable[$module->code];
                    $scheduleId = DB::table('class_schedules')->insertGetId([
                        'section_module_id' => $sectionModuleId,
                        'start_time' => $start,
                        'end_time' => $end,
                        'effective_from' => $year->start_date,
                        'effective_until' => $year->end_date,
                    ]);
                    DB::table('schedule_days')->insert(array_map(
                        fn (int $day) => ['schedule_id' => $scheduleId, 'day_of_week' => $day],
                        $days,
                    ));
                }
            }
        });
    }

    // "morning" or "afternoon" from the stored Joy/Faith identity, never from the display name; null for an old section without one.
    public function classSession(): ?string
    {
        return $this->choice ? self::CHOICES[$this->choice] : null;
    }

    /**
     * The ids among $ids with any enrollment (of any status) or any classroom session; their names are fixed.
     *
     * @return array<int, int>
     */
    public static function withRecords(array $ids): array
    {
        return DB::table('learner_school_years')->whereIn('section_id', $ids)->distinct()->pluck('section_id')
            ->merge(DB::table('classroom_sessions')
                ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
                ->whereIn('section_modules.section_id', $ids)->distinct()->pluck('section_modules.section_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // Shape the frontend's Section type expects; $hasRecords comes from withRecords().
    public function toApiArray(bool $hasRecords): array
    {
        return [
            'sectionId' => $this->id,
            'gradeLevel' => $this->grade_level,
            'sectionName' => $this->name,
            'session' => $this->classSession(),
            'canRename' => ! $hasRecords,
            'renameReason' => $hasRecords ? self::NAME_LOCKED_MESSAGE : null,
        ];
    }
}
