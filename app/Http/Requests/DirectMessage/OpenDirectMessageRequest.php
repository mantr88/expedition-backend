<?php

namespace App\Http\Requests\DirectMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * DM із самим собою не підтримується (контракт B3).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id'),
            ],
        ];
    }
}
