<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreLearnerRequest extends FormRequest
{
    private const GRADE_LEVELS = ['Kindergarten'];

    private const EXTENSION_NAMES = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

    private const RELATIONSHIPS = ['MOTHER', 'FATHER', 'GRANDMOTHER', 'GRANDFATHER', 'SISTER', 'BROTHER', 'AUNT', 'UNCLE', 'OTHER'];

    private const MAX_GUARDIANS = 10;

    public function rules(): array
    {
        $year = SchoolYear::active();
        // Age window is checked against today in PH time, same as the form.
        $today = Carbon::now('Asia/Manila')->startOfDay();

        $rules = [
            'lrn' => ['nullable', 'digits:12', 'unique:learners,lrn'],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'extensionName' => ['nullable', Rule::in(self::EXTENSION_NAMES)],
            'birthdate' => [
                'required', 'date',
                'before_or_equal:'.$today->copy()->subYears(4)->toDateString(),
                'after:'.$today->copy()->subYears(7)->toDateString(),
            ],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'houseStreet' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'cityMunicipality' => ['required', 'string', 'max:100'],
            'provinceId' => ['required', 'integer', 'exists:provinces,id'],
            'zipCode' => ['required', 'regex:/^\d{4}$/'],
            'gradeLevel' => ['required', Rule::in(self::GRADE_LEVELS)],
            // The section must be one of this teacher's sections in the active school year.
            'section' => [
                'required', 'string', 'max:100',
                Rule::exists('sections', 'name')
                    ->where('school_year_id', $year?->id)
                    ->where('teacher_id', $this->user()->id)
                    ->where('grade_level', $this->input('gradeLevel')),
            ],
            'schoolYear' => ['required', 'regex:/^\d{4}-\d{4}$/', Rule::in([$year?->name])],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:3072'],
            'guardianCount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_GUARDIANS],
        ];

        // Guardian fields arrive flat as field_index, matching the form's input names.
        foreach ($this->guardianIndexes() as $i) {
            $rules += $this->guardianRules("_$i");
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'lrn.digits' => 'LRN must be exactly 12 digits.',
            'lrn.unique' => 'This LRN is already registered.',
            'firstName.required' => 'First name is required.',
            'lastName.required' => 'Last name is required.',
            'birthdate.required' => 'Birthdate is required.',
            'birthdate.before_or_equal' => 'Learner must be between 4 and 6 years old.',
            'birthdate.after' => 'Learner must be between 4 and 6 years old.',
            'gender.required' => 'Gender is required.',
            'houseStreet.required' => 'House No./Street is required.',
            'barangay.required' => 'Barangay is required.',
            'cityMunicipality.required' => 'Town/City/Municipality is required.',
            'provinceId.required' => 'Province is required.',
            'provinceId.exists' => 'Province is required.',
            'zipCode.required' => 'ZIP code must be 4 digits.',
            'zipCode.regex' => 'ZIP code must be 4 digits.',
            'section.required' => 'Section is required.',
            'section.exists' => 'Pick one of your sections.',
            'schoolYear.regex' => 'School year must be like 2025-2026.',
            'schoolYear.in' => 'School year must be the active school year.',
            'photo.max' => 'Photo must be 3MB or smaller.',
            'photo.mimes' => 'Photo must be a JPG, PNG, or WEBP image.',
            'photo.image' => 'Photo must be a JPG, PNG, or WEBP image.',
        ];

        foreach ($this->guardianIndexes() as $i) {
            $messages += $this->guardianMessages("_$i");
        }

        return $messages;
    }

    // One guardian's rules; $suffix is "_0", "_1"… matching the form's input names.
    protected function guardianRules(string $suffix): array
    {
        return [
            "guardianName$suffix" => ['required', 'string', 'max:100'],
            "contactNumber$suffix" => ['required', 'regex:/^\d{11}$/'],
            "relationshipToLearner$suffix" => ['required', Rule::in(self::RELATIONSHIPS)],
            "relationshipOther$suffix" => ["required_if:relationshipToLearner$suffix,OTHER", 'nullable', 'string', 'max:50'],
            "guardianEmail$suffix" => ['nullable', 'email', 'max:255'],
            "occupation$suffix" => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function guardianMessages(string $suffix): array
    {
        return [
            "guardianName$suffix.required" => 'Guardian name is required.',
            "contactNumber$suffix.required" => 'Contact number must be 11 digits.',
            "contactNumber$suffix.regex" => 'Enter a valid Philippine mobile number.',
            "relationshipToLearner$suffix.required" => 'Relationship is required.',
            "relationshipToLearner$suffix.in" => 'Relationship is required.',
            "relationshipOther$suffix.required_if" => 'Specify the relationship.',
            "guardianEmail$suffix.email" => 'Enter a valid email address.',
        ];
    }

    protected function guardianIndexes(): array
    {
        $count = min(max((int) $this->input('guardianCount'), 0), self::MAX_GUARDIANS);

        return $count > 0 ? range(0, $count - 1) : [];
    }
}
