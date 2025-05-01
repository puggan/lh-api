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

    const statusTranslation = {Pending: "Förfrågad", Available: "Ledig", Booked: "Bokad"};

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

    const saveInputValue = (event: Event) => {
        const input = event.currentTarget as HTMLInputElement;
        localStorage.setItem(
            'calenderBooking' + input.name,
            input.value
        )
    };
    let currentDate = new Date();
    let bookingForm: HTMLFormElement|null = null;
    const renderMonth = () => {
        while (tableBody.lastElementChild) {
            tableBody.removeChild(tableBody.lastElementChild)
        }
        // start på måndagen i den veckan där första dagen på månaden är
        const firstDayOfMonth = new Date(currentDate);
        firstDayOfMonth.setDate(1);
        monthTitle.innerText = currentDate.toLocaleDateString("SV", {month: "long", year: "numeric"});
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
                    eventBox = <div data-status={event.status}>{statusTranslation[event.status]}</div>;
                    if (event.status == "Available") {
                        eventBox.addEventListener("click", () => {
                            if (bookingForm) {
                                scriptContent.removeChild(bookingForm);
                            }

                            const bookCalenderEvent = async (event: Event) => {
                                event.preventDefault();
                                const inputData = new FormData(bookingForm);
                                console.log(inputData);
                                await (await fetch(
                                    "/api/calender/book",
                                    {
                                        body: inputData,
                                        method: 'POST',
                                    }
                                )).json();
                            };

                            const inputField = (name: string) => <input
                                type="text"
                                name={name}
                                oninput={saveInputValue}
                                value={localStorage.getItem('calenderBooking' + name)}
                            />;

                            bookingForm = <form id="bookingForm" onsubmit={bookCalenderEvent}>
                                <h3>Boka {event.start_date} till {event.end_date}</h3>
                                <input type="hidden" name="periodId" value={event.id}/>
                                <div>
                                    <fieldset>
                                        <label>
                                            <span>För- och efternamn:</span>
                                            {inputField('name')}
                                        </label>

                                        <label>
                                            <span>Telefonnummer:</span>
                                            {inputField('phone')}
                                        </label>
                                        <label>
                                            <span>Email:</span>
                                            {inputField('email')}
                                        </label>
                                        <label>
                                            <span>Adress:</span>
                                            {inputField('street')}
                                        </label>
                                        <label>
                                            <span>Lägenhetsnummer:</span>
                                            {inputField('apartment')}
                                        </label>
                                        <label>
                                            <span>Postnummer:</span>
                                            {inputField('zipcode')}
                                        </label>
                                        <label>
                                            <span>Stad:</span>
                                            {inputField('city')}
                                        </label>

                                        <label>
                                            <span>Vad ska lokalen användas till:</span>
                                            <textarea
                                                name="purpose"
                                                oninput={saveInputValue}
                                            >
                                                {localStorage.getItem('calenderBooking' + 'purpose')}
                                            </textarea>
                                        </label>
                                    </fieldset>
                                    <fieldset>
                                        <div>bla bla bla</div>
                                        <input type="checkbox" name="Approve" value="1" />
                                        <button>Skicka bokningsförfrågan</button>
                                    </fieldset>
                                </div>
                            </form> as HTMLFormElement;
                            scriptContent.appendChild(bookingForm);
                            bookingForm.scrollTo();
                        });
                    }
                }
                weektr.appendChild(
                    <td data-month={cellDate.getMonth() + 1}>
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
