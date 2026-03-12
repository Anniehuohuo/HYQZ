<template>
	<view class="br">
		<view v-if="!renderBlocks.length && fallbackHtml" class="fallback">
			<rich-text :nodes="fallbackHtml"></rich-text>
		</view>
		<view v-else-if="!renderBlocks.length && fallbackTextShow" class="fallbackText">
			<text class="t">{{ fallbackTextShow }}</text>
		</view>
		<view v-else class="blocks">
			<block v-for="(b, bi) in renderBlocks" :key="bi">
				<view v-if="b.type === 'h1'" class="h1">{{ b.text }}</view>
				<view v-else-if="b.type === 'h2'" class="h2">{{ b.text }}</view>
				<view v-else-if="b.type === 'h3'" class="h3">{{ b.text }}</view>
				<view v-else-if="b.type === 'h4'" class="h4">{{ b.text }}</view>

				<view v-else-if="b.type === 'p'" class="p">
					<block v-for="(it, ii) in (b.inlines || [])" :key="ii">
						<text
							v-if="it.type === 'text'"
							class="t"
							:style="(it.color ? 'color:' + it.color + ';' : '') + (it.bgColor ? 'background-color:' + it.bgColor + ';' : '') + (it.bold ? 'font-weight:700;' : '') + (it.italic ? 'font-style:italic;' : '') + (it.underline ? 'text-decoration:underline;' : '') + (it.strike ? 'text-decoration:line-through;' : '')"
						>{{ it.text }}</text>
						<text v-else-if="it.type === 'emoji'" class="emoji">{{ it.text }}</text>
						<text v-else-if="it.type === 'tag'" class="tag" :style="(it.color ? 'color:' + it.color + ';' : '') + (it.bgColor ? 'background-color:' + it.bgColor + ';' : '')" @click="onTag(it)">{{ it.text }}</text>
						<text v-else-if="it.type === 'link'" class="link" @click="onLink(it)">{{ it.text }}</text>
					</block>
				</view>

				<view v-else-if="b.type === 'list'" class="list">
					<view class="li" v-for="(li, lii) in (b.items || [])" :key="lii">
						<view class="liBullet">{{ b.ordered ? (lii + 1) + '.' : '•' }}</view>
						<view class="liBody">
							<view class="p">
								<block v-for="(it2, ij) in (li.inlines || [])" :key="ij">
									<text v-if="it2.type === 'text'" class="t" :style="(it2.color ? 'color:' + it2.color + ';' : '') + (it2.bgColor ? 'background-color:' + it2.bgColor + ';' : '') + (it2.bold ? 'font-weight:700;' : '') + (it2.italic ? 'font-style:italic;' : '') + (it2.underline ? 'text-decoration:underline;' : '') + (it2.strike ? 'text-decoration:line-through;' : '')">{{ it2.text }}</text>
									<text v-else-if="it2.type === 'emoji'" class="emoji">{{ it2.text }}</text>
									<text v-else-if="it2.type === 'tag'" class="tag" :style="(it2.color ? 'color:' + it2.color + ';' : '') + (it2.bgColor ? 'background-color:' + it2.bgColor + ';' : '')" @click="onTag(it2)">{{ it2.text }}</text>
									<text v-else-if="it2.type === 'link'" class="link" @click="onLink(it2)">{{ it2.text }}</text>
								</block>
							</view>
						</view>
					</view>
				</view>

				<view v-else-if="b.type === 'code'" class="code">
					<view class="codeHd">
						<text class="codeLang">{{ b.lang || 'code' }}</text>
						<view class="codeActions">
							<text class="codeBtn" @click="copyText(b.text)">复制</text>
							<text v-if="isFoldableCode(b)" class="codeBtn" @click="toggleFold('code', bi)">{{ isFolded('code', bi) ? '展开' : '收起' }}</text>
						</view>
					</view>
					<scroll-view scroll-x class="codeBd" :class="{ codeBdFolded: isFoldableCode(b) && isFolded('code', bi) }">
						<text class="codeTxt">{{ b.text }}</text>
					</scroll-view>
					<view v-if="isFoldableCode(b) && isFolded('code', bi)" class="fade"></view>
				</view>

				<view v-else-if="b.type === 'quote'" class="quote">
					<view class="quoteHd" v-if="b.title || isFoldableQuote(b)">
						<text v-if="b.title" class="quoteTitle">{{ b.title }}</text>
						<text v-if="isFoldableQuote(b)" class="quoteFold" @click="toggleFold('quote', bi)">{{ isFolded('quote', bi) ? '展开' : '收起' }}</text>
					</view>
					<view class="quoteBody" :class="{ quoteBodyFolded: isFoldableQuote(b) && isFolded('quote', bi) }">
						<block v-for="(qb, qbi) in (b.blocks || [])" :key="qbi">
							<view v-if="qb.type === 'p'" class="p">
								<block v-for="(qit, qii) in (qb.inlines || [])" :key="qii">
									<text v-if="qit.type === 'text'" class="t" :style="(qit.color ? 'color:' + qit.color + ';' : '') + (qit.bgColor ? 'background-color:' + qit.bgColor + ';' : '') + (qit.bold ? 'font-weight:700;' : '') + (qit.italic ? 'font-style:italic;' : '') + (qit.underline ? 'text-decoration:underline;' : '') + (qit.strike ? 'text-decoration:line-through;' : '')">{{ qit.text }}</text>
									<text v-else-if="qit.type === 'emoji'" class="emoji">{{ qit.text }}</text>
									<text v-else-if="qit.type === 'tag'" class="tag" :style="(qit.color ? 'color:' + qit.color + ';' : '') + (qit.bgColor ? 'background-color:' + qit.bgColor + ';' : '')" @click="onTag(qit)">{{ qit.text }}</text>
									<text v-else-if="qit.type === 'link'" class="link" @click="onLink(qit)">{{ qit.text }}</text>
								</block>
							</view>
							<view v-else-if="qb.type === 'code'" class="code">
								<view class="codeHd">
									<text class="codeLang">{{ qb.lang || 'code' }}</text>
									<view class="codeActions">
										<text class="codeBtn" @click="copyText(qb.text)">复制</text>
									</view>
								</view>
								<scroll-view scroll-x class="codeBd">
									<text class="codeTxt">{{ qb.text }}</text>
								</scroll-view>
							</view>
						</block>
					</view>
					<view v-if="isFoldableQuote(b) && isFolded('quote', bi)" class="fade"></view>
				</view>

				<view v-else-if="b.type === 'image'" class="imgWrap">
					<image v-if="b.urls && b.urls.length" class="img" :src="b.urls[0]" mode="widthFix"></image>
				</view>

				<view v-else-if="b.type === 'divider'" class="divider"></view>
				<view v-else-if="b.type === 'space'" :style="{ height: (b.size || 12) + 'px' }"></view>
			</block>
			<view v-if="progressive && renderBlocks.length < blocksNorm.length" class="moreHint">
				<text class="moreHintText">内容较长，继续加载中…</text>
			</view>
		</view>
	</view>
