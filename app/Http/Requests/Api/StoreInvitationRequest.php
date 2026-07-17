<?php

namespace App\Http\Requests\Api;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->has('channel_id') && $this->input('channel_id') !== null) {
            $channel = Channel::find($this->input('channel_id'));

            return $channel ? Gate::allows('invite', $channel) : true;
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'channel_id' => ['nullable', 'exists:channels,id'],
        ];
    }
}
