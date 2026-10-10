<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authentication is outside this exercise.
    }

    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->attributes->get('tenant');

        return [
            'child_id' => [
                'required',
                'integer',
                Rule::exists('children', 'id')
                    ->where('tenant_id', $tenant->id),
            ],
        ];
    }
}