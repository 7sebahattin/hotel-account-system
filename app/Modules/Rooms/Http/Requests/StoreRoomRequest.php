<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Http\Requests;

use App\Modules\Rooms\Models\Room;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Room::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_no' => ['required', 'string', 'max:20', 'unique:rooms,room_no'],
            'room_name' => ['nullable', 'string', 'max:150'],
            'room_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'room_no' => 'oda numarası',
            'room_name' => 'oda adı',
            'room_type' => 'oda tipi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['room_no.unique' => 'Bu oda numarası zaten kayıtlı.'];
    }
}
