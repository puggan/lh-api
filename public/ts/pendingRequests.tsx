/// <reference path="./jsx.ts" />

type requests = {
    id: number;
    terms_id: number;
    periods_id: number;
    status: string;
    description: string;
    name: string;
    phone: number;
    email: string;
    street: string;
    apartment: number;
    zipcode: number;
    city: string;
    created_at: string;
    update_at: string;
    /** another booking for the same period is already booked: this request can only be declined */
    period_booked: boolean;
    period: {
        id: number;
        places_id: number;
        start_date: string;
        end_date: string;
        created_at: string;
    }
}

type calender = {
    id: number;
    places_id: number;
    place: string;
    start_date: string;
    end_date: string;
    status: "Passed" | "Available" | "Pending" | "Booked";
};

document.addEventListener('DOMContentLoaded', async () => {
    const isoDate = (date: Date) => `${date.toLocaleString("default", {year: "numeric"})}-${date.toLocaleString("default", {month: "2-digit"})}-${date.toLocaleString("default", {day: "2-digit"})}`;

    const statusTranslation = {Pending: "Förfrågad", Available: "Ledig", Booked: "Bokad"};

    const styleElement = <link rel="stylesheet" href="/less/pendingRequests.css"></link>;
    document.head.appendChild(styleElement);

    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="pendingRequests"]');

    const scriptContent = <div id="pendingRequest">
        <h2>
            Bokningsförfrågningar
        </h2>
    </div>;

    const insertContent = () => {
        if (tsxScriptElement) {
            tsxScriptElement.parentElement.insertBefore(
                scriptContent,
                tsxScriptElement.nextElementSibling
            );
        } else {
            document.body.appendChild(scriptContent);
        }
    };

    // Admin only: 401 when not logged in to WordPress, 403 without editor/administrator rights.
    const pendingResponse = await fetch("/api/request/pending", {headers: {Accept: 'application/json'}});
    if (!pendingResponse.ok) {
        scriptContent.appendChild(<p>
            {pendingResponse.status === 401
                ? 'Logga in i WordPress för att se bokningsförfrågningarna.'
                : pendingResponse.status === 403
                    ? 'Du får inte hantera bokningar (kräver redaktör eller administratör).'
                    : 'Kunde inte hämta bokningsförfrågningarna.'}
        </p>);
        insertContent();
        return;
    }
    const requests = await pendingResponse.json() as requests[];

    // Periods that are booked: their remaining requests can only be declined.
    const bookedPeriods = new Set<number>(requests.filter(r => r.period_booked).map(r => r.periods_id));
    const approveButtons: {periodId: number, button: HTMLButtonElement}[] = [];
    const disableApproveForBookedPeriods = () => {
        for (const {periodId, button} of approveButtons) {
            if (bookedPeriods.has(periodId)) {
                button.disabled = true;
                button.title = 'Perioden är redan bokad, den här förfrågan kan bara nekas.';
            }
        }
    };

    /**
     * POST /api/request/approve, /decline or /spam. The request is no longer pending afterwards,
     * so its table is removed. 409 (approve): the period already has a booked booking.
     * Spam declines without texting the booker.
     */
    const answer = async (action: 'approve' | 'decline' | 'spam', request: requests, table: HTMLElement) => {
        const question = {
            approve: `Acceptera bokningen för ${request.name}, ${request.period.start_date}?`,
            decline: `Neka bokningen för ${request.name}, ${request.period.start_date}?`,
            spam: `Markera förfrågan från ${request.name}, ${request.period.start_date} som spam? Den nekas utan att något SMS skickas.`,
        }[action];
        if (!confirm(question)) {
            return;
        }
        const buttons = table.querySelectorAll('button');
        buttons.forEach(b => b.disabled = true);
        let response: Response | null;
        try {
            response = await fetch(
                `/api/request/${action}`,
                {
                    body: JSON.stringify({bookings_id: request.id}),
                    headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
                    method: 'POST',
                }
            );
        } catch {
            response = null;
        }
        if (!response?.ok) {
            if (response?.status === 409) {
                bookedPeriods.add(request.periods_id);
            }
            alert(response?.status === 409
                ? 'Perioden är redan bokad av någon annan. Neka den här förfrågan i stället.'
                : 'Någonting gick fel, försök igen.');
            buttons.forEach(b => b.disabled = false);
            disableApproveForBookedPeriods();
            return;
        }
        table.remove();
        if (action === 'approve') {
            bookedPeriods.add(request.periods_id);
            disableApproveForBookedPeriods();
        }
        // e.g. upcomingBookings reloads on this
        document.dispatchEvent(new CustomEvent('lh-bookings-changed'));
    };

    for (const request of requests) {
        const approveButton = <button onclick={() => answer('approve', request, table)}>
            Acceptera
        </button> as HTMLButtonElement;
        approveButtons.push({periodId: request.periods_id, button: approveButton});
        const table: HTMLElement = <table>
                <tr>
                    <th>
                        Datum:
                    </th>
                    <td>
                        {request.period.start_date}
                        -
                        {request.period.end_date}
                    </td>
                </tr>
                <tr>
                    <th>
                        Beskrivning:
                    </th>
                    <td>
                        {request.description}
                    </td>
                </tr>
                <tr>
                    <th>
                        Vem:
                    </th>
                    <td>
                        {request.name}<br/>
                        {request.street}<br/>
                        {request.apartment}<br/>
                        {request.zipcode}<br/>
                        {request.city}<br/>
                    </td>
                </tr>
                <tr>
                    <th>
                        Kontaktuppgifter:
                    </th>
                    <td>
                        <span class="phone-prefix">+46 </span>{request.phone}<br/>
                        {request.email}
                    </td>
                </tr>
                <tr>
                    <th>
                        Inskickad:
                    </th>
                    <td>
                        {request.created_at.substr(0, 16).replace('T', ' ')}
                    </td>
                </tr>
                <tr>
                    <th>
                    </th>
                    <td>
                        {approveButton}
                        <button onclick={() => answer('decline', request, table)}>
                            Neka
                        </button>
                        <button onclick={() => answer('spam', request, table)} title="Nekar utan att skicka SMS">
                            Spam
                        </button>
                    </td>
                </tr>
            </table>;
        scriptContent.appendChild(table);
    }
    disableApproveForBookedPeriods();

    insertContent();
}, {once: true, passive: true});
