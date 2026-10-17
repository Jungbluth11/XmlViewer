const tabs = Array.from(document.querySelectorAll('#xmlviewer [role="tab"]'))
const viewer = document.getElementById('xmlviewer')

// Nextcloud uses different layout containers for user and public pages.
// Place the viewer at the document root so both modes share the same layout.
if (viewer) {
	document.body.appendChild(viewer)
}

function activateTab(tab) {
	for (const candidate of tabs) {
		const active = candidate === tab
		candidate.setAttribute('aria-selected', String(active))
		candidate.tabIndex = active ? 0 : -1
		const panel = document.getElementById(candidate.getAttribute('aria-controls'))
		panel.hidden = !active
		panel.style.setProperty('display', active ? 'block' : 'none', 'important')
	}
}

if (tabs.length > 0) activateTab(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0])

for (const tab of tabs) {
	tab.addEventListener('click', () => activateTab(tab))
	tab.addEventListener('keydown', (event) => {
		if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
		event.preventDefault()
		const current = tabs.indexOf(tab)
		const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
			: (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length
		tabs[next].focus()
		activateTab(tabs[next])
	})
}

