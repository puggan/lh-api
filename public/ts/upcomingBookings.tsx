/// <reference path="./jsx.ts" />

/**
 * Upcoming (and ongoing) periods with their bookings, from GET /api/calender.
 * The bookings are only included when the API recognises the WordPress login.
 * Reloads when pendingRequests answers a request (the "lh-bookings-changed" event).
 */
type upcomingBooking = {
    id: number;
    status: 'pending' | 'booked' | 'unbooked' | 'rejected' | 'deleted';
    name: string;
    phone: number;
    email: string;
    apartment: number;
    deposit: number | null;
};

type upcomingPeriod = {
    id: number;
    place: string;
    start_date: string;
    end_date: string;
    status: 'Passed' | 'Available' | 'Pending' | 'Booked';
    bookings: upcomingBooking[] | null;
};

document.addEventListener('DOMContentLoaded', async () => {
    const isoDate = (date: Date) => `${date.toLocaleString("default", {year: "numeric"})}-${date.toLocaleString("default", {month: "2-digit"})}-${date.toLocaleString("default", {day: "2-digit"})}`;
    const periodStatus = {Passed: 'Passerad', Available: 'Ledig', Pending: 'Förfrågad', Booked: 'Bokad'};
    const bookingStatus = {pending: 'Förfrågad', booked: 'Bokad', unbooked: 'Avbokad', rejected: 'Nekad', deleted: 'Borttagen'};

    document.head.appendChild(<link rel="stylesheet" href="/less/upcomingBookings.css"></link>);

    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="upcomingBookings"]');
    const list = <div><p>Laddar…</p></div>;
    const scriptContent = <div id="upcomingBookings">
        <h2>Kommande dagar/perioder</h2>
        {list}
    </div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(scriptContent, tsxScriptElement.nextElementSibling);
    } else {
        document.body.appendChild(scriptContent);
    }

    const renderBookings = (bookings: upcomingBooking[] | null) => {
        if (bookings === null) {
            return <em>Logga in för att se bokningarna</em>;
        }
        if (!bookings.length) {
            return '–';
        }
        return <ul>
            {bookings.map(booking => <li data-status={booking.status}>
                <strong>{bookingStatus[booking.status] ?? booking.status}:</strong>
                {' '}{booking.name}, lgh {booking.apartment},
                {' '}<span class="phone-prefix">+46 </span>{booking.phone},
                {' '}{booking.email}
                {booking.deposit ? `, deposition ${booking.deposit} kr` : ''}
            </li>)}
        </ul>;
    };

    const reload = async () => {
        let periods: upcomingPeriod[];
        try {
            periods = await (await fetch('/api/calender', {headers: {Accept: 'application/json'}})).json() as upcomingPeriod[];
        } catch {
            list.replaceChildren(<p>Kunde inte hämta dagarna/perioderna.</p>);
            return;
        }

        const today = isoDate(new Date());
        const upcoming = periods
            .filter(period => period.end_date >= today)
            .sort((a, b) => a.start_date.localeCompare(b.start_date));

        if (!upcoming.length) {
            list.replaceChildren(<p>Inga kommande dagar/perioder.</p>);
            return;
        }

        list.replaceChildren(<table>
            <tr>
                <th>Datum</th>
                <th>Lokal</th>
                <th>Status</th>
                <th>Bokningar</th>
            </tr>
            {upcoming.map(period => <tr data-status={period.status}>
                <td>{period.start_date === period.end_date ? period.start_date : `${period.start_date} – ${period.end_date}`}</td>
                <td>{period.place}</td>
                <td>{periodStatus[period.status] ?? period.status}</td>
                <td>{renderBookings(period.bookings)}</td>
            </tr>)}
        </table>);
    };

    document.addEventListener('lh-bookings-changed', reload);
    await reload();
}, {once: true, passive: true});
