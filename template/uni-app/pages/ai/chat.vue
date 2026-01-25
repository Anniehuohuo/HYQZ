<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<scroll-view class="chat" :style="{ height: chatHeight }" scroll-y="true" :scroll-top="scrollTop" @scrolltolower="noop" :lower-threshold="60">
			<view class="chatInner">
				<view class="msg" v-for="m in messages" :key="m.id" :class="m.role === 'user' ? 'user' : 'bot'">
					<view class="bubble">
						<text class="bubbleText">{{ m.text }}</text>
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
				<input class="input" v-model="draft" :adjust-position="true" confirm-type="send" placeholder="描述你的困扰或场景，我来帮你拆解" @confirm="send" />
				<view class="send" :class="{ disabled: !draftTrim }" @click="send">
					<text class="sendText">发送</text>
				</view>
			</view>
			<view class="hintRow">
				<view class="hintChip" v-for="(s, i) in suggestions" :key="i" @click="useSuggestion(s)">
					<text class="hintChipText">{{ s }}</text>
				</view>
			</view>
			<view class="safePad"></view>
		</view>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import {
		aiChat,
		getChatHistory,
		getRecentSession
	} from '@/api/ai.js'
	import {
		HTTP_REQUEST_URL,
		HEADER,
		TOKENNAME
	} from '@/config/app.js'
	import store from '@/store'

	function uid() {
		return `${Date.now()}_${Math.random().toString(16).slice(2)}`
	}

	export default {
		mixins: [colors],
		data() {
			return {
				agentId: '',
				title: '',
				conversationId: '',
				sessionId: 0,
				draft: '',
				sending: false,
				scrollTop: 0,
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立'],
				requestTask: null
			}
		},
		computed: {
			pagePad() {
				return {}
			},
			footerBottom() {
				return '0rpx'
			},
			chatHeight() {
				return 'calc(100vh - 260rpx)'
			},
			draftTrim() {
				return (this.draft || '').trim()
			}
		},
		onLoad(options) {
			this.agentId = options.agentId || 'hyqz_default'
			this.title = options.title || '慧圆'
			uni.setNavigationBarTitle({
				title: this.title || '对话'
			})
			const prefill = options.prefill ? decodeURIComponent(options.prefill) : ''
			
			// Initial welcome message
			this.messages = [{
				id: uid(),
				role: 'bot',
				text: '把发生的场景描述给我：谁、在什么时间、说了什么、你怎么回应的？我会给你一套可执行的沟通步骤。'
			}]

			if (this.agentId && this.agentId !== 'hyqz_default') {
				getRecentSession({
					agent_id: this.agentId
				}).then(res => {
					if (res.data && res.data.id) {
						this.sessionId = res.data.id
						this.loadHistory()
					}
				})
			}

			if (prefill) {
				this.draft = prefill
				this.send()
			}
		},
		methods: {
			loadHistory() {
				if (!this.sessionId) return
				getChatHistory({
					session_id: this.sessionId,
					page: 1,
					limit: 50
				}).then(res => {
					const list = res.data || []
					if (list.length > 0) {
						const msgs = list.map(m => ({
							id: m.id,
							role: m.role === 'user' ? 'user' : 'bot',
							text: m.content
						}))
						// Replace initial welcome message with history
						this.messages = msgs
						this.scrollToBottom()
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
					text: ''
				})
				const botMsgIndex = this.messages.length - 1
				let pendingChunk = ''

				if (!token) {
					this.messages[botMsgIndex].text = '请先登录'
					this.sending = false
					this.scrollToBottom()
					return
				}

				if (!this.agentId || this.agentId === 'hyqz_default') {
					this.messages[botMsgIndex].text = '请先选择一个智能体'
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
							return
						}
						try {
							const data = JSON.parse(dataStr)
							if (data.session_id) this.sessionId = data.session_id
							if (data.content) {
								this.messages[botMsgIndex].text += data.content
								this.scrollToBottom()
							}
							if (data.error) {
								this.messages[botMsgIndex].text = data.error
								this.scrollToBottom()
							}
						} catch (e) {
							if (dataStr) {
								this.messages[botMsgIndex].text += dataStr
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
								this.sending = false
								this.scrollToBottom()
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
					this.sending = false
				})
				// #endif

				// #ifndef H5
				this.requestTask = uni.request({
					url: HTTP_REQUEST_URL + '/api/ai/chat',
					method: 'POST',
					header: header,
					data: {
						agent_id: this.agentId,
						session_id: this.sessionId,
						message: text,
						stream: true
					},
					enableChunked: true,
					responseType: 'text',
					success: (res) => {
						if (res && res.data) {
							if (typeof res.data === 'string') {
								processChunk(res.data, true)
							} else {
								try {
									processChunk(new TextDecoder('utf-8').decode(new Uint8Array(res.data)), true)
								} catch (e) {}
							}
						} else {
							processChunk('', true)
						}
					},
					fail: (err) => {
						this.messages[botMsgIndex].text = '发送失败: ' + (err.errMsg || '网络错误')
					},
					complete: () => {
						processChunk('', true)
						this.sending = false
						this.scrollToBottom()
					}
				})
				if (this.requestTask && this.requestTask.onChunkReceived) {
					this.requestTask.onChunkReceived((res) => {
						let chunk = ''
						if (typeof res.data === 'string') {
							chunk = res.data
						} else {
							// For ArrayBuffer
							// Note: TextDecoder might not exist in some environments (e.g. older Android Webview in App)
							// But basic support is usually there or polyfilled in uni-app.
							// If not, we might need a simple utf8 decode function.
							try {
								chunk = new TextDecoder('utf-8').decode(new Uint8Array(res.data))
							} catch (e) {
								console.error('TextDecoder not supported', e)
							}
						}
						processChunk(chunk)
					})
				}
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

	.chatInner {
		padding: 18rpx 24rpx 24rpx;
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
		background: #fff;
		border-top-left-radius: 6rpx;
	}

	.bubbleText {
		font-size: 26rpx;
		line-height: 38rpx;
		color: #1f2329;
	}

	.msg.user .bubbleText {
		color: rgba(31, 35, 41, 0.92);
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

	.safePad {
		height: env(safe-area-inset-bottom);
	}
</style>
