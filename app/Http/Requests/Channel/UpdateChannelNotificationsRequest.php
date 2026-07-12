<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $this->user()->can('view', $channel);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', 'string', 'in:all,mentions,mute'],
        ];
    }
}
