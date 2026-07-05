<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkChannelReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $this->user()->can('view', $channel);
    }

    /**
     * Маркер має вказувати на повідомлення саме цього каналу.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $channel = $this->route('channel');

        return [
            'last_read_message_id' => [
                'required',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('channel_id', $channel instanceof Channel ? $channel->id : null),
            ],
        ];
    }
}
