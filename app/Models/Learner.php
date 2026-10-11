<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property \Illuminate\Support\Carbon $birth_date
 */
class Learner extends Model
{
    protected $fillable = [
        'lrn', 'first_name', 'middle_name', 'last_name', 'extension_name', 'birth_date', 'gender', 'avatar_url', 'is_active',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_active' => 'boolean'];
    }

    public function address(): HasOne
    {
        return $this->hasOne(LearnerAddress::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(LearnerSchoolYear::class);
    }

    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(LearnerSchoolYear::class)->latestOfMany();
    }

    // Row shape for the teacher's learner list; expects latestEnrollment.section loaded.
    public function toListArray(): array
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return [
            'learnerId' => $this->id,
            'lrn' => $this->lrn,
            'firstName' => $this->first_name,
            'middleName' => $this->middle_name,
            'lastName' => $this->last_name,
            'extensionName' => $this->extension_name,
            'gender' => $this->gender,
            'birthdate' => $this->birth_date->toDateString(),
            'gradeLevel' => $this->latestEnrollment?->section?->grade_level,
            'section' => $this->latestEnrollment?->section?->name,
            'photoUrl' => $this->avatar_url ? $disk->url($this->avatar_url) : null,
        ];
    }

    // Everything the profile page shows; expects address.province, guardians and latestEnrollment.section/schoolYear loaded.
    public function toProfileArray(): array
    {
        $address = $this->address;
        $enrollment = $this->latestEnrollment;

        return $this->toListArray() + [
            'schoolYear' => $enrollment?->schoolYear?->name,
            'address' => $address ? [
                'addressId' => $address->id,
                'learnerId' => $this->id,
                'houseStreet' => $address->house_street,
                'barangay' => $address->barangay,
                'cityMunicipality' => $address->city_municipality,
                'provinceId' => $address->province_id,
                'province' => $address->province?->province_name,
                'zipCode' => $address->zip_code,
            ] : null,
            'guardians' => $this->guardians->sortBy('id')->map(fn (Guardian $g) => [
                'guardianId' => $g->id,
                'learnerId' => $this->id,
                'guardianName' => $g->guardian_name,
                'contactNumber' => $g->contact_number,
                'relationshipToLearner' => $g->relationship_to_learner,
                'guardianEmail' => $g->guardian_email,
                'occupation' => $g->occupation,
            ])->values()->all(),
            // Current enrollment's attendance only, oldest first.
            'attendances' => $enrollment
                ? DB::table('attendance')->where('enrollment_id', $enrollment->id)->orderBy('attendance_date')
                    ->get(['attendance_date', 'status', 'recorded_at', 'excuse_reason'])
                    ->map(fn ($a) => ['date' => $a->attendance_date, 'status' => $a->status, 'recordedAt' => $a->recorded_at, 'reason' => $a->excuse_reason])->all()
                : [],
            'progress' => $enrollment ? $this->checklistProgress($enrollment) : [],
        ];
    }

    // Every active Developmental Checklist skill of the learner's grade, each with this learner's rating in that skill's own quarter.
    private function checklistProgress(LearnerSchoolYear $enrollment): array
    {
        $ratings = DB::table('learner_developmental_checklists')
            ->join('quarters', 'quarters.id', '=', 'learner_developmental_checklists.quarter_id')
            ->where('learner_developmental_checklists.enrollment_id', $enrollment->id)
            ->get(['learner_developmental_checklists.checklist_item_id', 'quarters.quarter_number', 'learner_developmental_checklists.rating'])
            ->keyBy(fn ($r) => $r->checklist_item_id.'-'.$r->quarter_number);

        return DB::table('developmental_checklist_items')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'developmental_checklist_items.domain_id')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->where(['developmental_checklist_items.grade_level' => $enrollment->section?->grade_level, 'developmental_checklist_items.is_active' => true])
            ->orderBy('developmental_checklist_items.quarter_number')->orderBy('developmental_domains.display_order')->orderBy('developmental_checklist_items.display_order')
            ->get(['developmental_checklist_items.id', 'developmental_checklist_items.item', 'developmental_checklist_items.quarter_number', 'developmental_domains.id as domain_id', 'developmental_domains.name as domain_name', 'activities.name as activity_name'])
            ->map(fn ($i) => [
                'itemId' => $i->id,
                'item' => $i->item,
                'quarter' => $i->quarter_number,
                'domainId' => $i->domain_id,
                'domainName' => $i->domain_name,
                'activityName' => $i->activity_name,
                'rating' => $ratings[$i->id.'-'.$i->quarter_number]->rating ?? null,
            ])->all();
    }
}
