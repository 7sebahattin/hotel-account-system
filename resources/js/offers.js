// Teklif matrisi: tek kaynak state, sıralama değişince fiyatlar oda/dönemle birlikte taşınır.
document.querySelectorAll('[data-offer-matrix]').forEach((root) => {
    const state = JSON.parse(root.dataset.initial);
    state.periods = state.periods || [];
    state.rooms = state.rooms || [];
    state.prices = state.prices || [];
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

    function render() {
        root.querySelector('[data-period-list]').innerHTML = state.periods.map((period, index) => `
            <div class="rounded-xl border border-line bg-bg p-3" data-period="${index}">
                <div class="flex gap-2">
                    <input name="periods[${index}][label]" value="${esc(period.label)}" aria-label="Dönem ${index + 1} adı" placeholder="1 Eylül – 30 Eylül" required maxlength="160" class="min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 py-2">
                    <button type="button" data-move-period="-1" aria-label="Dönemi yukarı taşı" class="px-2">↑</button>
                    <button type="button" data-move-period="1" aria-label="Dönemi aşağı taşı" class="px-2">↓</button>
                    <button type="button" data-remove-period aria-label="Dönemi sil" class="px-2 text-red-600">×</button>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <label class="text-xs text-tx-muted">Başlangıç<input type="date" name="periods[${index}][start_date]" value="${esc(period.start_date)}" class="mt-1 w-full rounded-lg border border-line bg-surface px-2 py-2"></label>
                    <label class="text-xs text-tx-muted">Bitiş<input type="date" name="periods[${index}][end_date]" value="${esc(period.end_date)}" class="mt-1 w-full rounded-lg border border-line bg-surface px-2 py-2"></label>
                </div>
            </div>`).join('');
        root.querySelector('[data-room-list]').innerHTML = state.rooms.map((room, index) => `
            <div class="flex items-center gap-2 rounded-xl border border-line bg-bg p-3" data-room="${index}">
                <input name="room_types[${index}][name]" value="${esc(room.name)}" aria-label="Oda tipi ${index + 1}" placeholder="DOUBLE" required maxlength="100" class="min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 py-2">
                <button type="button" data-move-room="-1" aria-label="Oda tipini yukarı taşı" class="px-2">↑</button>
                <button type="button" data-move-room="1" aria-label="Oda tipini aşağı taşı" class="px-2">↓</button>
                <button type="button" data-remove-room aria-label="Oda tipini sil" class="px-2 text-red-600">×</button>
            </div>`).join('');
        root.querySelector('[data-matrix-head]').innerHTML = `<tr><th class="border-b border-line px-3 py-3 text-left">Oda tipi</th>${state.periods.map((period, i) => `<th class="min-w-36 border-b border-line px-3 py-3 text-left" data-period-title="${i}">${esc(period.label || 'Dönem ' + (i + 1))}</th>`).join('')}</tr>`;
        root.querySelector('[data-matrix-body]').innerHTML = state.rooms.map((room, ri) => `<tr class="border-b border-line">
            <th class="px-3 py-3 text-left font-semibold" data-room-title="${ri}">${esc(room.name || 'Oda ' + (ri + 1))}</th>
            ${state.periods.map((period, pi) => `<td class="px-3 py-3"><input type="number" min="0" step="0.01" inputmode="decimal" name="prices[${ri}][${pi}]" value="${esc(state.prices[ri]?.[pi])}" aria-label="${esc(room.name || 'Oda ' + (ri + 1))} · ${esc(period.label || 'Dönem ' + (pi + 1))} fiyatı" class="w-36 rounded-lg border border-line bg-bg px-3 py-2 text-right"></td>`).join('')}
        </tr>`).join('');
    }
    root.addEventListener('input', (event) => {
        const target = event.target;
        const period = target.closest('[data-period]');
        const room = target.closest('[data-room]');
        if (period) {
            const field = target.name.match(/\[(label|start_date|end_date)\]$/)?.[1];
            if (field) state.periods[Number(period.dataset.period)][field] = target.value;
            if (field === 'label') root.querySelector(`[data-period-title="${period.dataset.period}"]`).textContent = target.value || 'Dönem';
        } else if (room) {
            state.rooms[Number(room.dataset.room)].name = target.value;
            root.querySelector(`[data-room-title="${room.dataset.room}"]`).textContent = target.value || 'Oda';
        } else if (target.name?.startsWith('prices[')) {
            const [, ri, pi] = target.name.match(/^prices\[(\d+)\]\[(\d+)\]$/) || [];
            if (ri !== undefined) {
                state.prices[Number(ri)] ||= [];
                state.prices[Number(ri)][Number(pi)] = target.value;
            }
        }
    });
    root.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const period = button.closest('[data-period]');
        const room = button.closest('[data-room]');
        if (button.hasAttribute('data-add-period') && state.periods.length < 12) {
            state.periods.push({ label: '', start_date: '', end_date: '' });
            render();
        } else if (button.hasAttribute('data-add-room') && state.rooms.length < 20) {
            state.rooms.push({ name: '' });
            state.prices.push([]);
            render();
        } else if (period) {
            const index = Number(period.dataset.period);
            if (button.hasAttribute('data-remove-period') && state.periods.length > 1) {
                state.periods.splice(index, 1);
                state.prices.forEach((row) => row.splice(index, 1));
            } else if (button.hasAttribute('data-move-period')) {
                const next = index + Number(button.dataset.movePeriod);
                if (next < 0 || next >= state.periods.length) return;
                [state.periods[index], state.periods[next]] = [state.periods[next], state.periods[index]];
                state.prices.forEach((row) => { [row[index], row[next]] = [row[next], row[index]]; });
            } else return;
            render();
        } else if (room) {
            const index = Number(room.dataset.room);
            if (button.hasAttribute('data-remove-room') && state.rooms.length > 1) {
                state.rooms.splice(index, 1);
                state.prices.splice(index, 1);
            } else if (button.hasAttribute('data-move-room')) {
                const next = index + Number(button.dataset.moveRoom);
                if (next < 0 || next >= state.rooms.length) return;
                [state.rooms[index], state.rooms[next]] = [state.rooms[next], state.rooms[index]];
                [state.prices[index], state.prices[next]] = [state.prices[next], state.prices[index]];
            } else return;
            render();
        }
    });
    render();
});
