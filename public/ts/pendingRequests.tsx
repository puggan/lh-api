/// <reference path="./jsx.ts" />

type requests = {
    id: number;
    terms_id: number;
    periods_id: number;
    status: string;
    description: string;
    name: string;
    phone: number;;
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

    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="pendingRequests"]');

    const requests = await (await fetch("/api/request/pending")).json() as requests[];

    console.table(requests);

    const scriptContent = <div></div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(
            scriptContent,
            tsxScriptElement.nextElementSibling
        );
    } else {
        document.body.appendChild(scriptContent);
    }
}, {once: true, passive: true});
