<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<scroll-view class="chat" scroll-y="true" :scroll-top="scrollTop" @scrolltolower="noop" :lower-threshold="60">
			<view class="chatInner">
				<view class="intro" v-if="showIntro">
					<view class="introTop">
						<image v-if="intro.avatar" class="introAvatar" :src="intro.avatar" mode="aspectFill"></image>
						<view v-else class="introAvatar placeholder"></view>
						<view class="introTitle">{{ title }}</view>
						<view class="introSub">{{ intro.description }}</view>
					</view>
					<view class="introQuestions">
						<view class="introQ" v-for="(s, i) in intro.suggestions" :key="i" @click="useSuggestion(s)">
							<text class="introQText">{{ s }}</text>
						</view>
					</view>
					<view class="introWelcome" v-if="intro.welcome">
						<text class="introWelcomeText">{{ intro.welcome }}</text>
					</view>
				</view>
				<view class="msg" v-for="m in messages" :key="m.id" :class="m.role === 'user' ? 'user' : 'bot'">
					<view class="bubble">
						<text v-if="m.role === 'user'" class="bubbleText">{{ m.text }}</text>
						<view v-else class="replyCard">
							<view class="replyCardHead">
								<view class="replyCardDot"></view>
								<text class="replyCardTitle">{{ title || 'AI助手' }}</text>
							</view>
							<view class="replyCardBody">
								<view class="fmt">
									<BlocksRenderer :blocks="m.blocks" :fallback-html="m.html" :fallback-text="m.text" :message-id="m.id"></BlocksRenderer>
								</view>
							</view>
						</view>
					</view>
				</view>

				<view class="typing" v-if="sending">
					<view class="typingDot"></view>
					<view class="typingDot"></view>
					<view class="typingDot"></view>
				</view>
			</view>
		</scroll-view>

		<view class="composer" :style="{ bottom: footerBottom }">
			<view class="composerInner">
				<view class="leftBadge" :class="{ disabled: !sessionId }" @click="clearHistory()">
					<text class="iconfont icon-shanchu31 leftIcon"></text>
				</view>
				<input class="input" v-model="draft" :adjust-position="true" confirm-type="send" placeholder="描述你的困扰或场景，我来帮你拆解" @confirm="send" />
				<view class="send" :class="{ disabled: !draftTrim }" @click="send">
					<text class="sendText">发送</text>
				</view>
			</view>
			<view class="hintRow" v-if="!showIntro">
				<view class="hintChip" v-for="(s, i) in suggestions" :key="i" @click="useSuggestion(s)">
					<text class="hintChipText">{{ s }}</text>
				</view>
			</view>
			<view class="aiDisclaimer">
				<text class="aiDisclaimerText">本服务为AI生成内容，结果仅供参考。</text>
			</view>
			<view class="safePad"></view>
		</view>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import BlocksRenderer from '@/components/aiBlocks/BlocksRenderer.vue'
	import {
		aiChat,
		getChatHistory,
		getRecentSession,
		getAgentAccess,
		clearChatHistory
	} from '@/api/ai.js'
	import {
		HTTP_REQUEST_URL,
		HEADER,
		TOKENNAME
	} from '@/config/app.js'
	import store from '@/store'
	import { toLogin } from '@/libs/login.js'
	import md from '@/utils/markdown.js'

	function uid() {
		return `${Date.now()}_${Math.random().toString(16).slice(2)}`
	}

	function safeDecode(v) {
		if (v === undefined || v === null) return ''
		const s = String(v)
		try {
			return decodeURIComponent(s)
		} catch (e) {
			return s
		}
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

	function sessionStorageKey(agentId) {
		return `AI_AGENT_SESSION_${String(agentId || '')}`
	}

	export default {
		mixins: [colors],
		components: { BlocksRenderer },
		data() {
			return {
				agentId: '',
				title: '',
				conversationId: '',
				sessionId: 0,
				sessionNonce: 0,
				showIntro: true,
				intro: {
					avatar: '',
					description: '',
					welcome: '',
					suggestions: []
				},
				draft: '',
				sending: false,
				scrollTop: 0,
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立'],
				requestTask: null,
				perfStartAt: 0,
				perfFirstByteAt: 0
			}
		},
		computed: {
			pagePad() {
				return {}
			},
			footerBottom() {
				return '0rpx'
			},
			draftTrim() {
				return (this.draft || '').trim()
			}
		},
		onLoad(options) {
			const opt = options || {}
			const rawAgentId = opt.agentId || opt.agent_id || opt.id || opt.agentID || ''
			this.agentId = rawAgentId !== '' ? rawAgentId : 'hyqz_default'
			this.title = safeDecode(opt.title) || '慧圆'
			uni.setNavigationBarTitle({
				title: this.title || '对话'
			})
			const prefill = opt.prefill ? decodeURIComponent(opt.prefill) : ''
			
			this.messages = []
			this.showIntro = true

			if (this.agentId && this.agentId !== 'hyqz_default') {
				getAgentAccess({
					agent_id: this.agentId
				}).then(res => {
					const d = res && res.data ? res.data : {}
					this.intro = {
						avatar: d.avatar || '',
						description: d.description || '',
						welcome: d.welcome || '',
						suggestions: Array.isArray(d.suggestions) ? d.suggestions : []
					}
					if (this.intro.suggestions && this.intro.suggestions.length) {
						this.suggestions = this.intro.suggestions
					}
					if (!d.unlocked) {
						const pid = Number(d.product_id) || 0
						if (pid) {
							uni.redirectTo({
								url: `/pages/goods_details/index?id=${encodeURIComponent(pid)}&agent_id=${encodeURIComponent(this.agentId)}&agent_title=${encodeURIComponent(this.title || '')}`
							})
						}
					}
				}).catch(err => {
					const msg = err && err.msg ? err.msg : err
					if (String(msg || '').includes('请先登录')) {
						toLogin()
					}
				})
				const stored = Number(uni.getStorageSync(sessionStorageKey(this.agentId)) || 0) || 0
				if (stored) {
					this.sessionId = stored
					this.loadHistory()
				} else {
					const nonce = this.sessionNonce
					getRecentSession({
						agent_id: this.agentId
					}).then(res => {
						if (this.sessionNonce !== nonce) return
						if (res.data && res.data.id) {
							this.sessionId = res.data.id
							this.persistSession()
							this.loadHistory()
						}
					})
				}
			}

			if (prefill) {
				this.draft = prefill
				this.send()
			}
		},
		onShow() {
			if (this.agentId && this.agentId !== 'hyqz_default') {
				const stored = Number(uni.getStorageSync(sessionStorageKey(this.agentId)) || 0) || 0
				if (stored && !this.sessionId) {
					this.sessionId = stored
				}
				if (this.sessionId && this.messages && this.messages.length <= 1) {
					this.loadHistory()
				}
			}
		},
		onHide() {
			if (this.requestTask && this.requestTask.abort) {
				try {
					this.requestTask.abort()
				} catch (e) {}
			}
		},
		onUnload() {
			if (this.requestTask && this.requestTask.abort) {
				try {
					this.requestTask.abort()
				} catch (e) {}
			}
		},
		methods: {
			persistSession() {
				if (!this.agentId || this.agentId === 'hyqz_default') return
				if (!this.sessionId) return
				uni.setStorageSync(sessionStorageKey(this.agentId), String(this.sessionId))
			},
			clearPersistedSession() {
				if (!this.agentId || this.agentId === 'hyqz_default') return
				uni.removeStorageSync(sessionStorageKey(this.agentId))
			},
			loadHistory() {
				if (!this.sessionId) return
				const nonce = this.sessionNonce
				const sessionId = this.sessionId
				getChatHistory({
					session_id: sessionId,
					page: 1,
					limit: 50
				}).then(res => {
					if (this.sessionNonce !== nonce) return
					if (this.sessionId !== sessionId) return
					const list = res.data || []
					if (list.length > 0) {
						const msgs = list.map(m => ({
							id: m.id,
							role: m.role === 'user' ? 'user' : 'bot',
							text: m.content,
							html: m.role === 'user' ? '' : md.markdownToHtml(m.content)
						}))
						// Replace initial welcome message with history
						this.messages = msgs
						this.showIntro = false
						this.scrollToBottom()
					}
				})
			},
			clearHistory() {
				uni.showModal({
					title: '确认清空',
					content: '将清空当前智能体的聊天记录，且无法恢复。',
					confirmText: '清空',
					cancelText: '取消',
					success: (r) => {
						if (!r || !r.confirm) return
						const prevSessionId = Number(this.sessionId) || 0
						this.sessionNonce += 1
						this.sending = false
						if (this.requestTask && this.requestTask.abort) {
							try {
								this.requestTask.abort()
							} catch (e) {}
						}
						this.sessionId = 0
						this.clearPersistedSession()
						this.messages = []
						this.showIntro = true
						this.scrollToBottom()

						clearChatHistory({
							session_id: prevSessionId,
							agent_id: this.agentId
						}).catch((err) => {
							this.$util && this.$util.Tips ? this.$util.Tips({
								title: err || '清空失败'
							}) : uni.showToast({
								title: '清空失败',
								icon: 'none'
							})
						})
					}
				})
			},
			noop() {
			},
			useSuggestion(s) {
				this.draft = s
				this.send()
			},
			goAgents() {
				uni.navigateTo({
					url: '/pages/ai/agents'
				})
			},
			scrollToBottom() {
				this.$nextTick(() => {
					this.scrollTop = 999999
				})
			},
			send() {
				if (!this.draftTrim || this.sending) return
				const text = this.draftTrim
				this.draft = ''
				this.showIntro = false
				this.perfStartAt = Date.now()
				this.perfFirstByteAt = 0
				this.messages.push({
					id: uid(),
					role: 'user',
					text
				})
				this.sending = true
				this.scrollToBottom()

				const token = store.state.app.token
				const header = { ...HEADER
				}
				header['content-type'] = 'application/json'
				if (token) header[TOKENNAME] = 'Bearer ' + token

				// Bot message placeholder
				const botMsgId = uid()
				this.messages.push({
					id: botMsgId,
					role: 'bot',
					text: '',
					html: '',
					blocks: [],
					diag: null
				})
				const botMsgIndex = this.messages.length - 1
				let pendingChunk = ''

				if (!token) {
					this.messages[botMsgIndex].text = '请先登录'
					this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
					this.sending = false
					this.scrollToBottom()
					return
				}

				if (!this.agentId || this.agentId === 'hyqz_default') {
					this.messages[botMsgIndex].text = '请先选择一个智能体'
					this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
					this.sending = false
					this.scrollToBottom()
					return
				}

				const handleLine = (rawLine) => {
					let line = (rawLine || '').trim()
					if (!line) return
					if (line.startsWith('data:')) {
						const dataStr = line.replace(/^data:\s*/, '')
						if (dataStr === '[DONE]') {
							this.sending = false
							this.persistSession()
							return
						}
						try {
							const data = JSON.parse(dataStr)
							if (data.session_id) {
								this.sessionId = data.session_id
								this.persistSession()
							}
							if (data.content) {
								if (!this.perfFirstByteAt) this.perfFirstByteAt = Date.now()
								this.messages[botMsgIndex].text += data.content
								this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
								this.scrollToBottom()
							}
							if (data.blocks && Array.isArray(data.blocks) && data.blocks.length) {
								this.messages[botMsgIndex].blocks = data.blocks
								this.scrollToBottom()
								const endAt = Date.now()
								const ttfb = this.perfFirstByteAt ? (this.perfFirstByteAt - (this.perfStartAt || this.perfFirstByteAt)) : 0
								const total = this.perfStartAt ? (endAt - this.perfStartAt) : 0
								try { console.log('[ai.render]', { ttfb, total, blocks: data.blocks.length }) } catch (e) {}
							}
							if (data.diag && typeof data.diag === 'object') {
								this.messages[botMsgIndex].diag = data.diag
								try { console.log('[ai.diag]', data.diag) } catch (e) {}
							}
							if (data.error) {
								this.messages[botMsgIndex].text = data.error
								this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
								this.scrollToBottom()
								if (String(data.error || '').includes('充值')) {
									uni.showModal({
										title: '算力不足',
										content: String(data.error || '今日免费次数已用完，请充值算力'),
										confirmText: '去充值',
										cancelText: '取消',
										success: (r) => {
											if (r && r.confirm) {
												uni.navigateTo({
													url: '/pages/users/ai_power_payment/index'
												})
											}
										}
									})
								}
							}
						} catch (e) {
							if (dataStr) {
								this.messages[botMsgIndex].text += dataStr
								this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
								this.scrollToBottom()
							}
						}
						return
					}

					if (line.startsWith('{') && line.endsWith('}')) {
						try {
							const data = JSON.parse(line)
							const msg = data.msg || data.error || data.message
							if (msg) {
								this.messages[botMsgIndex].text = msg
								this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
								this.sending = false
								this.scrollToBottom()
								if (data && data.data && data.data.need_recharge) {
									uni.showModal({
										title: '算力不足',
										content: String(msg || '今日免费次数已用完，请充值算力'),
										confirmText: '去充值',
										cancelText: '取消',
										success: (r) => {
											if (r && r.confirm) {
												uni.navigateTo({
													url: '/pages/users/ai_power_payment/index'
												})
											}
										}
									})
								}
							}
						} catch (e) {}
					}
				}

				const processChunk = (chunk, flush = false) => {
					if (chunk) pendingChunk += chunk
					const lines = pendingChunk.split(/\r?\n/)
					pendingChunk = lines.pop() || ''
					for (let line of lines) handleLine(line)
					if (flush && pendingChunk.trim()) {
						handleLine(pendingChunk)
						pendingChunk = ''
					}
				}

				// #ifdef H5
				fetch(HTTP_REQUEST_URL + '/api/ai/chat', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						[TOKENNAME]: 'Bearer ' + token
					},
					body: JSON.stringify({
						agent_id: this.agentId,
						session_id: this.sessionId,
						message: text,
						stream: true
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
				}).catch(err => {
					this.messages[botMsgIndex].text = '发送失败'
					this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
					this.sending = false
				})
				// #endif

				// #ifndef H5
				const shouldRetryNoStream = (err) => {
					const msg = String((err && err.errMsg) || err || '')
					return msg.includes('CONNECTION_RESET') || msg.includes('Failed to fetch')
				}
				const handleNonStreamResponse = (res) => {
					let payload = res && res.data ? res.data : null
					if (typeof payload === 'string') {
						try {
							payload = JSON.parse(payload)
						} catch (e) {}
					}
					if (payload && typeof payload === 'object' && Number(payload.status) === 200) {
						const d = payload.data || {}
						const reply = (d && d.reply) ? String(d.reply) : ''
						if (d && d.session_id) this.sessionId = Number(d.session_id) || this.sessionId
						if (reply) {
							this.messages[botMsgIndex].text = reply
							this.messages[botMsgIndex].html = md.markdownToHtml(reply)
						}
						return
					}
					const msg = payload && typeof payload === 'object' ? (payload.msg || payload.error || payload.message) : ''
					if (msg) {
						this.messages[botMsgIndex].text = String(msg)
						this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
					}
				}

				let retried = false
				const finalize = () => {
					processChunk('', true)
					if (!this.messages[botMsgIndex].text) {
						this.messages[botMsgIndex].text = '未收到回复，请检查小程序请求域名、网络或服务日志'
						this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
					}
					this.sending = false
					this.persistSession()
					this.scrollToBottom()
				}

				const startRequest = (useStream) => {
					this.requestTask = uni.request({
						url: HTTP_REQUEST_URL + '/api/ai/chat',
						method: 'POST',
						header: header,
						data: {
							agent_id: this.agentId,
							session_id: this.sessionId,
							message: text,
							stream: useStream ? true : false,
							format: 'blocks'
						},
						enableChunked: useStream ? true : undefined,
						responseType: useStream ? 'text' : undefined,
						dataType: useStream ? undefined : 'json',
						success: (res) => {
							if (!useStream) {
								handleNonStreamResponse(res)
								return
							}
							if (res && res.data) {
								if (typeof res.data === 'string') {
									processChunk(res.data, true)
								} else if (res.data && typeof res.data === 'object') {
									handleNonStreamResponse(res)
								} else {
									processChunk(decodeUtf8(res.data), true)
								}
							} else {
								processChunk('', true)
							}
						},
						fail: (err) => {
							if (useStream && !retried && shouldRetryNoStream(err)) {
								retried = true
								startRequest(false)
								return
							}
							this.messages[botMsgIndex].text = '发送失败: ' + (err && err.errMsg ? err.errMsg : '网络错误')
							this.messages[botMsgIndex].html = md.markdownToHtml(this.messages[botMsgIndex].text)
						},
						complete: () => {
							if (useStream && retried) return
							finalize()
						}
					})

					if (useStream && this.requestTask && this.requestTask.onChunkReceived) {
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
				}

				startRequest(true)
				// #endif
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page {
		min-height: 100vh;
		background: #f4f6f8;
	}
	
	.chat {
		position: fixed;
		left: 0;
		right: 0;
		top: 0;
		bottom: 260rpx;
	}

	.chatInner {
		padding: 18rpx 24rpx 360rpx;
	}
	
	.intro {
		background: #fff;
		border-radius: 20rpx;
		padding: 26rpx 22rpx;
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
		margin-bottom: 18rpx;
	}
	
	.introTop {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
	
	.introAvatar {
		width: 116rpx;
		height: 116rpx;
		border-radius: 58rpx;
		background: #f6f7f8;
		margin-bottom: 18rpx;
	}
	
	.introAvatar.placeholder {
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.30), rgba(255, 152, 0, 0.10));
	}
	
	.introTitle {
		font-size: 34rpx;
		line-height: 46rpx;
		font-weight: 800;
		color: #1f2329;
		text-align: center;
	}
	
	.introSub {
		margin-top: 10rpx;
		font-size: 24rpx;
		line-height: 36rpx;
		color: rgba(31, 35, 41, 0.62);
		text-align: center;
	}
	
	.introQuestions {
		margin-top: 20rpx;
		display: flex;
		flex-direction: column;
		gap: 14rpx;
	}
	
	.introQ {
		padding: 18rpx 18rpx;
		border-radius: 16rpx;
		background: rgba(246, 247, 248, 1);
		border: 1rpx solid rgba(31, 35, 41, 0.08);
	}
	
	.introQText {
		font-size: 26rpx;
		line-height: 38rpx;
		color: rgba(31, 35, 41, 0.92);
	}
	
	.introWelcome {
		margin-top: 18rpx;
		text-align: center;
	}
	
	.introWelcomeText {
		font-size: 24rpx;
		line-height: 36rpx;
		color: rgba(31, 35, 41, 0.65);
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
		background: rgba(241, 165, 92, 0.24);
		border: 1rpx solid rgba(241, 165, 92, 0.18);
		border-top-right-radius: 6rpx;
	}

	.msg.bot .bubble {
		background: transparent;
		border-top-left-radius: 6rpx;
		padding: 0;
		box-shadow: none;
	}

	.replyCard {
		width: 100%;
		min-width: 360rpx;
		max-width: 660rpx;
		background: #fff;
		border-radius: 20rpx;
		border: 1rpx solid rgba(31, 35, 41, 0.08);
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
		overflow: hidden;
	}

	.replyCardHead {
		display: flex;
		align-items: center;
		gap: 10rpx;
		padding: 14rpx 16rpx;
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.16), rgba(241, 165, 92, 0.06));
		border-bottom: 1rpx solid rgba(31, 35, 41, 0.06);
	}

	.replyCardDot {
		width: 12rpx;
		height: 12rpx;
		border-radius: 50%;
		background: #f1a55c;
		flex: none;
	}

	.replyCardTitle {
		font-size: 22rpx;
		line-height: 30rpx;
		font-weight: 700;
		color: rgba(31, 35, 41, 0.72);
	}

	.replyCardBody {
		padding: 16rpx 16rpx 14rpx;
	}

	.bubbleText {
		font-size: 26rpx;
		line-height: 38rpx;
		color: #1f2329;
	}

	.msg.user .bubbleText {
		color: rgba(31, 35, 41, 0.92);
	}

	.fmt {
		font-size: 26rpx;
		line-height: 38rpx;
		color: #1f2329;
		word-break: break-word;
		::v-deep p {
			margin: 0 0 10rpx 0;
		}
		::v-deep h1 {
			font-size: 34rpx;
			line-height: 48rpx;
			font-weight: 800;
			margin: 8rpx 0 12rpx 0;
		}
		::v-deep h2 {
			font-size: 32rpx;
			line-height: 46rpx;
			font-weight: 800;
			margin: 8rpx 0 12rpx 0;
		}
		::v-deep h3 {
			font-size: 30rpx;
			line-height: 44rpx;
			font-weight: 800;
			margin: 8rpx 0 10rpx 0;
		}
		::v-deep h4 {
			font-size: 28rpx;
			line-height: 42rpx;
			font-weight: 800;
			margin: 8rpx 0 10rpx 0;
		}
		::v-deep h5 {
			font-size: 26rpx;
			line-height: 40rpx;
			font-weight: 800;
			margin: 8rpx 0 8rpx 0;
		}
		::v-deep h6 {
			font-size: 24rpx;
			line-height: 38rpx;
			font-weight: 800;
			margin: 8rpx 0 8rpx 0;
		}
		::v-deep ul {
			margin: 0 0 10rpx 0;
			padding-left: 34rpx;
		}
		::v-deep ol {
			margin: 0 0 10rpx 0;
			padding-left: 34rpx;
		}
		::v-deep li {
			margin: 4rpx 0;
		}
		::v-deep blockquote {
			margin: 0 0 10rpx 0;
			padding: 12rpx 14rpx;
			border-left: 6rpx solid rgba(241, 165, 92, 0.65);
			background: rgba(241, 165, 92, 0.10);
			border-radius: 12rpx;
			color: rgba(31, 35, 41, 0.86);
		}
		::v-deep hr {
			border: 0;
			border-top: 1rpx solid rgba(31, 35, 41, 0.10);
			margin: 12rpx 0;
		}
		::v-deep pre {
			margin: 0 0 10rpx 0;
			padding: 14rpx 14rpx;
			border-radius: 12rpx;
			background: #f6f7f8;
			overflow-x: auto;
		}
		::v-deep code {
			padding: 2rpx 8rpx;
			border-radius: 8rpx;
			background: #f6f7f8;
			font-size: 24rpx;
			font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		}
		::v-deep pre code {
			padding: 0;
			background: transparent;
			font-size: 24rpx;
		}
	}

	.typing {
		margin-top: 10rpx;
		display: flex;
		gap: 10rpx;
		align-items: center;
		padding: 16rpx 16rpx;
		width: 160rpx;
		border-radius: 18rpx;
		background: #fff;
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
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
		background: rgba(255, 255, 255, 0.92);
		backdrop-filter: blur(10px);
		border-top: 1rpx solid rgba(0, 0, 0, 0.06);
		padding: 14rpx 18rpx 0;
	}

	.composerInner {
		display: flex;
		align-items: center;
		gap: 12rpx;
	}

	.leftBadge {
		width: 84rpx;
		height: 84rpx;
		border-radius: 18rpx;
		background: rgba(241, 165, 92, 0.12);
		border: 1rpx solid rgba(241, 165, 92, 0.20);
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.leftBadge.disabled {
		opacity: 0.35;
	}

	.leftIcon {
		font-size: 34rpx;
		color: rgba(241, 165, 92, 0.92);
	}

	.input {
		flex: 1;
		height: 84rpx;
		padding: 0 18rpx;
		border-radius: 18rpx;
		background: #f3f4f6;
		font-size: 26rpx;
		color: #1f2329;
	}

	.send {
		height: 84rpx;
		padding: 0 18rpx;
		border-radius: 18rpx;
		background: linear-gradient(135deg, var(--view-main-start) 0%, var(--view-main-over) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.send.disabled {
		opacity: 0.5;
	}

	.sendText {
		font-size: 26rpx;
		font-weight: 900;
		color: #fff;
	}

	.hintRow {
		margin-top: 12rpx;
		display: flex;
		gap: 12rpx;
		overflow-x: auto;
		white-space: nowrap;
		padding-bottom: 12rpx;
	}

	.hintChip {
		flex: none;
		padding: 12rpx 16rpx;
		border-radius: 999rpx;
		background: rgba(241, 165, 92, 0.08);
		border: 1rpx solid rgba(241, 165, 92, 0.16);
	}

	.hintChipText {
		font-size: 24rpx;
		color: #a95608;
	}
	
	.aiDisclaimer {
		padding-bottom: 12rpx;
	}
	
	.aiDisclaimerText {
		font-size: 22rpx;
		line-height: 32rpx;
		color: rgba(31, 35, 41, 0.45);
	}

	.safePad {
		height: env(safe-area-inset-bottom);
	}
</style>
