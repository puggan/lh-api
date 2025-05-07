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
        <h1>
            Bokningsförfrågningar
        </h1>
    </div>;

    for (const request of requests) {
        scriptContent.appendChild(
            <table>
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
                        {request.phone}<br/>
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
                        <button>
                            Acceptera
                        </button>
                        <button>
                            Neka
                        </button>
                    </td>
                </tr>
            </table>
        )
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
