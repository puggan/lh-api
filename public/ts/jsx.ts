// Source: https://raw.githubusercontent.com/puggan/phpTypeSupport/master/src/jsx.ts & https://www.meziantou.net/write-your-own-dom-element-factory-for-typescript.htm
namespace JSX {
    export type Element = HTMLElement;
    export interface AttributeCollection {
        [name: string]: string | number | boolean | string[] | EventListenerOrEventListenerObject;
    }
}

namespace JsxFactory {
    const Fragment = "<></>";

    export function createElement(tagName: string, attributes: JSX.AttributeCollection | null, ...children: any[]): Element | DocumentFragment {
        if (tagName === Fragment) {
            return document.createDocumentFragment();
        }

        const element = document.createElement(tagName);
        if (attributes) {
            for (const key of Object.keys(attributes)) {
                const attributeValue = attributes[key];

                if (typeof attributeValue === 'boolean') {
                    if (attributeValue) {
                        element.setAttribute(key, "");
                    }
                    continue;
                }

                if (typeof attributeValue == 'string') {
                    if (key === "className" || key === "class") { // JSX does not allow class as a valid name
                        element.setAttribute("class", attributeValue);
                        continue;
                    }
                    element.setAttribute(key, attributeValue);
                    continue;
                }

                if (typeof attributeValue == 'number') {
                    element.setAttribute(key, '' + attributeValue);
                    continue;
                }

                if (typeof attributeValue == 'object') {
                    if (Array.isArray(attributeValue)) {
                        if (key === "className" || key === "class") { // JSX does not allow class as a valid name
                            element.setAttribute("class", attributeValue.join(' '));
                            continue;
                        }
                        element.setAttribute(key, attributeValue.join(' '));
                        continue;
                    } else if (key === "className" || key === "class") {
                        for (const classKey of Object.keys(attributeValue)) {
                            if (typeof attributeValue[classKey]) {
                                element.classList.toggle(classKey, attributeValue[classKey]);
                            }
                        }
                    }
                }

                if (key.startsWith("on") && typeof attributes[key] === "function") {
                    element.addEventListener(key.substring(2), attributeValue);
                }
            }
        }

        for (const child of children) {
            appendChild(element, child);
        }

        return element;
    }

    export function appendChild(parent: Node, child: any) {
        if (typeof child === "undefined" || child === null) {
            return;
        }

        if (Array.isArray(child)) {
            for (const value of child) {
                appendChild(parent, value);
            }
        } else if (typeof child === "string") {
            parent.appendChild(document.createTextNode(child));
        } else if (child instanceof Node) {
            parent.appendChild(child);
        } else if (typeof child === "boolean") {
            // <>{condition && <a>Display when condition is true</a>}</>
            // if condition is false, the child is a boolean, but we don't want to display anything
        } else {
            parent.appendChild(document.createTextNode(String(child)));
        }
    }
}
