export const normalizeAdminUrl = (url?: string, adminEntrance = '/admin'): string | undefined => {
    if (!url || !url.startsWith('/admin/')) return url

    const entrance = adminEntrance.replace(/\/$/, '') || '/admin'
    if (entrance === '/admin') return url

    return url.replace(/^\/admin(?=\/)/, entrance)
}
