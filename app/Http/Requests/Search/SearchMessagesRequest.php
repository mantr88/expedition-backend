<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchMessagesRequest extends FormRequest
{
    /**
     * Членство фільтрується всередині SearchMessages, тому будь-який
     * автентифікований користувач може викликати пошук.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * channel_id навмисно без exists-перевірки: чужий/неіснуючий id
     * дає порожній результат і не розкриває існування каналу.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'channel_id' => ['sometimes', 'integer', 'min:1'],
            'before' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
