const blockedTags = new Set(['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base'])
const uriAttributes = new Set(['href', 'src', 'xlink:href', 'formaction'])

export function sanitizeHtml(input: unknown): string {
    const html = String(input ?? '')
    if (typeof DOMParser == 'undefined') {
        return escapeHtml(html)
    }

    const document = new DOMParser().parseFromString(html, 'text/html')
    const elements = Array.from(document.body.querySelectorAll('*'))
    for (const element of elements) {
        const tag = element.tagName.toLowerCase()
        if (blockedTags.has(tag)) {
            element.remove()
            continue
        }

        for (const attribute of Array.from(element.attributes)) {
            const name = attribute.name.toLowerCase()
            const value = attribute.value.trim().toLowerCase()
            if (name.startsWith('on') || (uriAttributes.has(name) && value.startsWith('javascript:'))) {
                element.removeAttribute(attribute.name)
            }
        }
    }

    return document.body.innerHTML
}

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (char) => {
        const map: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }
        return map[char] ?? char
    })
}
