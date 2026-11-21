/// <reference path="./jsx.ts" />

/**
 * Admin: the iCalendar feeds to subscribe to in Google Calendar, per place (GET /api/calender/feeds).
 * The public feed lists bookable days; the admin feed lists bookings with the bookers' details,
 * protected only by the secret in its URL.
 */
type calendarFeed = { places_id: number; place: string; public: string; admin: string | null };

document.addEventListener('DOMContentLoaded', async () => {
    document.head.appendChild(<link rel="stylesheet" href="/less/calendarFeeds.css"></link>);
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="calendarFeeds"]');

    const list = <div><p>Laddar…</p></div>;
    const scriptContent = <div id="calendarFeeds">
        <h2>Kalenderprenumerationer</h2>
        <p>
            Lägg till i Google Kalender: <em>Andra kalendrar → + → Från webbadress</em>, och klistra in adressen.
            Google hämtar ändringar själv, oftast inom ett dygn.
        </p>
        {list}
    </div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(scriptContent, tsxScriptElement.nextElementSibling);
    } else {
        document.body.appendChild(scriptContent);
    }

    const urlField = (url: string) => {
        const input = <input type="text" readonly value={url}/> as HTMLInputElement;
        input.addEventListener('focus', () => input.select());
        const copy = <button type="button">Kopiera</button> as HTMLButtonElement;
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(url);
                copy.innerText = 'Kopierad ✓';
            } catch {
                input.select();
            }
        });
        return <span class="url">{input}{copy}</span>;
    };

    const response = await fetch('/api/calender/feeds', {headers: {Accept: 'application/json'}}).catch(() => null);
    if (!response?.ok) {
        list.replaceChildren(<p>{response?.status === 401
            ? 'Logga in i WordPress för att se adresserna.'
            : 'Kunde inte hämta adresserna.'}</p>);
        return;
    }
    const feeds = await response.json() as calendarFeed[];
    list.replaceChildren(...feeds.map(feed => <div class="place">
        <h3>{feed.place}</h3>
        <label><span>Lediga dagar (publik):</span>{urlField(feed.public)}</label>
        {feed.admin
            ? <label>
                <span>Bokningar (admin):</span>{urlField(feed.admin)}
                <small>Hemlig: visar namn och telefonnummer. Dela inte adressen.</small>
            </label>
            : <p><em>Admin-kalendern är inte aktiverad (LH_CALENDAR_TOKEN saknas i wp-config.php).</em></p>}
    </div>));
}, {once: true, passive: true});
