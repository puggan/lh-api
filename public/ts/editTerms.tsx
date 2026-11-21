/// <reference path="./jsx.ts" />

/**
 * Admin: the booking terms. Shows the current version (GET /api/terms/last) in a text area,
 * and publishes the edited text as a new version (POST /api/terms). Terms are never edited in place:
 * each booking points at the version the booker accepted, and new bookings must accept the newest.
 */
type editTermsVersion = { id: number; content: string; created_at: string };

document.addEventListener('DOMContentLoaded', async () => {
    document.head.appendChild(<link rel="stylesheet" href="/less/editTerms.css"></link>);
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="editTerms"]');

    const versionInfo = <p class="version">Laddar…</p>;
    const content = <textarea rows="15" disabled></textarea> as HTMLTextAreaElement;
    const publishButton = <button type="submit" disabled>Publicera ny version</button> as HTMLButtonElement;
    const result = <p class="result"></p>;
    let current: editTermsVersion | null = null;

    const showVersion = (terms: editTermsVersion | null) => {
        terms = terms?.id ? terms : null; // an empty terms table answers {}
        current = terms;
        versionInfo.innerText = terms
            ? `Nuvarande version: ${terms.id}, publicerad ${new Date(terms.created_at).toLocaleString('sv-SE', {dateStyle: 'short', timeStyle: 'short'})}.`
            : 'Inga villkor publicerade än.';
        content.value = terms?.content ?? '';
        content.disabled = false;
        updateButton();
    };
    const updateButton = () => {
        publishButton.disabled = content.value.trim() === '' || content.value === (current?.content ?? '');
    };
    content.addEventListener('input', updateButton);

    const form = <form onsubmit={async (event: Event) => {
        event.preventDefault();
        if (!confirm('Publicera den nya versionen? Nya bokningar måste godkänna den, och den som har bokningsformuläret öppet måste ladda om sidan.')) {
            return;
        }
        publishButton.disabled = true;
        result.className = 'result';
        result.innerText = '';
        let response: Response | null;
        try {
            response = await fetch('/api/terms', {
                body: JSON.stringify({content: content.value}),
                headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
                method: 'POST',
            });
        } catch {
            response = null;
        }
        if (!response?.ok) {
            const body = await response?.json().catch(() => null) as {error?: string, errors?: Record<string, string[]>} | null;
            result.className = 'result error';
            result.innerText = '❌ ' + (body?.errors ? Object.values(body.errors).flat().join(' ') : body?.error ?? 'Någonting gick fel, försök igen.');
            updateButton();
            return;
        }
        const published = await response.json() as editTermsVersion;
        showVersion(published);
        result.innerText = `✅ Version ${published.id} är publicerad.`;
    }}>
        {versionInfo}
        {content}
        <div>{publishButton}</div>
    </form>;

    const scriptContent = <div id="editTerms">
        <h2>Bokningsvillkor</h2>
        {form}
        {result}
    </div>;

    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(scriptContent, tsxScriptElement.nextElementSibling);
    } else {
        document.body.appendChild(scriptContent);
    }

    try {
        const response = await fetch('/api/terms/last', {headers: {Accept: 'application/json'}});
        showVersion(await response.json() as editTermsVersion | null);
    } catch {
        versionInfo.innerText = '❌ Kunde inte hämta villkoren.';
    }
}, {once: true, passive: true});
