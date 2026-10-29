<?php

namespace App\Http\Requests;

use App\Http\Controllers\ClassroomRecordController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveChecklistRatingRequest extends FormRequest
{
    public const RATINGS = ['achieved', 'developing', 'needs_support', 'not_yet_observed'];

    public function rules(): array
    {
        return [
            // A learner currently enrolled in this section.
            'enrollmentId' => ['required', 'integer', Rule::exists('learner_school_years', 'id')
                ->where('section_id', $this->route('section'))->where('status', 'active')],
            'quarter' => ['required', 'integer', 'between:1,4'],
            // An active item of the quarter being rated.
            'itemId' => ['required', 'integer', Rule::exists('developmental_checklist_items', 'id')
                ->where('is_active', true)->where('quarter_number', $this->integer('quarter'))],
            // null clears the rating back to "Not rated".
            'rating' => ['present', 'nullable', Rule::in(self::RATINGS)],
            // Left out to keep the saved remarks; null or blank clears them.
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    // A skill linked to a game: before the learner plays it, only "/" (not_yet_observed, Not yet introduced) fits;
    // once played, the skill has been introduced and is rated HA, A or NS from the result.
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('rating') === null) {
                return;
            }
            $activityId = DB::table('developmental_checklist_items')->where('id', $this->integer('itemId'))->value('activity_id');
            if (! $activityId) {
                return;
            }
            $played = ClassroomRecordController::latestResults(collect([$this->integer('enrollmentId')]), collect([$activityId]))->isNotEmpty();
            $notIntroduced = $this->input('rating') === 'not_yet_observed';
            if (! $played && ! $notIntroduced) {
                $validator->errors()->add('rating', 'This learner has not finished the activity yet, so this skill cannot be rated.');
            } elseif ($played && $notIntroduced) {
                $validator->errors()->add('rating', 'This learner already played the activity, so this skill has been introduced.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'enrollmentId.exists' => 'This learner is not in the section.',
            'itemId.exists' => 'This checklist item is not part of this quarter.',
            'rating.in' => 'Pick a rating.',
            'remarks.max' => 'Keep remarks to 500 characters or fewer.',
        ];
    }
}
