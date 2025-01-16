/// <reference path="./jsx.ts" />

document.addEventListener('DOMContentLoaded', () => {
    const tsxScriptElement = document.querySelector<HTMLScriptElement>('script[data-tsx="test"]');
    const scriptContent = (
        <div>
            TEST
        </div>
    );
    if (tsxScriptElement) {
        tsxScriptElement.parentElement.insertBefore(
            scriptContent,
            tsxScriptElement.nextElementSibling
        );
    } else {
        document.body.appendChild(scriptContent);
    }
}, {once: true, passive: true});
