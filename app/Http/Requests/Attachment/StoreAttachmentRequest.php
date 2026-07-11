<?php

namespace App\Http\Requests\Attachment;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');
        $message = $this->route('message');

        return $channel instanceof Channel
            && $message instanceof Message
            && $message->channel_id === $channel->id
            && $this->user()->can('post', $channel);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120', // 5 MB
                'mimes:jpeg,jpg,png,gif,webp,pdf,txt,doc,docx,xls,xlsx,zip',
            ],
        ];
    }
}
