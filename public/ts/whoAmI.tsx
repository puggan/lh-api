/// <reference path="./jsx.ts" />

/**
 * Shows whether the API recognises the WordPress login (GET /api/who-am-i).
 * The API reads the WordPress "logged in" cookie, so this is a quick check that the two still agree.
 */
document.addEventListener('DOMContentLoaded', async () => {
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="whoAmI"]');

    const result = <p>Kontrollerar…</p>;
    const scriptContent = <div id="whoAmI">
        <h2>Inloggning</h2>
        {result}
    </div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(scriptContent, tsxScriptElement.nextElementSibling);
    } else {
        document.body.appendChild(scriptContent);
    }

    try {
        const user = await (await fetch('/api/who-am-i', {headers: {Accept: 'application/json'}})).json() as {loggedIn: boolean, who?: string, admin?: boolean};
        result.innerText = !user.loggedIn
            ? '❌ API:t känner inte igen din inloggning. Logga in i WordPress igen för att se och hantera bokningarna.'
            : user.admin
                ? `✅ API:t känner igen dig som ${user.who}.`
                : `⚠️ API:t känner igen dig som ${user.who}, men du får inte hantera bokningar (kräver redaktör eller administratör).`;
    } catch {
        result.innerText = '❌ Kunde inte nå API:t.';
    }
}, {once: true, passive: true});
