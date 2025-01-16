/// <reference path="./jsx.ts" />

type place = {
    city: string;
    description: string;
    id: number;
    name: string;
    street: string;
    zipcode: number;
};

document.addEventListener('DOMContentLoaded', async () => {
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="calender"]');
    const placeSelector =(<select>
        <option value="">
            Visa alla platser
        </option>
    </select>)
    const tableBody =(
        <tbody>
            <tr>
                <td>
                    Loading
                </td>
            </tr>
        </tbody>
    );
    const scriptContent = (
        <div>
            {placeSelector}
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
    const renderMonth = ()=>{
        while(tableBody.lastElementChild){
            tableBody.removeChild(tableBody.lastElementChild)
        }
        // start på måndagen i den veckan där första dagen på månaden är
        const firstDayOfMonth = new Date(currentDate);
        firstDayOfMonth.setDate(1);
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
            case 3: // Wendsday
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
        for(let weekNr = 0; weekNr < 5; weekNr++) {
            const weektr= <tr></tr>
            for(let dayNr = 0; dayNr < 7; dayNr++) {
                weektr.appendChild(<td>{cellDate.getDate()}</td>);
                cellDate.setHours(37)
            }
            tableBody.appendChild(weektr);
        }
    };
    const styleElement = <style></style>;
    styleElement.innerText = `
        #calender {width: 100%}
        #calender th {border: solid red 1px;}
        #calender td {border: solid red 1px;}
    `;
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
    console.log(places);
    for(const place of places){
        placeSelector.appendChild(
            <option value={place.id}>
                {place.name}
            </option>
        )
    }
    renderMonth();
}, {once: true, passive: true});
