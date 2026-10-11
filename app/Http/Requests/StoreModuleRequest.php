<?php

namespace App\Http\Requests;

use App\Http\Controllers\CurriculumController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreModuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'moduleName' => ['required', 'string', 'max:50'],
            'domainIds' => ['nullable', 'array'],
            'domainIds.*' => ['integer', 'distinct', Rule::exists('developmental_domains', 'id')->where('is_active', true)],
            'newDomain' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $name = trim($this->input('moduleName'));
            $newDomain = trim((string) $this->input('newDomain'));

            if (empty($this->input('domainIds')) && $newDomain === '') {
                $validator->errors()->add('domainIds', 'Pick at least one domain or add a new one.');
            }

            $code = CurriculumController::moduleCode($name);
            if ($code === '') {
                $validator->errors()->add('moduleName', 'Use letters or numbers in the module name.');
            } elseif (DB::table('modules')->where('grade_level', CurriculumController::GRADE_LEVEL)
                ->where(fn ($q) => $q->where('code', $code)->orWhereRaw('lower(name) = lower(?)', [$name]))->exists()) {
                $validator->errors()->add('moduleName', 'A module with this name already exists.');
            }

            if ($newDomain !== '') {
                $domainCode = CurriculumController::domainCode($newDomain);
                if ($domainCode === '') {
                    $validator->errors()->add('newDomain', 'Use letters or numbers in the domain name.');
                } elseif (DB::table('developmental_domains')
                    ->where(fn ($q) => $q->where('code', $domainCode)->orWhereRaw('lower(name) = lower(?)', [$newDomain]))->exists()) {
                    $validator->errors()->add('newDomain', 'This domain already exists. Tick it in the list instead.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'moduleName.required' => 'Module name is required.',
            'domainIds.*.exists' => 'Pick domains from the list.',
        ];
    }
}
