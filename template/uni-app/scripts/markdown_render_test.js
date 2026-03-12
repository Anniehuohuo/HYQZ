const assert = require('assert')
const fs = require('fs')
const path = require('path')
const md = require('../utils/markdown.js')

function nowIsoCompact() {
	const d = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
}

function ensureDir(p) {
	fs.mkdirSync(p, { recursive: true })
}

function writeReport(outDir, cases) {
	ensureDir(outDir)
	const lines = []
	lines.push('# Markdown 渲染一致性报告')
	lines.push('')
	lines.push(`- 用例数：${cases.length}`)
	lines.push('')
	for (const c of cases) {
		lines.push(`## ${c.name}`)
		lines.push('')
		lines.push('### Input')
		lines.push('```md')
		lines.push(String(c.input || ''))
		lines.push('```')
		lines.push('')
		lines.push('### Output')
		lines.push('```html')
		lines.push(String(c.output || ''))
		lines.push('```')
		lines.push('')
	}
	fs.writeFileSync(path.join(outDir, 'markdown_render_diff_report.md'), lines.join('\n'), 'utf8')
}

function run() {
	const outDir = path.join(process.cwd(), 'scripts_out', `markdown_render_${nowIsoCompact()}`)
	const cases = []

	const pushCase = (name, input, checks) => {
		const output = md.markdownToHtml(input)
		cases.push({ name, input, output })
		for (const fn of checks) fn(output)
	}

	pushCase(
		'Headings h1-h6',
		['# H1', '## H2', '### H3', '#### H4', '##### H5', '###### H6'].join('\n'),
		[
			(o) => assert.ok(o.includes('<h1>H1</h1>')),
			(o) => assert.ok(o.includes('<h2>H2</h2>')),
			(o) => assert.ok(o.includes('<h3>H3</h3>')),
			(o) => assert.ok(o.includes('<h4>H4</h4>')),
			(o) => assert.ok(o.includes('<h5>H5</h5>')),
			(o) => assert.ok(o.includes('<h6>H6</h6>')),
		]
	)

	pushCase(
		'Unordered list',
		['- A', '- B', '- C'].join('\n'),
		[(o) => assert.ok(o.includes('<ul>') && o.includes('</ul>') && o.includes('<li>A</li>') && o.includes('<li>B</li>'))]
	)

	pushCase(
		'Ordered list',
		['1. A', '2) B', '3. C'].join('\n'),
		[(o) => assert.ok(o.includes('<ol>') && o.includes('</ol>') && o.includes('<li>A</li>') && o.includes('<li>B</li>'))]
	)

	pushCase(
		'Inline code',
		'Use `code` here',
		[(o) => assert.ok(o.includes('<code>code</code>'))]
	)

	pushCase(
		'Fenced code block',
		['```js', 'const a = 1 < 2', '```'].join('\n'),
		[
			(o) => assert.ok(o.includes('<pre><code class="language-js">')),
			(o) => assert.ok(o.includes('const a = 1 &lt; 2')),
			(o) => assert.ok(o.includes('</code></pre>')),
		]
	)

	pushCase(
		'Blockquote',
		['> quote line'].join('\n'),
		[(o) => assert.ok(o.includes('<blockquote>quote line</blockquote>'))]
	)

	pushCase(
		'Horizontal rule',
		['---'].join('\n'),
		[(o) => assert.ok(o.includes('<hr/>'))]
	)

	pushCase(
		'XSS escape',
		'<script>alert(1)</script>',
		[(o) => assert.ok(o.includes('&lt;script&gt;alert(1)&lt;/script&gt;'))]
	)

	writeReport(outDir, cases)
	console.log(`OK: markdown render cases passed: ${cases.length}`)
	console.log(`Report: ${outDir}${path.sep}markdown_render_diff_report.md`)
}

run()

