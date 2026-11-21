/// <reference path="./jsx.ts" />

/**
 * Admin: add bookable periods with POST /api/periods, one at a time or in bulk.
 * Bulk: one start date (Y-m-d) per line, each period lasting `length` days (end = start + length - 1).
 * Each period is sent separately, so an overlapping date doesn't stop the others; every line gets its result.
 * Announces new periods with the "lh-bookings-changed" event (upcomingBookings reloads on it).
 */
type addPeriodsPlace = { id: number; name: string };

document.addEventListener('DOMContentLoaded', async () => {
    document.head.appendChild(<link rel="stylesheet" href="/less/addPeriods.css"></link>);
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="addPeriods"]');

    /** Y-m-d plus `days`, computed in UTC so daylight saving time can't shift the date. */
    const addDays = (date: string, days: number) => {
        const d = new Date(`${date}T00:00:00Z`);
        d.setUTCDate(d.getUTCDate() + days);
        return d.toISOString().slice(0, 10);
    };
    const isIsoDate = (date: string) => /^\d{4}-\d{2}-\d{2}$/.test(date) && !isNaN(Date.parse(`${date}T00:00:00Z`))
        && new Date(`${date}T00:00:00Z`).toISOString().slice(0, 10) === date;

    const placeSelect = <select></select> as HTMLSelectElement;
    const singleStart = <input type="date" required/> as HTMLInputElement;
    const singleEnd = <input type="date"/> as HTMLInputElement;
    const bulkLength = <input type="number" min="1" value="1" required/> as HTMLInputElement;
    const bulkDates = <textarea rows="8" placeholder={'2026-12-05\n2026-12-12\n2026-12-19'}></textarea> as HTMLTextAreaElement;
    const results = <ul class="results"></ul>;

    /** POST one period; returns null on success, otherwise the error to show. */
    const createPeriod = async (start: string, end: string): Promise<string | null> => {
        let response: Response;
        try {
            response = await fetch('/api/periods', {
                body: JSON.stringify({places_id: Number(placeSelect.value), start_date: start, end_date: end}),
                headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
                method: 'POST',
            });
        } catch {
            return 'Kunde inte nå API:t';
        }
        if (response.ok) {
            return null;
        }
        const body = await response.json().catch(() => null) as {error?: string, errors?: Record<string, string[]>} | null;
        if (body?.errors) {
            return Object.values(body.errors).flat().join(' ');
        }
        return body?.error ?? `HTTP ${response.status}`;
    };

    const showResult = (start: string, end: string, error: string | null) => {
        const period = start === end ? start : `${start} – ${end}`;
        results.appendChild(error === null
            ? <li class="ok">✅ {period}</li>
            : <li class="error">❌ {period}: {error}</li>);
    };

    /** Creates the periods in order, shows one result line each, and reloads the other sections once. */
    const createPeriods = async (periods: [string, string][], button: HTMLButtonElement) => {
        button.disabled = true;
        results.replaceChildren();
        let created = 0;
        for (const [start, end] of periods) {
            const error = await createPeriod(start, end);
            showResult(start, end, error);
            if (error === null) {
                created++;
            }
        }
        button.disabled = false;
        if (created) {
            document.dispatchEvent(new CustomEvent('lh-bookings-changed'));
        }
        return created;
    };

    const singleButton = <button type="submit">Lägg till</button> as HTMLButtonElement;
    const singleForm = <form onsubmit={async (event: Event) => {
        event.preventDefault();
        const start = singleStart.value;
        const end = singleEnd.value || start;
        if (await createPeriods([[start, end]], singleButton)) {
            singleStart.value = '';
            singleEnd.value = '';
        }
    }}>
        <h3>En period</h3>
        <label><span>Från:</span>{singleStart}</label>
        <label><span>Till:</span>{singleEnd}<small>tom = samma dag</small></label>
        {singleButton}
    </form>;

    const bulkButton = <button type="submit">Lägg till alla</button> as HTMLButtonElement;
    const bulkForm = <form onsubmit={async (event: Event) => {
        event.preventDefault();
        const length = Math.max(1, Math.floor(Number(bulkLength.value) || 1));
        const lines = bulkDates.value.split(/[\s,;]+/).map(line => line.trim()).filter(line => line !== '');
        const invalid = lines.filter(line => !isIsoDate(line));
        if (invalid.length) {
            results.replaceChildren(...invalid.map(line => <li class="error">❌ {line}: inte ett datum på formen ÅÅÅÅ-MM-DD</li>));
            return;
        }
        const periods = [...new Set(lines)].sort().map(start => [start, addDays(start, length - 1)] as [string, string]);
        const created = await createPeriods(periods, bulkButton);
        if (created === periods.length) {
            bulkDates.value = '';
        }
    }}>
        <h3>Flera perioder</h3>
        <label><span>Längd (dagar):</span>{bulkLength}</label>
        <label class="dates"><span>Startdatum, ett per rad:</span>{bulkDates}</label>
        {bulkButton}
    </form>;

    const scriptContent = <div id="addPeriods">
        <h2>Lägg till perioder</h2>
        <label><span>Lokal:</span>{placeSelect}</label>
        <div class="forms">
            {singleForm}
            {bulkForm}
        </div>
        {results}
    </div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(scriptContent, tsxScriptElement.nextElementSibling);
    } else {
        document.body.appendChild(scriptContent);
    }

    try {
        const places = await (await fetch('/api/places', {headers: {Accept: 'application/json'}})).json() as addPeriodsPlace[];
        for (const place of places) {
            placeSelect.appendChild(<option value={place.id}>{place.name}</option>);
        }
        if (!places.length) {
            results.replaceChildren(<li class="error">❌ Det finns inga lokaler att lägga perioder på.</li>);
        }
    } catch {
        results.replaceChildren(<li class="error">❌ Kunde inte hämta lokalerna.</li>);
    }
}, {once: true, passive: true});