</template>

<script>
	const MAX_FIRST = 18
	const STEP = 18
	const MAX_CODE_LINES = 10
	const MAX_QUOTE_BLOCKS = 6
const KEYWORD_TITLE_RE = /^(评分|分析与反馈|亮点分析|需要提升的地方|提升建议|改进建议|问题分析|建议|总结|结论|下一步|行动建议|风险提示|注意事项|场景)\s*[：:]\s*(.*)$/
const COMMON_TITLE_RE = /^([^\s：:]{2,16})\s*[：:]\s*(.*)$/

	function safeArray(v) {
		return Array.isArray(v) ? v : []
	}

	function splitStrongMarkdownInline(inline) {
		if (!inline || inline.type !== 'text') return [inline]
		if (inline.bold) return [inline]
		const raw = String(inline.text || '')
		if (!raw || (raw.indexOf('**') === -1 && raw.indexOf('__') === -1)) return [inline]
		const reg = /(\*\*[^*]+\*\*|__[^_]+__)/g
		let last = 0
		let m
		const out = []
		while ((m = reg.exec(raw)) !== null) {
			const idx = m.index
			if (idx > last) {
				out.push({ ...inline, text: raw.slice(last, idx) })
			}
			const seg = String(m[0] || '')
			const inner = seg.startsWith('**') ? seg.slice(2, -2) : seg.slice(2, -2)
			if (inner) out.push({ ...inline, text: inner, bold: true })
			last = idx + seg.length
		}
		if (last < raw.length) out.push({ ...inline, text: raw.slice(last) })
		return out.length ? out : [inline]
	}

	function normalizeInlinesWithEmphasis(arr) {
		const out = []
		for (const it of safeArray(arr)) {
			const norm = normalizeInline(it)
			if (!norm) continue
			const parts = splitStrongMarkdownInline(norm)
			for (const p of safeArray(parts)) {
				if (!p) continue
				if (String(p.text || '') === '') continue
				out.push(p)
			}
		}
		return out
	}

	function normalizeInline(it) {
	if (typeof it === 'string' || typeof it === 'number') {
		const text = String(it)
		if (!text) return null
		return { type: 'text', text }
	}
		if (!it || typeof it !== 'object') return null
		const type = String(it.type || 'text')
		if (type === 'text') {
			const text = String(it.text || '')
			if (!text) return null
			return {
				type: 'text',
				text,
				color: it.color ? String(it.color) : '',
				bgColor: it.bgColor ? String(it.bgColor) : '',
				bold: !!it.bold,
				italic: !!it.italic,
				underline: !!it.underline,
				strike: !!it.strike
			}
		}
		if (type === 'emoji') return { type: 'emoji', text: String(it.text || '') }
		if (type === 'tag') return { type: 'tag', text: String(it.text || ''), color: it.color ? String(it.color) : '', bgColor: it.bgColor ? String(it.bgColor) : '' }
		if (type === 'link') return { type: 'link', text: String(it.text || ''), url: String(it.url || '') }
		return null
	}

	function normalizeBlock(b) {
		if (!b || typeof b !== 'object') return null
		const type = String(b.type || '')
		if (!type) return null
	if (/^h[1-4]$/.test(type)) return { type, text: String(b.text || b.content || '') }
		if (type === 'p') {
		let inlines = normalizeInlinesWithEmphasis(b.inlines)
		if (!inlines.length) {
			const text = String(b.text || b.content || '')
			if (text) inlines = normalizeInlinesWithEmphasis([{ type: 'text', text }])
		}
			if (!inlines.length) return null
			return { type: 'p', inlines }
		}
		if (type === 'list') {
			const items = safeArray(b.items).map(x => {
			let inlines = normalizeInlinesWithEmphasis(x && x.inlines)
			if (!inlines.length) {
				const text = String((x && (x.text || x.content)) || '')
				if (text) inlines = normalizeInlinesWithEmphasis([{ type: 'text', text }])
			}
			if (!inlines.length && (typeof x === 'string' || typeof x === 'number')) {
				const text = String(x)
				if (text) inlines = normalizeInlinesWithEmphasis([{ type: 'text', text }])
			}
				return inlines.length ? { inlines } : null
			}).filter(Boolean)
			return { type: 'list', ordered: !!b.ordered, items }
		}
		if (type === 'code') return { type: 'code', lang: String(b.lang || ''), text: String(b.text || b.content || '') }
		if (type === 'quote') {
			const blocks = safeArray(b.blocks).map(normalizeBlock).filter(Boolean)
			return { type: 'quote', title: String(b.title || ''), blocks }
		}
		if (type === 'image') return { type: 'image', urls: safeArray(b.urls).map(String), aspectRatio: Number(b.aspectRatio || 0) || 0 }
		if (type === 'divider') return { type: 'divider' }
		if (type === 'space') return { type: 'space', size: Number(b.size || 12) || 12 }
		return { type: 'quote', title: '未识别内容', blocks: [{ type: 'code', lang: 'json', text: JSON.stringify(b) }] }
	}
	
	function compactBlocks(blocks) {
		const out = []
		for (const b of safeArray(blocks)) {
			if (!b) continue
			const prev = out.length ? out[out.length - 1] : null
			if (prev && prev.type === 'list' && b.type === 'list' && !!prev.ordered === !!b.ordered) {
				prev.items = safeArray(prev.items).concat(safeArray(b.items))
				continue
			}
			out.push(b)
		}
		return out
	}

