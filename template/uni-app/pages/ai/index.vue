<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<view class="pageFx"></view>
		<scroll-view class="chat" :style="{ height: chatHeight }" scroll-y="true" :scroll-top="scrollTop" @scrolltolower="noop" :lower-threshold="60">
			<view class="chatInner">
				<view class="intro" v-if="showIntro">
					<view class="heroCard">
						<view class="heroHead">
							<view class="heroText">
								<text class="heroTitle">你好，</text>
							<text class="heroTitle">{{ heroTitle }}</text>
							<text class="heroSub">{{ heroSub }}</text>
							</view>
							<view class="heroMascot">
								<view class="mascotGlow"></view>
								<image class="heroLogo" :src="heroLogoSrc" mode="aspectFill" @error="onHeroLogoError"></image>
							</view>
						</view>
						<view class="qaChips">
							<view class="qaChip" v-for="(s, i) in suggestions" :key="i" @click="startWithSuggestion(s)">
								<text class="qaChipText">{{ s }}</text>
							</view>
						</view>
					</view>
				</view>
				<view v-if="!showIntro">
					<view class="msg" v-for="(m, mi) in safeMessages" :key="m.id || ('m_' + mi)" :class="m.role === 'user' ? 'user' : 'bot'">
						<view class="bubble" v-if="m.text">
							<text class="bubbleText">{{ m.text }}</text>
						</view>
						<view class="disambig" v-if="m.options && m.options.length">
							<view class="disambigBtn" v-for="o in (m.options || []).filter(Boolean)" :key="o.id || (o.name || '')" @click="chooseDisambig(mi, o)">
								<text class="disambigBtnText">{{ o.name }}</text>
							</view>
						</view>
						<view class="cards" v-if="m.cards && m.cards.length">
							<view class="card" v-for="c in (m.cards || []).filter(Boolean)" :key="c.agentId || c.id" @click="goAgent(c)">
								<view class="cardCover">
									<image v-if="c.cover" class="cardImg" :src="c.cover" mode="aspectFill"></image>
									<view class="cardLabel">
										<text class="cardLabelText">智能体</text>
									</view>
								</view>
								<view class="cardBody">
									<text class="cardTitle">{{ c.title }}</text>
									<text class="cardDesc">{{ c.slogan || c.detail }}</text>
									<view class="cardFoot">
										<view class="cardBtn">
											<text class="cardBtnText">去看看</text>
										</view>
									</view>
								</view>
							</view>
						</view>
						<view class="matrixCta" v-if="m.matrixCta">
							<view class="matrixBtn" @click="goAgentMatrix">
								<text class="matrixBtnText">去智能体矩阵聊</text>
							</view>
						</view>
					</view>

					<view class="typing" v-if="sending">
						<view class="typingDot"></view>
						<view class="typingDot"></view>
						<view class="typingDot"></view>
					</view>
				</view>
			</view>
		</scroll-view>

		<view class="composer" :style="{ bottom: footerBottom }">
			<view class="composerInner">
				<view class="leftBadge" @click="clearChat">
					<text class="iconfont icon-shanchu31 leftIcon"></text>
				</view>
				<input class="input" v-model="draft" :adjust-position="true" confirm-type="send" placeholder="继续对话..." @confirm="send" />
				<view class="send" :class="{ disabled: !draftTrim }" @click="send">
					<text class="iconfont icon-fasong sendIcon"></text>
				</view>
			</view>
			<view class="aiDisclaimer">
				<text class="aiDisclaimerText">本服务为AI生成内容，结果仅供参考</text>
			</view>
			<view class="safePad"></view>
		</view>

		<pageFooter @newDataStatus="newDataStatus" v-show="showBar"></pageFooter>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import {
		getShare
	} from '@/api/public.js'
	import { getAgentSaleInfo, getAgentAccess } from '@/api/ai.js'
	import {
		HTTP_REQUEST_URL,
		HEADER
	} from '@/config/app.js'
	import pageFooter from '@/components/pageFooter/index.vue'
	import { toLogin } from '@/libs/login.js'

	function uid() {
		return `${Date.now()}_${Math.random().toString(16).slice(2)}`
	}

	function decodeUtf8(input) {
		try {
			if (typeof input === 'string') return input
			if (!input) return ''
			const u8 = input instanceof Uint8Array ? input : new Uint8Array(input)
			if (typeof TextDecoder !== 'undefined') {
				return new TextDecoder('utf-8').decode(u8)
			}
			let out = ''
			let i = 0
			while (i < u8.length) {
				const c = u8[i++]
				if (c < 0x80) {
					out += String.fromCharCode(c)
					continue
				}
				if (c >= 0xC0 && c < 0xE0) {
					const c2 = u8[i++] & 0x3F
					out += String.fromCharCode(((c & 0x1F) << 6) | c2)
					continue
				}
				if (c >= 0xE0 && c < 0xF0) {
					const c2 = u8[i++] & 0x3F
					const c3 = u8[i++] & 0x3F
					out += String.fromCharCode(((c & 0x0F) << 12) | (c2 << 6) | c3)
					continue
				}
				if (c >= 0xF0) {
					const c2 = u8[i++] & 0x3F
					const c3 = u8[i++] & 0x3F
					const c4 = u8[i++] & 0x3F
					let codepoint = ((c & 0x07) << 18) | (c2 << 12) | (c3 << 6) | c4
					codepoint -= 0x10000
					out += String.fromCharCode(0xD800 + ((codepoint >> 10) & 0x3FF))
					out += String.fromCharCode(0xDC00 + (codepoint & 0x3FF))
				}
			}
			return out
		} catch (e) {
			return ''
		}
	}

	export default {
		mixins: [colors],
		components: {
			pageFooter
		},
		data() {
			return {
				isFooter: false,
				pdHeight: 0,
				showBar: false,
				showIntro: true,
				homeAgentEnabled: true,
				heroTitle: '小圆为你服务',
				heroSub: '我可以帮你把亲子沟通问题拆清楚，给到可直接照读的引导句式。你想问点什么呢？',
				heroLogoFallback: '/static/images/jf-head.png',
				heroLogoSrc: '/static/images/jf-head.png',
				shareInfo: {},
				shareCover: '',
				draft: '',
				sending: false,
				scrollTop: 0,
				replyCount: 0,
				turnCount: 0,
				lastRecommendedId: 0,
				sessionId: '',
				agentId: 'hyqz_default',
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立'],
				recommendProducts: []
			}
		},
		onLoad(options) {
			this.agentId = (options && options.agentId) ? String(options.agentId) : 'hyqz_default'
			this.sessionId = this.getOrCreateSessionId(this.agentId)
			this.loadHomeAgentConfig()
			this.loadRecommendProducts()
			this.loadChatState()
			//#ifdef MP
			uni.showShareMenu({
				withShareTicket: true,
				menus: ['shareAppMessage', 'shareTimeline']
			})
			uni.getImageInfo({
				src: this.heroLogoFallback,
				success: (res) => {
					this.shareCover = res.path
				},
				fail: () => {
					this.shareCover = this.heroLogoFallback
				}
			})
			//#endif
			getShare().then((res) => {
				this.shareInfo = res.data || {}
			})
			this.heroLogoSrc = this.buildHeroSvg()
			if (!this.messages || !this.messages.length) {
				this.messages = [{
					id: uid(),
					role: 'bot',
					text: '把发生的场景描述给我：谁、在什么时间、说了什么、你怎么回应的？我会给你一套可执行的沟通步骤。',
					cards: []
				}]
			}
		},
		onHide() {
			this.saveChatState()
		},
		onUnload() {
			this.saveChatState()
		},
		computed: {
			pagePad() {
				if (this.isFooter) {
					return {
						paddingBottom: `${this.pdHeight * 2 + 140}rpx`
					}
				}
				return {}
			}
			,
			footerOffsetRpx() {
				if (!this.isFooter) return 0
				return this.pdHeight * 2 + 120
			},
			footerBottom() {
				if (!this.isFooter) return '0rpx'
				return `${this.footerOffsetRpx}rpx`
			},
			chatHeight() {
				if (!this.isFooter) return 'calc(100vh - 160rpx)'
				return `calc(100vh - 160rpx - ${this.footerOffsetRpx}rpx)`
			},
			draftTrim() {
				return (this.draft || '').trim()
			},
			safeMessages() {
				const list = Array.isArray(this.messages) ? this.messages : []
				return list.filter(m => m && typeof m === 'object')
			}
		},
		methods: {
			getChatStateStorageKey() {
				return `home_ai_chat_state:${this.agentId || 'hyqz_default'}`
			},
			loadChatState() {
				let state = null
				try {
					state = uni.getStorageSync(this.getChatStateStorageKey())
				} catch (e) {
					state = null
				}
				if (!state || typeof state !== 'object') return
				const msgs = Array.isArray(state.messages) ? state.messages.filter(m => m && typeof m === 'object') : []
				if (!msgs.length) return
				this.messages = msgs
				this.turnCount = Number(state.turnCount || 0) || 0
				this.replyCount = Number(state.replyCount || 0) || 0
				this.lastRecommendedId = Number(state.lastRecommendedId || 0) || 0
				this.showIntro = false
			},
			saveChatState() {
				const msgs = Array.isArray(this.messages) ? this.messages.slice(-30) : []
				const payload = {
					messages: msgs,
					turnCount: this.turnCount || 0,
					replyCount: this.replyCount || 0,
					lastRecommendedId: this.lastRecommendedId || 0,
					ts: Date.now()
				}
				try {
					uni.setStorageSync(this.getChatStateStorageKey(), payload)
				} catch (e) {}
			},
			clearChat() {
				if (this.sending) return
				uni.showModal({
					title: '清空聊天',
					content: '确定要清空当前聊天记录吗？',
					confirmText: '清空',
					cancelText: '取消',
					success: (res) => {
						if (!res || !res.confirm) return
						const agentId = this.agentId || 'hyqz_default'
						const sessionKey = `home_ai_session_id:${agentId}`
						const stateKey = this.getChatStateStorageKey()
						const newSessionId = `${Date.now()}_${Math.random().toString(16).slice(2)}`
						try {
							uni.setStorageSync(sessionKey, newSessionId)
						} catch (e) {}
						try {
							uni.removeStorageSync(stateKey)
						} catch (e) {}
						this.sessionId = newSessionId
						this.draft = ''
						this.sending = false
						this.scrollTop = 0
						this.replyCount = 0
						this.turnCount = 0
						this.lastRecommendedId = 0
						this.messages = [{
							id: uid(),
							role: 'bot',
							text: '把发生的场景描述给我：谁、在什么时间、说了什么、你怎么回应的？我会给你一套可执行的沟通步骤。',
							cards: []
						}]
						this.showIntro = true
					}
				})
			},
			getOrCreateSessionId(agentId) {
				const key = `home_ai_session_id:${agentId || 'hyqz_default'}`
				let sid = ''
				try {
					sid = String(uni.getStorageSync(key) || '')
				} catch (e) {
					sid = ''
				}
				if (sid) return sid
				sid = `${Date.now()}_${Math.random().toString(16).slice(2)}`
				try {
					uni.setStorageSync(key, sid)
				} catch (e) {}
				return sid
			},
			loadRecommendProducts() {
				this.recommendProducts = []
			},
			attachRecommendCards(botMsgIndex) {
				const text = (this.messages[botMsgIndex] && this.messages[botMsgIndex].text) ? String(this.messages[botMsgIndex].text) : ''
				const matrixFlag = /\[\[GO_AGENT(?:_|\s)MATRIX\]\]/.test(text)
				const optRe = /\[\[DISAMBIG_OPTIONS:([^\]]*)\]\]/g
				let optLast = null
				let om = null
				while ((om = optRe.exec(text)) !== null) {
					optLast = om
				}
				let disambigOptions = []
				if (optLast) {
					const raw = String(optLast[1] || '').trim()
					if (raw) {
						disambigOptions = raw.split(';').map(p => {
							const parts = String(p || '').split('|')
							const id = parseInt(String(parts[0] || '').trim())
							const name = String(parts[1] || '').trim()
							return (Number.isFinite(id) && id > 0 && name) ? {
								id,
								name
							} : null
						}).filter(Boolean)
					}
				}
				const re = /\[\[RECOMMEND_AGENTS:([0-9,\s]*)\]\]/g
				let lastMatch = null
				let mm = null
				while ((mm = re.exec(text)) !== null) {
					lastMatch = mm
				}
				if (!lastMatch && !matrixFlag && !disambigOptions.length) return Promise.resolve(false)
				const idsRaw = lastMatch ? String(lastMatch[1] || '').trim() : ''
				let cleanText = text
				cleanText = cleanText.replace(/\[\[RECOMMEND_AGENTS:([0-9,\s]*)\]\]/g, '')
				cleanText = cleanText.replace(/\[\[GO_AGENT(?:_|\s)MATRIX\]\]/g, '').trim()
				cleanText = cleanText.replace(/\[\[DISAMBIG_OPTIONS:[^\]]*\]\]/g, '').trim()
				if (!cleanText) {
					if (idsRaw) {
						cleanText = '我先给你匹配了一个可能相关的智能体，你可以点下面卡片查看。'
					}
				}
				this.messages[botMsgIndex].text = cleanText
				this.messages[botMsgIndex].options = disambigOptions
				if (!idsRaw) {
					this.messages[botMsgIndex].cards = []
					this.messages[botMsgIndex].matrixCta = matrixFlag
					this.saveChatState()
					return Promise.resolve(true)
				}
				this.messages[botMsgIndex].options = []
				this.messages[botMsgIndex].matrixCta = false
				const ids = idsRaw.split(',').map(v => parseInt(String(v).trim())).filter(v => Number.isFinite(v) && v > 0)
				if (!ids.length) {
					this.messages[botMsgIndex].cards = []
					this.messages[botMsgIndex].matrixCta = matrixFlag
					return Promise.resolve(true)
				}
				return Promise.all(ids.slice(0, 1).map(id => getAgentSaleInfo({ agent_id: id }))).then(resList => {
					const cards = []
					for (const res of resList) {
						const d = res && res.data ? res.data : null
						if (!d || !d.agent_id) continue
						cards.push({
							agentId: d.agent_id,
							agentName: d.agent_name || '',
							title: d.title || d.agent_name || '智能体',
							slogan: d.slogan || '',
							detail: d.detail || '',
							cover: d.cover || '',
							productId: d.product_id || 0,
							unlocked: !!d.unlocked
						})
					}
					this.messages[botMsgIndex].cards = cards
					if (cards && cards.length) {
						this.lastRecommendedId = Number(cards[0].agentId || 0) || 0
					}
					this.messages[botMsgIndex].matrixCta = false
					this.messages[botMsgIndex].options = []
					this.saveChatState()
					return true
				}).catch(() => {
					this.saveChatState()
					return false
				})
			},
			loadHomeAgentConfig() {
				uni.request({
					url: HTTP_REQUEST_URL + '/api/ai/home_config',
					method: 'GET',
					success: (res) => {
						const d = res && res.data && res.data.data ? res.data.data : null
						if (!d) return
						const enabled = Number(d.enabled || 0) === 1 && Number(d.status || 0) === 1
						this.homeAgentEnabled = enabled
						const name = d.name || '小圆为你服务'
						this.heroTitle = name
						if (!enabled) {
							this.heroSub = '助手暂未启用，请稍后再试'
						}
					},
					fail: () => {}
				})
			},
			buildHeroSvg() {
				const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <defs>
    <radialGradient id="g1" cx="40%" cy="35%" r="70%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.95"/>
      <stop offset="35%" stop-color="#ffd4a4" stop-opacity="0.9"/>
      <stop offset="70%" stop-color="#e98f36" stop-opacity="0.55"/>
      <stop offset="100%" stop-color="#e98f36" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="g2" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#e98f36" stop-opacity="0.95"/>
      <stop offset="100%" stop-color="#f9e9c8" stop-opacity="0.95"/>
    </linearGradient>
    <filter id="blur" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="10"/>
    </filter>
  </defs>
  <g>
    <circle cx="128" cy="128" r="92" fill="url(#g1)" filter="url(#blur)">
      <animate attributeName="opacity" values="0.75;1;0.75" dur="2.2s" repeatCount="indefinite"/>
    </circle>
    <g>
      <animateTransform attributeName="transform" type="rotate" from="0 128 128" to="360 128 128" dur="10s" repeatCount="indefinite"/>
      <path d="M128 34c52 0 94 42 94 94s-42 94-94 94S34 180 34 128 76 34 128 34z" fill="none" stroke="url(#g2)" stroke-width="10" stroke-linecap="round" stroke-dasharray="40 22">
        <animate attributeName="stroke-dashoffset" values="0;124" dur="2.4s" repeatCount="indefinite"/>
      </path>
    </g>
    <g>
      <animateTransform attributeName="transform" type="translate" values="0 -2;0 2;0 -2" dur="2.1s" repeatCount="indefinite"/>
      <circle cx="128" cy="136" r="64" fill="#ffffff" opacity="0.92"/>
      <path d="M92 142c10 12 22 18 36 18s26-6 36-18" fill="none" stroke="#a95608" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="106" cy="126" r="8" fill="#1f2329" opacity="0.78"/>
      <circle cx="150" cy="126" r="8" fill="#1f2329" opacity="0.78"/>
      <circle cx="106" cy="123" r="3" fill="#ffffff" opacity="0.9"/>
      <circle cx="150" cy="123" r="3" fill="#ffffff" opacity="0.9"/>
      <path d="M84 118c8-12 18-18 30-18" fill="none" stroke="#a95608" stroke-width="8" stroke-linecap="round" opacity="0.72"/>
      <path d="M172 118c-8-12-18-18-30-18" fill="none" stroke="#a95608" stroke-width="8" stroke-linecap="round" opacity="0.72"/>
    </g>
  </g>
</svg>`
				return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
			},
			onHeroLogoError() {
				this.heroLogoSrc = this.heroLogoFallback
			},
			newDataStatus(val, num) {
				this.isFooter = !!val
				this.showBar = !!val
				this.pdHeight = num || 0
			},
			noop() {
			},
			useSuggestion(s) {
				this.draft = s
				this.send()
			},
			startWithSuggestion(s) {
				this.showIntro = false
				this.draft = s
				this.send()
			},
			startChat() {
				this.showIntro = false
				this.scrollToBottom()
			},
			goAgent(card) {
				const agentId = Number(card && (card.agentId !== undefined ? card.agentId : card.id)) || 0
				if (!agentId) return
				getAgentAccess({ agent_id: agentId }).then((res) => {
					const d = res && res.data ? res.data : {}
					if (d.unlocked) {
						uni.navigateTo({
							url: `/pages/ai/chat?agentId=${encodeURIComponent(agentId)}&title=${encodeURIComponent(card.agentName || card.title || '对话')}`
						})
						return
					}
					const productId = Number(d.product_id || card.productId) || 0
					if (!productId) {
						uni.showToast({ title: '未配置购买商品', icon: 'none' })
						return
					}
					uni.navigateTo({
						url: `/pages/goods_details/index?id=${encodeURIComponent(productId)}&agent_id=${encodeURIComponent(agentId)}&agent_title=${encodeURIComponent(card.agentName || card.title || '')}`
					})
				}).catch((err) => {
					const msg = err && err.msg ? err.msg : err
					if (String(msg || '').includes('请先登录')) {
						toLogin()
						return
					}
					uni.showToast({ title: typeof msg === 'string' ? msg : '操作失败', icon: 'none' })
				})
			},
			goAgentMatrix() {
				uni.navigateTo({
					url: `/pages/ai/agents`
				})
			},
			chooseDisambig(msgIndex, opt) {
				if (this.sending) return
				if (!opt || !opt.name) return
				const idx = Number(msgIndex)
				if (Number.isFinite(idx) && idx >= 0 && this.messages[idx]) {
					this.messages[idx].options = []
				}
				this.saveChatState()
				this.showIntro = false
				this.draft = `我说的沟通是${opt.name}`
				this.send()
			},
			homeChatNonStream(message, round, botMsgIndex) {
				return new Promise((resolve) => {
					uni.request({
						url: HTTP_REQUEST_URL + '/api/ai/home_chat',
						method: 'POST',
						header: {
							'Content-Type': 'application/json'
						},
						data: {
							message,
							session_id: this.sessionId,
							stream: 0,
							round,
							recent_recommended_id: this.lastRecommendedId
						},
						success: (res) => {
							const payload = res && res.data ? res.data : null
							const data = payload && payload.data ? payload.data : null
							const reply = data && data.reply ? String(data.reply) : ''
							if (reply) {
								this.messages[botMsgIndex].text = reply
							} else if (payload && payload.msg) {
								this.messages[botMsgIndex].text = String(payload.msg)
							}
						},
						complete: () => resolve(true),
						fail: () => resolve(false)
					})
				})
			},
			scrollToBottom() {
				this.$nextTick(() => {
					this.scrollTop = 999999
				})
			},
			send() {
				if (!this.draftTrim || this.sending) return
				if (!this.homeAgentEnabled) {
					uni.showToast({
						title: '助手暂未启用',
						icon: 'none'
					})
					return
				}
				this.showIntro = false
				const text = this.draftTrim
				this.draft = ''

				this.messages.push({
					id: uid(),
					role: 'user',
					text
				})
				this.saveChatState()
				this.sending = true
				this.scrollToBottom()

				const currentTurn = this.turnCount + 1
				const botMsgIndex = this.messages.length
				this.messages.push({
					id: uid(),
					role: 'bot',
					text: ''
				})
				this.saveChatState()

				let header = Object.assign({}, HEADER)
				header['Content-Type'] = 'application/json'

				let pendingChunk = ''
				const processChunk = (chunk, flush = false) => {
					pendingChunk += chunk
					const lines = pendingChunk.split('\n')
					pendingChunk = lines.pop() || ''

					const handleLine = (line) => {
						line = (line || '').trim()
						if (!line) return
						if (line === 'data: [DONE]' || line === '[DONE]') return
						if (line.indexOf('data: ') !== 0) return
						const dataStr = line.slice(6)
						if (dataStr === '[DONE]') return
						let data = null
						try {
							data = JSON.parse(dataStr)
						} catch (e) {
							return
						}
						if (data && data.error) {
							this.messages[botMsgIndex].text = data.error
							return
						}
						if (data && data.content) {
							this.messages[botMsgIndex].text += data.content
							this.scrollToBottom()
						}
					}

					for (let line of lines) handleLine(line)
					if (flush && pendingChunk.trim()) {
						handleLine(pendingChunk)
						pendingChunk = ''
					}
				}

				// #ifdef H5
				fetch(HTTP_REQUEST_URL + '/api/ai/home_chat', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						message: text,
						session_id: this.sessionId,
						stream: 1,
						round: currentTurn,
						recent_recommended_id: this.lastRecommendedId
					})
				}).then(response => {
					const reader = response.body.getReader()
					const decoder = new TextDecoder()
					const read = () => {
						reader.read().then(({
							done,
							value
						}) => {
							if (done) {
								processChunk('', true)
								this.sending = false
								return
							}
							const chunk = decoder.decode(value)
							processChunk(chunk)
							read()
						})
					}
					read()
				}).catch((err) => {
					this.messages[botMsgIndex].text = '发送失败: ' + ((err && err.message) || '网络错误')
					this.sending = false
				}).finally(() => {
					this.replyCount += 1
					this.turnCount = currentTurn
					this.attachRecommendCards(botMsgIndex).then(() => {})
					this.scrollToBottom()
				})
				// #endif

				// #ifndef H5
				this.requestTask = uni.request({
					url: HTTP_REQUEST_URL + '/api/ai/home_chat',
					method: 'POST',
					header: header,
					timeout: 120000,
					data: {
						message: text,
						session_id: this.sessionId,
						stream: 1,
						round: currentTurn,
						recent_recommended_id: this.lastRecommendedId
					},
					enableChunked: true,
					responseType: 'text',
					success: (res) => {
						if (res && res.data) {
							if (typeof res.data === 'string') {
								processChunk(res.data, true)
							} else {
								processChunk(decodeUtf8(res.data), true)
							}
						} else {
							processChunk('', true)
						}
					},
					fail: (err) => {
						const errMsg = (err && err.errMsg) ? String(err.errMsg) : '网络错误'
						this.messages[botMsgIndex].text = '发送失败: ' + errMsg
					},
					complete: () => {
						processChunk('', true)
						this.sending = false
						this.replyCount += 1
						this.turnCount = currentTurn
						const hasStreamText = !!(this.messages[botMsgIndex] && this.messages[botMsgIndex].text)
						const finalize = () => {
							this.attachRecommendCards(botMsgIndex).then(() => {})
							this.scrollToBottom()
						}
						if (!hasStreamText) {
							this.homeChatNonStream(text, currentTurn, botMsgIndex).then(() => {
								if (!this.messages[botMsgIndex].text) {
									this.messages[botMsgIndex].text = '未收到回复，请检查小程序请求域名、网络或服务日志'
								}
							}).finally(() => {
								finalize()
							})
						} else {
							finalize()
						}
					}
				})
				if (this.requestTask && this.requestTask.onChunkReceived) {
					this.requestTask.onChunkReceived((res) => {
						let chunk = ''
						if (typeof res.data === 'string') {
							chunk = res.data
						} else {
							chunk = decodeUtf8(res.data)
						}
						processChunk(chunk)
					})
				}
				// #endif
			}
		},
		//#ifdef MP
		onShareAppMessage() {
			const title = (this.shareInfo && this.shareInfo.title) || '小圆为你服务'
			const agentId = this.agentId || 'hyqz_default'
			const imageUrl = (this.shareInfo && this.shareInfo.img) || this.shareCover || this.heroLogoFallback
			return {
				title,
				path: `/pages/ai/index?agentId=${agentId}`,
				imageUrl,
				desc: (this.shareInfo && this.shareInfo.synopsis) || ''
			}
		},
		onShareTimeline() {
			const title = (this.shareInfo && this.shareInfo.title) || '小圆为你服务'
			const agentId = this.agentId || 'hyqz_default'
			const imageUrl = (this.shareInfo && this.shareInfo.img) || this.shareCover || this.heroLogoFallback
			return {
				title,
				query: {
					agentId
				},
				imageUrl
			}
		}
		//#endif
	}
</script>

<style lang="scss" scoped>
	.page {
		position: relative;
		min-height: 100vh;
		background: linear-gradient(180deg, rgba(241, 165, 92, 0.52) 0%, rgba(249, 233, 200, 0.96) 62%, #fff7ef 100%);
	}

	.pageFx {
		position: fixed;
		left: 0;
		top: 0;
		width: 100vw;
		height: 100vh;
		pointer-events: none;
		z-index: 0;
		background:
			radial-gradient(circle at 22% 14%, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0) 58%),
			radial-gradient(circle at 84% 26%, rgba(241, 165, 92, 0.18) 0%, rgba(241, 165, 92, 0) 62%),
			radial-gradient(circle at 46% 92%, rgba(255, 255, 255, 0.62) 0%, rgba(255, 255, 255, 0) 54%);
		opacity: 1;
	}

	.chat {
		position: relative;
		z-index: 1;
	}

	.chatInner {
		padding: 28rpx 24rpx 28rpx;
	}

	.intro {
		padding: 8rpx 0 18rpx;
	}

	.heroCard {
		padding: 26rpx 22rpx 22rpx;
		border-radius: 30rpx;
		background: rgba(255, 255, 255, 0.86);
		border: 1rpx solid rgba(255, 255, 255, 0.55);
		box-shadow: 0rpx 18rpx 48rpx rgba(31, 35, 41, 0.14);
		backdrop-filter: blur(12px);
	}

	.heroHead {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 18rpx;
	}

	.heroText {
		flex: 1;
		min-width: 0;
	}

	.heroTitle {
		display: block;
		font-size: 34rpx;
		font-weight: 900;
		color: rgba(31, 35, 41, 0.92);
	}

	.heroSub {
		margin-top: 10rpx;
		display: block;
		font-size: 24rpx;
		line-height: 36rpx;
		color: rgba(31, 35, 41, 0.64);
	}

	.heroMascot {
		position: relative;
		width: 124rpx;
		height: 124rpx;
		flex: none;
	}

	.mascotGlow {
		position: absolute;
		left: -26rpx;
		top: -26rpx;
		width: 176rpx;
		height: 176rpx;
		border-radius: 50%;
		background: radial-gradient(circle at 40% 40%, rgba(241, 165, 92, 0.30) 0%, rgba(241, 165, 92, 0.08) 45%, rgba(241, 165, 92, 0) 72%);
		filter: blur(1px);
	}

	.heroLogo {
		position: absolute;
		left: 0;
		top: 0;
		width: 124rpx;
		height: 124rpx;
		border-radius: 50%;
		border: 2rpx solid rgba(255, 255, 255, 0.85);
		box-shadow: 0rpx 10rpx 28rpx rgba(31, 35, 41, 0.18);
		background: #fff;
	}

	.qaChips {
		margin-top: 18rpx;
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
	}

	.qaChip {
		padding: 12rpx 16rpx;
		border-radius: 999rpx;
		background: rgba(241, 165, 92, 0.08);
		border: 1rpx solid rgba(241, 165, 92, 0.16);
	}

	.qaChipText {
		font-size: 26rpx;
		color: #a95608;
		font-weight: 800;
	}

	.msg {
		display: flex;
		flex-direction: column;
		margin-bottom: 16rpx;
	}

	.msg.user {
		align-items: flex-end;
	}

	.msg.bot {
		align-items: flex-start;
	}

	.bubble {
		max-width: 620rpx;
		padding: 18rpx 18rpx;
		border-radius: 18rpx;
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
	}

	.msg.user .bubble {
		background: linear-gradient(135deg, var(--view-main-start) 0%, var(--view-main-over) 100%);
		border-top-right-radius: 6rpx;
	}

	.msg.bot .bubble {
		background: #fff;
		border-top-left-radius: 6rpx;
	}

	.bubbleText {
		font-size: 26rpx;
		line-height: 40rpx;
		color: rgba(31, 35, 41, 0.92);
	}

	.msg.user .bubbleText {
		color: rgba(255, 255, 255, 0.96);
	}

	.cards {
		margin-top: 12rpx;
		display: flex;
		flex-direction: column;
		gap: 12rpx;
	}

	.matrixCta {
		margin-top: 12rpx;
		width: 640rpx;
	}

	.matrixBtn {
		padding: 18rpx 20rpx;
		border-radius: 18rpx;
		background: rgba(255, 255, 255, 0.88);
		border: 1rpx dashed rgba(169, 86, 8, 0.35);
	}

	.matrixBtnText {
		font-size: 24rpx;
		color: rgba(169, 86, 8, 0.95);
		line-height: 34rpx;
	}

	.disambig {
		margin-top: 12rpx;
		width: 640rpx;
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
	}

	.disambigBtn {
		padding: 14rpx 18rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.88);
		border: 1rpx solid rgba(241, 165, 92, 0.35);
	}

	.disambigBtnText {
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.86);
		line-height: 34rpx;
	}

	.card {
		width: 640rpx;
		display: flex;
		background: #fff;
		border-radius: 18rpx;
		overflow: hidden;
		border: 1rpx solid rgba(0, 0, 0, 0.06);
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
	}

	.cardCover {
		width: 190rpx;
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.18) 0%, rgba(241, 165, 92, 0.07) 100%);
		position: relative;
	}

	.cardImg {
		position: absolute;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
	}

	.cardLabel {
		position: absolute;
		left: 14rpx;
		top: 14rpx;
		padding: 8rpx 14rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.9);
	}

	.cardLabelText {
		font-size: 22rpx;
		color: #a95608;
	}

	.cardBody {
		flex: 1;
		padding: 18rpx 18rpx 16rpx;
	}

	.cardTitle {
		display: block;
		font-size: 28rpx;
		font-weight: 800;
		color: #1f2329;
	}

	.cardDesc {
		margin-top: 6rpx;
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.62);
	}

	.cardFoot {
		margin-top: 14rpx;
		display: flex;
		align-items: center;
		justify-content: flex-end;
	}

	.cardBtn {
		padding: 12rpx 16rpx;
		border-radius: 14rpx;
		border: 1rpx solid rgba(241, 165, 92, 0.28);
		background: rgba(241, 165, 92, 0.07);
	}

	.cardBtnText {
		font-size: 24rpx;
		color: #a95608;
		font-weight: 700;
	}

	.typing {
		display: flex;
		gap: 10rpx;
		align-items: center;
		justify-content: center;
		padding: 14rpx 0 6rpx;
	}

	.typingDot {
		width: 10rpx;
		height: 10rpx;
		border-radius: 50%;
		background: rgba(31, 35, 41, 0.35);
		animation: pulse 1s infinite ease-in-out;
	}

	.typingDot:nth-child(2) {
		animation-delay: 0.15s;
	}

	.typingDot:nth-child(3) {
		animation-delay: 0.3s;
	}

	@keyframes pulse {
		0% {
			opacity: 0.25;
			transform: translateY(0);
		}

		50% {
			opacity: 1;
			transform: translateY(-4rpx);
		}

		100% {
			opacity: 0.25;
			transform: translateY(0);
		}
	}

	.composer {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		padding: 16rpx 18rpx 0;
		background: transparent;
		z-index: 2;
	}

	.composerInner {
		display: flex;
		align-items: center;
		gap: 12rpx;
		padding: 10rpx 10rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.88);
		backdrop-filter: blur(12px);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
		box-shadow: 0rpx 18rpx 46rpx rgba(31, 35, 41, 0.16);
	}

	.leftBadge {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.20) 0%, rgba(241, 165, 92, 0.07) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.leftIcon {
		font-size: 34rpx;
		color: rgba(241, 165, 92, 0.92);
	}

	.input {
		flex: 1;
		height: 72rpx;
		padding: 0 12rpx;
		border-radius: 999rpx;
		background: transparent;
		font-size: 26rpx;
		color: #1f2329;
	}

	.send {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		background: linear-gradient(135deg, var(--view-main-start) 0%, var(--view-main-over) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.send.disabled {
		opacity: 0.5;
	}

	.sendIcon {
		font-size: 32rpx;
		color: rgba(255, 255, 255, 0.98);
	}

	.safePad {
		height: env(safe-area-inset-bottom);
	}
	
	.aiDisclaimer {
		margin-top: 10rpx;
		text-align: center;
	}
	
	.aiDisclaimerText {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.45);
	}
</style>
