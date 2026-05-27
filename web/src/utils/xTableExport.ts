interface ExportPayloadInput {
    title: string
    columns: TableColumn[]
    filter?: XTable['filter']
}

interface ExportDownloadUrlInput {
    apiBaseUrl: string
    routePath: string
    taskId: string | number
    token: string
}

export function buildExportPayload(input: ExportPayloadInput) {
    return {
        title: input.title,
        column: input.columns,
        ...(input.filter ?? {}),
    }
}

export function buildExportDownloadUrl(input: ExportDownloadUrlInput) {
    const base = input.apiBaseUrl.replace(/\/$/, '')
    const route = input.routePath.replace(/\/$/, '')
    const params = new URLSearchParams({
        subId: '0',
        download: 'true',
        id: String(input.taskId),
        xtoken: input.token,
        server: '1',
    })
    return `${base}${route}/export?${params.toString()}`
}