function inlinesToText(inlines) {
	return safeArray(inlines).map(it => String(it && it.text || '')).join('')
}

function makeParagraph(text, boldPrefix = '') {
	const t = String(text || '').trim()
	if (!t && !boldPrefix) return null
	const inlines = []
	const bp = String(boldPrefix || '').trim()
	if (bp) {
		inlines.push({ type: 'text', text: bp, bold: true })
	}
	if (t) {
		inlines.push({ type: 'text', text: bp ? (' ' + t) : t })
	}
	return { type: 'p', inlines }
}

function linesToBlocks(lines) {
	const out = []
	let orderedItems = []
	let unorderedItems = []
	const flushOrdered = () => {
		if (!orderedItems.length) return
		out.push({ type: 'list', ordered: true, items: orderedItems })
		orderedItems = []
	}
	const flushUnordered = () => {
		if (!unorderedItems.length) return
		out.push({ type: 'list', ordered: false, items: unorderedItems })
		unorderedItems = []
	}
	for (const lineRaw of safeArray(lines)) {
		const line = String(lineRaw || '').trim()
		if (!line) continue
		const mo = line.match(/^(\d+)[\.、]\s*(.+)$/)
		if (mo) {
			flushUnordered()
			orderedItems.push({ inlines: [{ type: 'text', text: String(mo[2] || '').trim() }] })
			continue
		}
		const mu = line.match(/^[-*]\s+(.+)$/)
		if (mu) {
			flushOrdered()
			unorderedItems.push({ inlines: [{ type: 'text', text: String(mu[1] || '').trim() }] })
			continue
		}
		flushOrdered()
		flushUnordered()
		const p = makeParagraph(line)
		if (p) out.push(p)
	}
	flushOrdered()
	flushUnordered()
	return out
}

