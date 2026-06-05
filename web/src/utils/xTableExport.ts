interface ExportPayloadInput {
    title: string
    columns: TableColumn[]
    filter?: XTable['filter']
}

export function buildExportPayload(input: ExportPayloadInput) {
    return {
        title: input.title,
        column: input.columns,
        ...(input.filter ?? {}),
    }
}

export function buildExportDownloadPayload(taskId: string | number, options: { subId?: string | number; zip?: boolean } = {}) {
    return {
        id: taskId,
        ...(options.zip ? { downloadZip: 1 } : { subId: options.subId ?? 0, download: true }),
    }
}

export function downloadBlobResponse(response: any, defaultFilename = 'download') {
    const blob = response?.data
    if (!(blob instanceof Blob)) {
        return
    }

    const filename = resolveDownloadFilename(response?.headers?.['content-disposition'], defaultFilename)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

export function resolveDownloadFilename(contentDisposition = '', defaultFilename = 'download') {
    const header = String(contentDisposition)
    const encoded = header.match(/filename\*=UTF-8''([^;]+)/i)
    if (encoded) {
        try {
            return decodeURIComponent(encoded[1])
        } catch {
            return defaultFilename
        }
    }

    const plain = header.match(/filename=\"?([^\";]+)\"?/i)
    return plain ? plain[1] : defaultFilename
}
