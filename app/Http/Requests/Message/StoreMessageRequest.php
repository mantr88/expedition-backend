<?php

namespace App\Http\Requests\Message;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $this->user()->can('post', $channel);
    }

    /**
     * parent_id: лише живе top-level повідомлення того самого каналу —
     * вкладені треди не підтримуються (контракт B1/B4).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $channel = $this->route('channel');

        return [
            'body' => ['required', 'string', 'max:4000'],
            'client_message_id' => ['required', 'uuid'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('channel_id', $channel instanceof Channel ? $channel->id : null)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
