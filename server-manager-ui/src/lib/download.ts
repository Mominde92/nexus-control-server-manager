import apiClient from '@/lib/api-client'
import type { ListParams } from '@/types/api'

/**
 * GET a file from the API as a blob and hand it to the browser's downloader.
 *
 * The object URL is revoked immediately after the synthetic click so we do not
 * leak the blob for the lifetime of the document.
 */
export async function downloadFile(
  path: string,
  params: ListParams | undefined,
  filename: string,
): Promise<void> {
  const response = await apiClient.get<Blob>(path, {
    params,
    responseType: 'blob',
  })

  const url = URL.createObjectURL(response.data)
  const anchor = document.createElement('a')

  anchor.href = url
  anchor.download = filename
  anchor.rel = 'noopener'
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

/** `hostings-2026-08-05.csv` */
export function timestampedFilename(base: string, extension = 'csv'): string {
  const stamp = new Date().toISOString().slice(0, 10)

  return `${base}-${stamp}.${extension}`
}

export default downloadFile
