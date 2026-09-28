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
            <div class="rounded-lg border border-line bg-well/60 p-3" data-period="${index}">
                <div class="flex flex-wrap items-center gap-2">
                    <input name="periods[${index}][label]" value="${esc(period.label)}" aria-label="Dönem ${index + 1} adı" placeholder="1 Eylül – 30 Eylül" required maxlength="160" class="min-h-11 min-w-40 flex-1 rounded-lg border border-field bg-card px-3 text-sm text-tx focus:border-acc-tx">
                    <div class="flex items-center gap-1">
                        <button type="button" data-move-period="-1" aria-label="Dönemi yukarı taşı" title="Yukarı taşı" ${index === 0 ? 'disabled' : ''} class="min-h-11 min-w-11 rounded-lg border border-line bg-card text-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-acc-tx disabled:opacity-40">↑</button>
                        <button type="button" data-move-period="1" aria-label="Dönemi aşağı taşı" title="Aşağı taşı" ${index === state.periods.length - 1 ? 'disabled' : ''} class="min-h-11 min-w-11 rounded-lg border border-line bg-card text-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-acc-tx disabled:opacity-40">↓</button>
                        <button type="button" data-remove-period aria-label="Dönemi sil" title="Dönemi sil" ${state.periods.length === 1 ? 'disabled' : ''} class="ml-1 min-h-11 rounded-lg border border-danger/30 bg-card px-3 text-xs font-semibold text-danger hover:bg-danger-bg focus-visible:outline-2 focus-visible:outline-danger disabled:opacity-40">Sil</button>
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <label class="min-w-0 text-xs text-mut">Başlangıç<input type="date" name="periods[${index}][start_date]" value="${esc(period.start_date)}" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-field bg-card px-2 text-tx focus:border-acc-tx"></label>
                    <label class="min-w-0 text-xs text-mut">Bitiş<input type="date" name="periods[${index}][end_date]" value="${esc(period.end_date)}" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-field bg-card px-2 text-tx focus:border-acc-tx"></label>
                </div>
            </div>`).join('');
        root.querySelector('[data-room-list]').innerHTML = state.rooms.map((room, index) => `
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-well/60 p-3" data-room="${index}">
                <input name="room_types[${index}][name]" value="${esc(room.name)}" aria-label="Oda tipi ${index + 1}" placeholder="DOUBLE" required maxlength="100" class="min-h-11 min-w-40 flex-1 rounded-lg border border-field bg-card px-3 text-sm text-tx focus:border-acc-tx">
                <div class="flex items-center gap-1">
                    <button type="button" data-move-room="-1" aria-label="Oda tipini yukarı taşı" title="Yukarı taşı" ${index === 0 ? 'disabled' : ''} class="min-h-11 min-w-11 rounded-lg border border-line bg-card text-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-acc-tx disabled:opacity-40">↑</button>
                    <button type="button" data-move-room="1" aria-label="Oda tipini aşağı taşı" title="Aşağı taşı" ${index === state.rooms.length - 1 ? 'disabled' : ''} class="min-h-11 min-w-11 rounded-lg border border-line bg-card text-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-acc-tx disabled:opacity-40">↓</button>
                    <button type="button" data-remove-room aria-label="Oda tipini sil" title="Oda tipini sil" ${state.rooms.length === 1 ? 'disabled' : ''} class="ml-1 min-h-11 rounded-lg border border-danger/30 bg-card px-3 text-xs font-semibold text-danger hover:bg-danger-bg focus-visible:outline-2 focus-visible:outline-danger disabled:opacity-40">Sil</button>
                </div>
            </div>`).join('');
        root.querySelector('[data-matrix-head]').innerHTML = `<tr class="bg-well"><th scope="col" class="min-w-40 border-b border-line px-4 py-3 text-left font-semibold text-tx">Oda tipi</th>${state.periods.map((period, i) => `<th scope="col" class="min-w-40 border-b border-line px-4 py-3 text-right font-semibold text-tx" data-period-title="${i}">${esc(period.label || 'Dönem ' + (i + 1))}</th>`).join('')}</tr>`;
        root.querySelector('[data-matrix-body]').innerHTML = state.rooms.map((room, ri) => `<tr class="border-b border-line">
            <th scope="row" class="px-4 py-3 text-left font-semibold text-tx" data-room-title="${ri}">${esc(room.name || 'Oda ' + (ri + 1))}</th>
            ${state.periods.map((period, pi) => `<td class="px-3 py-3"><input type="number" min="0" step="0.01" inputmode="decimal" name="prices[${ri}][${pi}]" value="${esc(state.prices[ri]?.[pi])}" aria-label="${esc(room.name || 'Oda ' + (ri + 1))} · ${esc(period.label || 'Dönem ' + (pi + 1))} fiyatı" class="min-h-11 w-36 rounded-lg border border-field bg-card px-3 text-right tabular-nums text-tx focus:border-acc-tx"></td>`).join('')}
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
                if (!window.confirm('Bu dönemi ve ilgili fiyatları silmek istiyor musunuz?')) return;
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
                if (!window.confirm('Bu oda tipini ve ilgili fiyatları silmek istiyor musunuz?')) return;
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