function splitKeywordParagraph(block) {
	if (!block || block.type !== 'p') return [block]
	const raw = inlinesToText(block.inlines).trim()
	if (!raw) return [block]
	const lines = raw.split(/\r?\n/).map(s => String(s || '').trim()).filter(Boolean)
	if (!lines.length) return [block]
	const out = []
	for (const line of lines) {
		const probe = line.replace(/\*\*/g, '').trim()
		let m = probe.match(KEYWORD_TITLE_RE)
		if (!m) m = probe.match(COMMON_TITLE_RE)
		if (m) {
			const title = String(m[1] || '').trim()
			let rest = String(m[2] || '').trim()
			rest = rest.replace(/^\*\*(.*)\*\*$/g, '$1').trim()
			const head = makeParagraph(rest, title + '：')
			if (head) {
				out.push(head)
				continue
			}
		}
		const bodyBlocks = linesToBlocks([line])
		for (const b of bodyBlocks) out.push(b)
	}
	return out.length ? out : [block]
}

function splitKeywordBlocks(blocks) {
	const out = []
	for (const b of safeArray(blocks)) {
		const parts = splitKeywordParagraph(b)
		for (const p of safeArray(parts)) out.push(p)
	}
	return out
}

function blockHasVisibleContent(b) {
	if (!b || typeof b !== 'object') return false
	const type = String(b.type || '')
	if (/^h[1-4]$/.test(type)) return String(b.text || '').trim() !== ''
	if (type === 'p') return safeArray(b.inlines).some(it => String(it && it.text || '').trim() !== '')
	if (type === 'list') {
		const items = safeArray(b.items)
		if (!items.length) return false
		return items.some(li => safeArray(li && li.inlines).some(it => String(it && it.text || '').trim() !== ''))
	}
	if (type === 'code') return String(b.text || '').trim() !== ''
	if (type === 'quote') return String(b.title || '').trim() !== '' || safeArray(b.blocks).some(blockHasVisibleContent)
	if (type === 'image') return safeArray(b.urls).length > 0
	if (type === 'divider' || type === 'space') return true
	return false
}

