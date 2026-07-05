<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteChannelMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $this->user()->can('invite', $channel);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
