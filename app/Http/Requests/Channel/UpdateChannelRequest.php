<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $this->user()->can('update', $channel);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