function textFromBlocks(blocks) {
	const lines = []
	const walk = (arr) => {
		for (const b of safeArray(arr)) {
			if (!b || typeof b !== 'object') continue
			const type = String(b.type || '')
			if (/^h[1-4]$/.test(type)) {
				const t = String(b.text || b.content || '').trim()
				if (t) lines.push(t)
				continue
			}
			if (type === 'p') {
				const inlines = safeArray(b.inlines)
				const t = inlines.length ? inlines.map(it => String(it && it.text || '')).join('') : String(b.text || b.content || '')
				if (String(t || '').trim()) lines.push(String(t))
				continue
			}
			if (type === 'list') {
				for (const li of safeArray(b.items)) {
					const inlines = safeArray(li && li.inlines)
					let t = inlines.map(it => String(it && it.text || '')).join('')
					if (!t) t = String((li && (li.text || li.content)) || (typeof li === 'string' ? li : ''))
					if (String(t || '').trim()) lines.push(String(t))
				}
				continue
			}
			if (type === 'code') {
				const t = String(b.text || b.content || '')
				if (String(t || '').trim()) lines.push(t)
				continue
			}
			if (type === 'quote') {
				const title = String(b.title || '').trim()
				if (title) lines.push(title)
				walk(b.blocks)
				continue
			}
		}
	}
	walk(blocks)
	return lines.join('\n').trim()
}

	export default {
		name: 'BlocksRenderer',
		props: {
			blocks: { type: Array, default: () => [] },
			fallbackHtml: { type: String, default: '' },
			fallbackText: { type: String, default: '' },
			messageId: { type: [String, Number], default: '' },
			progressive: { type: Boolean, default: true }
		},
		data() {
			return {
				limit: MAX_FIRST,
				folded: {}
			}
		},
		computed: {
			blocksNorm() {
				const normalized = safeArray(this.blocks).map(normalizeBlock).filter(Boolean)
				return compactBlocks(splitKeywordBlocks(normalized))
			},
			blocksRenderable() {
				return this.blocksNorm.filter(blockHasVisibleContent)
			},
			renderBlocks() {
				if (!this.progressive) return this.blocksRenderable
				return this.blocksRenderable.slice(0, Math.min(this.blocksRenderable.length, this.limit))
			},
			fallbackTextShow() {
				const t = String(this.fallbackText || '').trim()
				if (t) return t
				return textFromBlocks(this.blocks)
			}
		},
		watch: {
			blocksNorm: {
				immediate: true,
				handler(val) {
					if (!this.progressive) return
					if (!val || !val.length) {
						this.limit = MAX_FIRST
						return
					}
					const target = Math.min(val.length, MAX_FIRST)
					this.limit = target
					this.$nextTick(() => {
						setTimeout(this.loadMore, 16)
					})
				}
			}
		},
		methods: {
			loadMore() {
				if (!this.progressive) return
				const total = this.blocksNorm.length
				if (this.limit >= total) return
				this.limit = Math.min(total, this.limit + STEP)
				this.$nextTick(() => {
					setTimeout(this.loadMore, 16)
				})
			},
			copyText(text) {
				const v = String(text || '')
				if (!v) return
				uni.setClipboardData({ data: v })
			},
			onLink(it) {
				const url = it && it.url ? String(it.url) : ''
				if (!url) return
				uni.setClipboardData({ data: url })
				uni.showToast({ title: '链接已复制', icon: 'none' })
			},
			onTag(it) {
				const t = it && it.text ? String(it.text) : ''
				if (!t) return
				uni.showToast({ title: t, icon: 'none' })
			},
			foldKey(kind, bi) {
				return String(this.messageId || 'm') + ':' + String(kind) + ':' + String(bi)
			},
			isFolded(kind, bi) {
				return !!this.folded[this.foldKey(kind, bi)]
			},
			toggleFold(kind, bi) {
				const k = this.foldKey(kind, bi)
				this.$set(this.folded, k, !this.folded[k])
			},
			isFoldableCode(b) {
				const text = String(b && b.text || '')
				const lines = text.split(/\r?\n/).length
				return lines > MAX_CODE_LINES
			},
			isFoldableQuote(b) {
				const blocks = safeArray(b && b.blocks)
				return blocks.length > MAX_QUOTE_BLOCKS
			},
			
		}
	}
