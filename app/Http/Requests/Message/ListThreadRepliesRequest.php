<?php

namespace App\Http\Requests\Message;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Авторизація: перевіряємо, що користувач — член каналу. Лише top-level (parent_id = null).
 */
class ListThreadRepliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message instanceof Message
            && $message->parent_id === null
            && $this->user()->can('view', $message->channel);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'after' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
