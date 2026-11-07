<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rooms\Exceptions\RoomHasStayHistory;
use App\Modules\Rooms\Http\Requests\StoreRoomRequest;
use App\Modules\Rooms\Http\Requests\UpdateRoomRequest;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Services\RoomService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Oda tanımı CRUD — Yönetim → Odalar (kullanıcı talimatı: Room Registry §1).
 *
 * "Tanımlar Merkezi" gibi genel bir ayarlar alt sistemi AÇILMADI; bu ekran
 * doğrudan Yönetim grubunun altındadır (kapsam kasıtlı olarak dar tutuldu).
 */
class RoomController extends Controller
{
    public function __construct(private readonly RoomService $rooms) {}

    public function index(): View
    {
        $this->authorize('viewAny', Room::class);

        return view('rooms::index', [
            'rooms' => Room::query()->orderBy('room_no')->paginate(25),
            'activeCount' => $this->rooms->activeCount(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Room::class);

        return view('rooms::create');
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $this->rooms->create($request->validated());

        return redirect()->route('rooms.index')->with('success', 'Oda eklendi.');
    }

    public function edit(Room $room): View
    {
        $this->authorize('update', $room);

        return view('rooms::edit', ['room' => $room]);
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $this->rooms->update($room, $request->validated());

        return redirect()->route('rooms.index')->with('success', 'Oda güncellendi.');
    }

    public function toggleActive(Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $this->rooms->setActive($room, ! $room->is_active);

        return back()->with('success', $room->is_active ? 'Oda aktifleştirildi.' : 'Oda pasifleştirildi.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        try {
            $this->rooms->delete($room);
        } catch (RoomHasStayHistory $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rooms.index')->with('success', 'Oda çöp kutusuna taşındı. Çöp Kutusu\'ndan geri alınabilir.');
    }
}