</script>

<style lang="scss" scoped>
	.br {
		width: 100%;
	}

	.blocks {
		width: 100%;
	}

	.h1 {
		font-size: 38rpx;
		line-height: 52rpx;
		font-weight: 900;
		margin: 10rpx 0 8rpx;
	}

	.h2 {
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 900;
		margin: 10rpx 0 8rpx;
	}

	.h3,
	.h4 {
		font-size: 30rpx;
		line-height: 44rpx;
		font-weight: 900;
		margin: 8rpx 0 6rpx;
	}

	.p {
		margin: 6rpx 0;
		font-size: 26rpx;
		line-height: 40rpx;
		color: rgba(31, 35, 41, 0.92);
		word-break: break-word;
		white-space: pre-wrap;
	}

	.t {
		white-space: pre-wrap;
		word-break: break-word;
		font-weight: 500;
	}

	.link {
		color: #2f6feb;
		text-decoration: underline;
	}

	.tag {
		padding: 2rpx 12rpx;
		border-radius: 999rpx;
		font-size: 24rpx;
		background: rgba(47, 111, 235, 0.10);
		margin: 0 6rpx;
	}

	.list {
		margin: 8rpx 0;
	}

	.li {
		display: flex;
		margin: 6rpx 0;
	}

	.liBullet {
		width: 32rpx;
		flex: none;
		color: rgba(31, 35, 41, 0.55);
	}

	.liBody {
		flex: 1;
	}

	.code {
		margin: 12rpx 0;
		border-radius: 16rpx;
		overflow: hidden;
		background: #0b1020;
		position: relative;
	}

	.codeHd {
		padding: 12rpx 16rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.codeLang {
		color: rgba(255, 255, 255, 0.65);
		font-size: 22rpx;
	}

	.codeActions {
		display: flex;
		gap: 14rpx;
	}

	.codeBtn {
		color: rgba(255, 255, 255, 0.80);
		font-size: 22rpx;
	}

	.codeBd {
		padding: 14rpx 16rpx 16rpx;
	}
	
	.codeBdFolded {
		max-height: 360rpx;
	}

	.codeTxt {
		color: #fff;
		font-family: Menlo, Consolas, monospace;
		font-size: 22rpx;
		line-height: 34rpx;
		white-space: pre;
	}

	.quote {
		margin: 12rpx 0;
		padding: 14rpx 16rpx;
		border-radius: 16rpx;
		background: rgba(241, 165, 92, 0.10);
		border: 1rpx solid rgba(241, 165, 92, 0.18);
		position: relative;
	}

	.quoteHd {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 6rpx;
	}

	.quoteTitle {
		font-weight: 800;
	}

	.quoteFold {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.55);
	}

	.quoteBody {
		position: relative;
	}
	
	.quoteBodyFolded {
		max-height: 420rpx;
		overflow: hidden;
	}

	.divider {
		height: 1rpx;
		background: rgba(0, 0, 0, 0.08);
		margin: 12rpx 0;
	}

	.imgWrap {
		margin: 12rpx 0;
		border-radius: 16rpx;
		overflow: hidden;
	}

	.img {
		width: 100%;
	}

	.moreHint {
		margin-top: 8rpx;
	}

	.moreHintText {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.45);
	}

	.fade {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		height: 120rpx;
		background: linear-gradient(to bottom, rgba(11, 16, 32, 0), rgba(11, 16, 32, 1));
		pointer-events: none;
	}

	.quote .fade {
		background: linear-gradient(to bottom, rgba(241, 165, 92, 0), rgba(241, 165, 92, 0.20));
	}
</style>
