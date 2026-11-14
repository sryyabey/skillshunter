<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSkillRequest extends FormRequest
{
    public function validationData(): array
    {
        return ['slug' => $this->route('slug')] + parent::validationData();
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:160', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'category_slug' => ['required', 'string', 'max:255', 'exists:categories,slug'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:4000'],
            'instructions' => ['required_without:source_markdown', 'nullable', 'string', 'max:100000'],
            'source_markdown' => ['nullable', 'string', 'max:100000', 'not_regex:/\x00/'],
            'source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_keywords' => ['nullable', 'array', 'list', 'max:12'],
            'seo_keywords.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'editor_note' => ['nullable', 'string', 'max:4000'],
            'license' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:32'],
            'use_cases' => ['nullable', 'array', 'list', 'max:20'],
            'use_cases.*' => ['required', 'string', 'max:300'],
            'limitations' => ['nullable', 'array', 'list', 'max:20'],
            'limitations.*' => ['required', 'string', 'max:300'],
            'compatibility' => ['nullable', 'array', 'list', 'max:20'],
            'compatibility.*' => ['required', 'array:model,success'],
            'compatibility.*.model' => ['required', 'string', 'max:100'],
            'compatibility.*.success' => ['required', 'numeric', 'between:0,100'],
            'setup_tip' => ['nullable', 'string', 'max:4000'],
            'sample_input' => ['nullable', 'string', 'max:30000'],
            'sample_output' => ['nullable', 'string', 'max:30000'],
            'is_published' => ['missing'],
            'verified_at' => ['missing'],
        ];
    }
}
