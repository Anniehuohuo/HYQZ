function escapeHtml(s) {
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
}

function renderInline(text) {
	const src = String(text || '')
	const placeholders = []
	const replaced = src.replace(/`([^`\n]+)`/g, (_, code) => {
		const idx = placeholders.length
		placeholders.push(`<code>${escapeHtml(code)}</code>`)
		return `@@CODE_${idx}@@`
	})

	let out = escapeHtml(replaced)
	out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
	out = out.replace(/\*([^*\n]+)\*/g, '<em>$1</em>')
	out = out.replace(/@@CODE_(\d+)@@/g, (_, n) => placeholders[Number(n)] || '')
	return out
}

function isHrLine(line) {
	const t = String(line || '').trim()
	if (!t) return false
	if (/^-{3,}$/.test(t)) return true
	if (/^_{3,}$/.test(t)) return true
	if (/^\*{3,}$/.test(t)) return true
	return false
}

function markdownToHtml(text) {
	const src = String(text || '').replace(/\r\n/g, '\n')
	const lines = src.split('\n')
	let html = ''
	let inUl = false
	let inOl = false
	let inCode = false
	let codeLang = ''
	let codeLines = []

	const closeUl = () => {
		if (inUl) {
			html += '</ul>'
			inUl = false
		}
	}
	const closeOl = () => {
		if (inOl) {
			html += '</ol>'
			inOl = false
		}
	}
	const closeLists = () => {
		closeUl()
		closeOl()
	}
	const flushCode = () => {
		if (!inCode) return
		const body = escapeHtml(codeLines.join('\n'))
		const langAttr = codeLang ? ` class="language-${escapeHtml(codeLang)}"` : ''
		html += `<pre><code${langAttr}>${body}</code></pre>`
		inCode = false
		codeLang = ''
		codeLines = []
	}

	for (let raw of lines) {
		const line = String(raw || '').trimEnd()
		const trimmed = line.trim()

		const fence = trimmed.match(/^```(\S+)?\s*$/)
		if (fence) {
			closeLists()
			if (inCode) {
				flushCode()
			} else {
				inCode = true
				codeLang = String(fence[1] || '').trim()
				codeLines = []
			}
			continue
		}

		if (inCode) {
			codeLines.push(line)
			continue
		}

		if (isHrLine(trimmed)) {
			closeLists()
			html += '<hr/>'
			continue
		}

		const h = line.match(/^\s{0,3}(#{1,6})\s+(.+)$/)
		if (h) {
			closeLists()
			const level = Math.min(6, Math.max(1, h[1].length))
			html += `<h${level}>${renderInline(h[2].trim())}</h${level}>`
			continue
		}

		const quote = line.match(/^\s*>\s+(.+)$/)
		if (quote) {
			closeLists()
			html += `<blockquote>${renderInline(quote[1].trim())}</blockquote>`
			continue
		}

		const ol = line.match(/^\s*\d+[.)]\s+(.+)$/)
		if (ol) {
			if (!inOl) {
				closeUl()
				html += '<ol>'
				inOl = true
			}
			html += `<li>${renderInline(ol[1].trim())}</li>`
			continue
		}

		const ul = line.match(/^\s*[-*•]\s+(.+)$/)
		if (ul) {
			if (!inUl) {
				closeOl()
				html += '<ul>'
				inUl = true
			}
			html += `<li>${renderInline(ul[1].trim())}</li>`
			continue
		}

		if (trimmed === '') {
			closeLists()
			continue
		}

		closeLists()
		html += `<p>${renderInline(line).replace(/\s{2,}/g, ' ')}</p>`
	}

	flushCode()
	closeLists()
	return html
}

module.exports = {
	markdownToHtml
}

