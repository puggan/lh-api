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

    const requests = await (await fetch("/api/request/pending")).json() as requests[];

    console.table(requests);

    const scriptContent = <div id="pendingRequest">
        <h2>
            Bokningsförfrågningar
        </h2>
    </div>;

    /**
     * POST /api/request/approve or /api/request/decline. The request is no longer pending afterwards,
     * so its table is removed. 409 (approve): the period already has a booked booking.
     */
    const answer = async (action: 'approve' | 'decline', request: requests, table: HTMLElement) => {
        const question = action === 'approve'
            ? `Acceptera bokningen för ${request.name}, ${request.period.start_date}?`
            : `Neka bokningen för ${request.name}, ${request.period.start_date}?`;
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
            alert(response?.status === 409
                ? 'Perioden är redan bokad av någon annan. Neka den här förfrågan i stället.'
                : 'Någonting gick fel, försök igen.');
            buttons.forEach(b => b.disabled = false);
            return;
        }
        table.remove();
        // e.g. upcomingBookings reloads on this
        document.dispatchEvent(new CustomEvent('lh-bookings-changed'));
    };

    for (const request of requests) {
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
                        <button onclick={() => answer('approve', request, table)}>
                            Acceptera
                        </button>
                        <button onclick={() => answer('decline', request, table)}>
                            Neka
                        </button>
                    </td>
                </tr>
            </table>;
        scriptContent.appendChild(table);
    }

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(
            scriptContent,
            tsxScriptElement.nextElementSibling
        );
    } else {
        document.body.appendChild(scriptContent);
    }
}, {once: true, passive: true});
