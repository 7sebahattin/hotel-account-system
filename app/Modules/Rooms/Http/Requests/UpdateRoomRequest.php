<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Http\Requests;

use App\Modules\Rooms\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room instanceof Room && $this->user()->can('update', $room);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->getKey() : null;

        return [
            'room_no' => ['required', 'string', 'max:20', Rule::unique('rooms', 'room_no')->ignore($roomId)],
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
