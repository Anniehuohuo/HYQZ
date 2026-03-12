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

function buildSample() {
	const blocks = []
	blocks.push('# 标题一')
	blocks.push('这是一段正文，包含 **加粗**、*斜体*、以及 `行内代码`。')
	blocks.push('---')
	blocks.push('> 引用内容：强调一个关键点。')
	blocks.push('1. 有序列表 A')
	blocks.push('2. 有序列表 B')
	blocks.push('- 无序列表 A')
	blocks.push('- 无序列表 B')
	blocks.push('```js')
	blocks.push('function add(a, b) {')
	blocks.push('  return a + b')
	blocks.push('}')
	blocks.push('```')
	blocks.push('## 标题二')
	blocks.push('再来一段正文。')
	return blocks.join('\n')
}

function bench(iterations) {
	const sample = buildSample()
	md.markdownToHtml(sample)
	const start = process.hrtime.bigint()
	let bytes = 0
	for (let i = 0; i < iterations; i++) {
		const out = md.markdownToHtml(sample)
		bytes += out.length
	}
	const end = process.hrtime.bigint()
	const ns = Number(end - start)
	const ms = ns / 1e6
	const ops = iterations / (ms / 1000)
	return { iterations, ms, ops, bytes }
}

function run() {
	const iterations = Number(process.env.BENCH_ITERS || '5000') || 5000
	const outDir = path.join(process.cwd(), 'scripts_out', `markdown_bench_${nowIsoCompact()}`)
	ensureDir(outDir)

	const r = bench(iterations)
	const lines = []
	lines.push('# Markdown 渲染性能基准')
	lines.push('')
	lines.push(`- iterations: ${r.iterations}`)
	lines.push(`- total_ms: ${r.ms.toFixed(2)}`)
	lines.push(`- ops_per_sec: ${r.ops.toFixed(2)}`)
	lines.push(`- output_bytes: ${r.bytes}`)
	lines.push('')

	fs.writeFileSync(path.join(outDir, 'markdown_render_benchmark.md'), lines.join('\n'), 'utf8')
	console.log(`OK: markdown render benchmark done`)
	console.log(`Output: ${outDir}${path.sep}markdown_render_benchmark.md`)
}

run()

