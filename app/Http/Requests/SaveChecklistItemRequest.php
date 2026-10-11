<?php

namespace App\Http\Requests;

use App\Http\Controllers\CurriculumController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveChecklistItemRequest extends FormRequest
{
    // Editing keeps the item's domain and quarter, so they come from the saved row, not the request.
    protected function prepareForValidation(): void
    {
        if ($id = $this->route('item')) {
            $row = DB::table('developmental_checklist_items')->where(['id' => $id, 'is_active' => true])->first(['domain_id', 'quarter_number']);
            abort_unless($row, 404);
            $this->merge(['domainId' => $row->domain_id, 'quarter' => $row->quarter_number]);
        }
    }

    public function rules(): array
    {
        return [
            'domainId' => ['required', 'integer', Rule::exists('developmental_domains', 'id')->where('is_active', true)],
            'quarter' => ['required', 'integer', 'between:1,4'],
            'item' => ['required', 'string', 'max:100'],
            'activityId' => ['nullable', 'integer', Rule::exists('activities', 'id')->where('is_active', true)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The same skill twice in one domain and quarter would split its ratings across two rows.
            if (DB::table('developmental_checklist_items')
                ->where(['domain_id' => $this->integer('domainId'), 'quarter_number' => $this->integer('quarter'), 'grade_level' => CurriculumController::GRADE_LEVEL, 'is_active' => true])
                ->when($this->route('item'), fn ($q, $id) => $q->where('id', '!=', $id))
                ->whereRaw('lower(item) = lower(?)', [trim($this->input('item'))])->exists()) {
                $validator->errors()->add('item', 'This skill is already on the checklist for this quarter.');
            }

            // The game must be one of this domain's activities in this quarter.
            if ($this->filled('activityId') && ! DB::table('activities')
                ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
                ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
                ->where(['activities.id' => $this->integer('activityId'), 'module_domains.domain_id' => $this->integer('domainId'), 'lessons.quarter_number' => $this->integer('quarter')])
                ->exists()) {
                $validator->errors()->add('activityId', 'Pick an activity from this domain and quarter.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'domainId.exists' => 'Pick a domain from the list.',
            'item.required' => 'Skill is required.',
            'item.max' => 'Keep the skill to 100 characters or fewer.',
            'activityId.exists' => 'Pick an activity from the list.',
        ];
    }
}
