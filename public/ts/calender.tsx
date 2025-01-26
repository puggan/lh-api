/// <reference path="./jsx.ts" />

type place = {
    city: string;
    description: string;
    id: number;
    name: string;
    street: string;
    zipcode: number;
};
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

    const statusTranslation ={Pending: "Förfrågad", Available: "Ledig", Booked: "Bokad"};

    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="calender"]');
    const placeSelector = (
        <select id="places">
            <option value="">
                Visa alla platser
            </option>
        </select>
    );
    const tableBody = (
        <tbody>
            <tr>
                <td>
                    Loading
                </td>
            </tr>
        </tbody>
    );

    const buttonToday = (
        <button>
            Idag
        </button>
    );
    const buttonPrevious = (
        <button>
            ⮘
        </button>
    );
    const buttonNext = (
        <button>
            ⮚
        </button>
    );
buttonNext.addEventListener('click', () => {
    currentDate.setDate(37);
    renderMonth()
});
buttonPrevious.addEventListener('click', () => {
    currentDate.setDate(-0);
    renderMonth()
});
buttonToday.addEventListener('click', () => {
    currentDate = new Date();
    renderMonth()
});
const monthTitle = (
    <span>
        Loading
    </span>
    );
    const scriptContent = (
        <div>
            <div id="calenderHeader">
            <span>
                {buttonPrevious}
                {buttonToday}
                {buttonNext}
            </span>
                {monthTitle}
            {placeSelector}
            </div>
            <table id="calender">
                <thead>
                    <tr>
                        <th>
                            Måndag
                        </th>
                        <th>
                            Tisdag
                        </th>
                        <th>
                            Onsdag
                        </th>
                        <th>
                            Torsdag
                        </th>
                        <th>
                            Fredag
                        </th>
                        <th>
                            Lördag
                        </th>
                        <th>
                            Söndag
                        </th>
                    </tr>
                </thead>
                {tableBody}
            </table>
        </div>
    );
    let currentDate = new Date();
    const renderMonth = () => {
        while (tableBody.lastElementChild) {
            tableBody.removeChild(tableBody.lastElementChild)
        }
        // start på måndagen i den veckan där första dagen på månaden är
        const firstDayOfMonth = new Date(currentDate);
        firstDayOfMonth.setDate(1);
        monthTitle.innerText = currentDate.toLocaleDateString("SV", {month:"long", year:"numeric"});
        tableBody.setAttribute("data-month", (currentDate.getMonth() + 1) + '');
        const startDate = new Date(firstDayOfMonth);
        switch (firstDayOfMonth.getDay()) {
            case 0: // Sunday
                startDate.setDate(-5)
                break;
            case 1: // Monday
                // startDate.setDate(1)
                break;
            case 2: // Tuesday
                startDate.setDate(0)
                break;
            case 3: // Wednesday
                startDate.setDate(-1)
                break;
            case 4: // Thursday
                startDate.setDate(-2)
                break;
            case 5: // Friday
                startDate.setDate(-3)
                break;
            case 6: // Saturday
                startDate.setDate(-4)
                break;

        }

        let cellDate = new Date(startDate);
        for (let weekNr = 0; weekNr < 5; weekNr++) {
            const weektr = <tr></tr>
            for (let dayNr = 0; dayNr < 7; dayNr++) {
                let eventBox = null;
                const cellDateIso = isoDate(cellDate);
                for (const event of periods) {
                    if (event.status == "Passed") {
                        continue;
                    }
                    if (event.start_date > cellDateIso) {
                        continue;
                    }
                    if (event.end_date < cellDateIso) {
                        continue;
                    }
                    eventBox = <div data-status={event.status}>{statusTranslation[event.status]}</div>
                }
                weektr.appendChild(
                    <td data-month={cellDate.getMonth()+1}>
                        <span>{cellDate.getDate()}</span>
                        {eventBox}
                    </td>
                );
                cellDate.setHours(37)
            }
            tableBody.appendChild(weektr);
        }
    };
    const styleElement = <link rel="stylesheet" href="/less/calender.css"></link>;
    document.head.appendChild(styleElement);

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(
            scriptContent,
            tsxScriptElement.nextElementSibling
        );
    } else {
        document.body.appendChild(scriptContent);
    }
    const places = await (await fetch("/api/places")).json() as place[];
    for (const place of places) {
        placeSelector.appendChild(
            <option value={place.id}>
                {place.name}
            </option>
        )
    }
    // renderMonth();
    const periods = await (await fetch("/api/calender")).json() as calender[];
    renderMonth();
}, {once: true, passive: true});
