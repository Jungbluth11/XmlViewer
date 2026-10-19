import { DefaultType, FileAction, registerFileAction } from '@nextcloud/files'

function loadParserScript() {
	const viewer = document.getElementById('xmlviewer')
	const scriptUrl = viewer?.dataset.parserScriptUrl
	if (!scriptUrl) {
		return
	}

	const script = document.createElement('script')
	script.src = scriptUrl
	script.async = true
	document.head.append(script)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', loadParserScript, { once: true })
} else {
	loadParserScript()
}

function isXmlMimeType(file) {
	const mime = String(file.mime || '').toLowerCase().split(';')[0].trim()
	const aliases = window.OC?.MimeTypeList?.aliases || {}
	const resolved = new Set([mime])
	let current = mime
	while (aliases[current] && !resolved.has(aliases[current])) {
		current = aliases[current].toLowerCase()
		resolved.add(current)
	}
	return [...resolved].some((value) => value === 'application/xml' || value === 'text/xml' || value.endsWith('+xml'))
}

const nativeViewerFallbackFileId = new URLSearchParams(window.location.search).get('xmlviewerFallback')
const isPublicFolderFallback = new URLSearchParams(window.location.hash.slice(1)).get('xmlviewerFallback') === 'folder'

function getBackUrl() {
	const url = new URL(window.location.href)
	if (/(?:^|\/)s\/[^/]+/.test(url.pathname)) {
		// Return to the shared folder, not to a previously selected native file.
		url.searchParams.delete('openfile')
		url.searchParams.delete('xmlviewerFallback')
		url.searchParams.delete('xmlviewerFallbackType')
	} else if (url.pathname.includes('/apps/files/')) {
		const filesMarker = '/apps/files/'
		const markerPosition = url.pathname.lastIndexOf(filesMarker)
		url.pathname = url.pathname.slice(0, markerPosition + filesMarker.length)
		url.searchParams.delete('fileid')
		url.searchParams.delete('openfile')
	}
	return url.pathname + url.search + url.hash
}

// A direct public file share has no file id in its URL and no public file list
// row to attach an action to. Let the server resolve the shared root node.
const directShareMatch = window.location.pathname.match(/(?:^|\/)s\/([^/]+)$/)
const hasPublicFileRows = document.querySelector('#fileList tr[data-id], #files-public-list tr[data-id], .files-list tr[data-id]')
if (directShareMatch && !nativeViewerFallbackFileId && !isPublicFolderFallback && !hasPublicFileRows) {
	const backUrl = getBackUrl()
	const rootViewerUrl = OC.generateUrl(`/apps/xmlviewer/public/${encodeURIComponent(directShareMatch[1])}`)
	window.location.assign(`${rootViewerUrl}?${new URLSearchParams({ backUrl }).toString()}`)
}

// Opening a nested file through openfile on public shares may only reload the
// folder listing. Reuse the native row click once the listing exists.
const publicShareMatch = window.location.pathname.match(/(?:^|\/)s\/([^/]+)/)
const isFolderFileFallback = new URLSearchParams(window.location.search).get('xmlviewerFallbackType') === 'folder'
if (publicShareMatch && isFolderFileFallback && nativeViewerFallbackFileId && /^\d+$/.test(nativeViewerFallbackFileId)) {
	let observer
	let opened = false
	const openFallbackRow = () => {
		if (opened) {
			return true
		}
		const row = [...document.querySelectorAll('tr[data-id]')]
			.find((candidate) => String(candidate.dataset.id) === nativeViewerFallbackFileId)
		const link = row?.querySelector('a[href]')
		if (!link) {
			return false
		}
		opened = true
		observer?.disconnect()
		window.setTimeout(() => link.click(), 50)
		return true
	}
	if (!openFallbackRow()) {
		observer = new MutationObserver(() => openFallbackRow())
		observer.observe(document.documentElement, { childList: true, subtree: true })
		window.setTimeout(() => observer.disconnect(), 15000)
	}
}

const xmlAction = new FileAction({
	id: 'xmlviewer-open-table',
	displayName: () => 'Als Tabelle öffnen',
	iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M3 4h18v16H3zm2 2v3h5V6zm7 0v3h7V6zM5 11v3h5v-3zm7 0v3h7v-3zm-7 5v2h5v-2zm7 0v2h7v-2z"/></svg>',
	default: DefaultType.DEFAULT,
	enabled(files) {
		return files.length === 1
			&& files[0].type === 'file'
			&& String(files[0].fileid) !== nativeViewerFallbackFileId
			&& isXmlMimeType(files[0])
	},
	async exec(file) {
		const currentFilesUrl = getBackUrl()
		const shareMatch = window.location.pathname.match(/(?:^|\/)s\/([^/]+)/)
		const viewerUrl = shareMatch
			? OC.generateUrl(`/apps/xmlviewer/public/${encodeURIComponent(shareMatch[1])}/${file.fileid}`)
			: OC.generateUrl(`/apps/xmlviewer/view/${file.fileid}`)
		const params = new URLSearchParams({ backUrl: currentFilesUrl })
		window.location.assign(`${viewerUrl}?${params.toString()}`)
		return true
 	},
})

registerFileAction(xmlAction)

// Public link pages can use a separate file list from Files. Intercept only
// clicks on XML file rows there so other files keep the native public viewer.
if (/\/(?:index\.php\/)?s\/[^/]+/.test(window.location.pathname)) {
	document.addEventListener('click', (event) => {
		const link = event.target.closest('a[href]')
		const row = link?.closest('tr[data-id]')
		if (!row || row.dataset.type === 'dir' || row.dataset.type === 'folder') {
			return
		}
		if (String(row.dataset.id) === nativeViewerFallbackFileId) {
			return
		}

		const mime = row.dataset.mime || row.getAttribute('data-mime') || ''
		if (!isXmlMimeType({ mime })) {
			return
		}

		const shareMatch = window.location.pathname.match(/(?:^|\/)s\/([^/]+)/)
		const fileId = row.dataset.id
		if (!shareMatch || !/^\d+$/.test(fileId || '')) {
			return
		}

		event.preventDefault()
		event.stopImmediatePropagation()
		const currentFilesUrl = getBackUrl()
		const viewerUrl = OC.generateUrl(`/apps/xmlviewer/public/${encodeURIComponent(shareMatch[1])}/${fileId}`)
		const params = new URLSearchParams({ backUrl: currentFilesUrl })
		window.location.assign(`${viewerUrl}?${params.toString()}`)
	}, true)
}
