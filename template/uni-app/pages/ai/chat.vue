<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<!-- #ifdef MP || APP-PLUS -->
		<view class="sys-head">
			<view class="sys-bar" :style="{ height: sysHeight }"></view>
			<view class="sys-nav">
				<view class="sys-back" @tap="goBack">
					<text class="iconfont icon-xiangzuo"></text>
				</view>
				<view class="sys-title">{{ title || '对话' }}</view>
				<view class="sys-side"></view>
			</view>
			<view class="bg"></view>
		</view>
		<!-- #endif -->
		<scroll-view class="chat" :style="{ top: chatTop, bottom: chatBottom }" scroll-y="true" :scroll-top="scrollTop" @scrolltolower="noop" :lower-threshold="60">
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
				<view class="msg" v-for="(m, mi) in messages" :key="mi" :class="m.role === 'user' ? 'user' : 'bot'">
					<view class="bubble" v-if="m.text || m.file">
						<view class="bubbleFile" v-if="m.file">
							<image v-if="m.file.type === 'image'" :src="m.file.url" mode="widthFix" class="bubbleImg" @tap.stop="previewImage(m.file.url)"></image>
							<view v-else class="bubbleOtherFile" @tap.stop="downloadFile(m.file.url)">
								<text class="iconfont icon-lianjie fileIcon"></text>
								<text class="fileName">{{ m.file.name || '点击下载文件' }}</text>
							</view>
						</view>
						<text v-if="m.role === 'user' && m.text" class="bubbleText">{{ m.text }}</text>
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
			<view class="hintRow" v-if="!showIntro">
				<view class="hintChip" v-for="(s, i) in suggestions" :key="i" @click="useSuggestion(s)">
					<text class="hintChipText">{{ s }}</text>
				</view>
			</view>
			<view class="composerInner">
				<view class="pendingFilePreview" v-if="pendingFile">
					<image v-if="pendingFile.type === 'image'" :src="pendingFile.url" mode="aspectFill" class="previewImg"></image>
					<view v-else class="previewFile">
						<text class="iconfont icon-lianjie fileIcon"></text>
						<text class="fileName">{{ pendingFile.name }}</text>
					</view>
					<view class="removeFile" @tap.stop="pendingFile = null">
						<text class="iconfont icon-guanbi6 removeIcon"></text>
					</view>
				</view>
				<view class="composerButtons">
					<view class="leftBadge" :class="{ disabled: !sessionId }" @tap.stop="clearHistory()">
						<text class="iconfont icon-shanchu31 leftIcon"></text>
					</view>
					<textarea class="ai-textarea" v-model="draft" :adjust-position="false" :cursor-spacing="0" :show-confirm-bar="false" :auto-height="true" placeholder="描述你的困扰或场景，我来帮你拆解" maxlength="-1" @focus="onComposerFocus" @blur="onComposerBlur" />
					<view class="addBadge" @tap.stop="uploadFile">
						<text class="iconfont icon-tianjia1 addIcon"></text>
					</view>
					<view class="send" :class="{ disabled: !draftTrim && !pendingFile }" @tap.stop="send">
						<text class="iconfont icon-fasong sendIcon"></text>
					</view>
				</view>
			</view>
			
			<!-- <view class="safePad"></view> -->
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
	import { markdownToHtml } from '@/utils/markdown.js'

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
				scrollTopSeed: 0,
				composerHeightPx: 0,
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立'],
				requestTask: null,
				perfStartAt: 0,
				perfFirstByteAt: 0,
				pendingFile: null,
				keyboardHeight: 0,
				keyboardHandler: null,
				sysHeight: '0px',
				customNavEnabled: false,
				customNavHeightPx: 0
			}
		},
		computed: {
			pagePad() {
				return {}
			},
			footerBottom() {
				if (this.keyboardHeight > 0) return `${this.keyboardHeight}px`
				return '0px'
			},
			chatBottom() {
				const base = this.composerHeightPx > 0 ? this.composerHeightPx : this.rpxToPx(260)
				const keyboard = this.keyboardHeight > 0 ? this.keyboardHeight : 0
				return `${Math.max(0, base + keyboard)}px`
			},
			chatTop() {
				if (!this.customNavEnabled) return '0px'
				return `${Number(this.customNavHeightPx || 0)}px`
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
			let statusBar = 0
			try {
				const sys = uni.getSystemInfoSync()
				statusBar = Number(sys && sys.statusBarHeight ? sys.statusBarHeight : 0) || 0
				if (statusBar <= 0 && uni.getMenuButtonBoundingClientRect) {
					const menuRect = uni.getMenuButtonBoundingClientRect()
					statusBar = Number(menuRect && menuRect.top ? menuRect.top : 0) || 0
				}
			} catch (e) {
				statusBar = 0
			}
			if (statusBar <= 0) statusBar = 20
			this.sysHeight = `${statusBar}px`
			// #ifdef MP || APP-PLUS
			this.customNavEnabled = true
			// #endif
			this.customNavHeightPx = statusBar + 43
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
			this.registerKeyboardListener()
			this.$nextTick(() => {
				this.measureComposerHeight()
			})
		},
		onShow() {
			this.registerKeyboardListener()
			this.$nextTick(() => {
				this.measureComposerHeight()
			})
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
			this.keyboardHeight = 0
			this.unregisterKeyboardListener()
			if (this.requestTask && this.requestTask.abort) {
				try {
					this.requestTask.abort()
				} catch (e) {}
			}
		},
		onUnload() {
			this.keyboardHeight = 0
			this.unregisterKeyboardListener()
			if (this.requestTask && this.requestTask.abort) {
				try {
					this.requestTask.abort()
				} catch (e) {}
			}
		},
		methods: {
			rpxToPx(v) {
				if (typeof uni !== 'undefined' && typeof uni.upx2px === 'function') {
					return Number(uni.upx2px(Number(v) || 0)) || 0
				}
				return Number(v) || 0
			},
			measureComposerHeight() {
				if (typeof uni === 'undefined' || typeof uni.createSelectorQuery !== 'function') return
				try {
					uni.createSelectorQuery()
						.in(this)
						.select('.composer')
						.boundingClientRect((rect) => {
							const h = rect && rect.height ? Number(rect.height) : 0
							if (h > 0) this.composerHeightPx = h
						})
						.exec()
				} catch (e) {}
			},
			goBack() {
				const pages = getCurrentPages()
				if (pages && pages.length > 1) {
					uni.navigateBack({
						delta: 1
					})
					return
				}
				uni.switchTab({
					url: '/pages/ai/index'
				})
			},
			uploadFile() {
				const that = this;
				// #ifdef MP-WEIXIN
				uni.chooseMessageFile({
					count: 1,
					type: 'all',
					success: (res) => {
						const file = res.tempFiles[0];
						this.performUpload(file.path, file.name);
					}
				});
				// #endif
				// #ifndef MP-WEIXIN
				uni.chooseImage({
					count: 1,
					sizeType: ['compressed'],
					sourceType: ['album', 'camera'],
					success: (res) => {
						this.performUpload(res.tempFilePaths[0]);
					}
				});
				// #endif
			},
			performUpload(filePath, fileName = '') {
				const that = this;
				uni.showLoading({ title: '上传中' });
				
				let fileType = 'file';
				const ext = (fileName || filePath).split('.').pop().toLowerCase();
				if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
					fileType = 'image';
				} else if (['mp4', 'mov', 'm4v'].includes(ext)) {
					fileType = 'video';
				}

				uni.uploadFile({
					url: HTTP_REQUEST_URL + '/api/upload/image',
					filePath: filePath,
					name: 'file',
					header: HEADER,
					success: (res) => {
						uni.hideLoading();
						let data = res.data;
						if (typeof data === 'string') {
							try { data = JSON.parse(data); } catch (e) {}
						}
						if (data && (data.status === 200 || data.code === 200)) {
							const url = data.data.url;
							that.pendingFile = {
								url: url,
								name: fileName || url.split('/').pop(),
								type: fileType,
								ext: ext
							};
						} else {
							uni.showToast({ title: (data && data.msg) || '上传失败', icon: 'none' });
						}
					},
					fail: (err) => {
						uni.hideLoading();
						uni.showToast({ title: '上传异常', icon: 'none' });
					}
				});
			},
			previewImage(url) {
				uni.previewImage({
					urls: [url]
				})
			},
			downloadFile(url) {
				// #ifdef MP-WEIXIN
				uni.downloadFile({
					url: url,
					success: (res) => {
						if (res.statusCode === 200) {
							uni.openDocument({
								filePath: res.tempFilePath,
								success: function (res) {
									console.log('打开文档成功');
								}
							});
						}
					}
				});
				// #endif
				// #ifndef MP-WEIXIN
				window.open(url);
				// #endif
			},
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
							html: m.role === 'user' ? '' : markdownToHtml(m.content)
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
			registerKeyboardListener() {
				if (typeof uni.onKeyboardHeightChange !== 'function') return
				if (this.keyboardHandler) return
				const handler = (res) => {
					const h = Number((res && res.height) || 0) || 0
					this.keyboardHeight = h
					this.$nextTick(() => {
						this.measureComposerHeight()
					})
					if (h > 0) this.scrollToBottom()
				}
				this.keyboardHandler = handler
				try {
					uni.onKeyboardHeightChange(handler)
				} catch (e) {}
			},
			unregisterKeyboardListener() {
				if (!this.keyboardHandler) return
				if (typeof uni.offKeyboardHeightChange === 'function') {
					try {
						uni.offKeyboardHeightChange(this.keyboardHandler)
					} catch (e) {}
				}
				this.keyboardHandler = null
			},
			useSuggestion(s) {
				this.draft = s
				this.send()
			},
			onComposerFocus() {
				this.scrollToBottom()
				setTimeout(() => {
					this.scrollToBottom()
				}, 120)
				this.$nextTick(() => {
					this.measureComposerHeight()
				})
			},
			onComposerBlur() {
				this.keyboardHeight = 0
				this.$nextTick(() => {
					this.measureComposerHeight()
				})
			},
			goAgents() {
				uni.navigateTo({
					url: '/pages/ai/agents'
				})
			},
			scrollToBottom() {
				this.$nextTick(() => {
					this.scrollTopSeed += 1
					this.scrollTop = 900000 + this.scrollTopSeed
				})
			},
			send() {
				if ((!this.draftTrim && !this.pendingFile) || this.sending) return
				const text = this.draftTrim
				const file = this.pendingFile
				this.draft = ''
				this.pendingFile = null
				this.showIntro = false
				this.perfStartAt = Date.now()
				this.perfFirstByteAt = 0
				this.messages.push({
					id: uid(),
					role: 'user',
					text,
					file: file
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
					this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
					this.sending = false
					this.scrollToBottom()
					return
				}

				if (!this.agentId || this.agentId === 'hyqz_default') {
					this.messages[botMsgIndex].text = '请先选择一个智能体'
					this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
					this.sending = false
					this.scrollToBottom()
					return
				}

				// Construct the message content to send to API
				let apiMessage = text
				if (file && file.url) {
					apiMessage = text ? `${text}\n[文件: ${file.url}]` : `[文件: ${file.url}]`
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
								this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
								this.scrollToBottom()
							}
							if (data.blocks && Array.isArray(data.blocks) && data.blocks.length && !String(this.messages[botMsgIndex].text || '').trim()) {
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
								this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
								this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
								this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
						message: apiMessage,
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
					this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
							this.messages[botMsgIndex].html = markdownToHtml(reply)
						}
						return
					}
					const msg = payload && typeof payload === 'object' ? (payload.msg || payload.error || payload.message) : ''
					if (msg) {
						this.messages[botMsgIndex].text = String(msg)
						this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
					}
				}

				let retried = false
				const finalize = () => {
					processChunk('', true)
					if (!this.messages[botMsgIndex].text) {
						this.messages[botMsgIndex].text = '未收到回复，请检查小程序请求域名、网络或服务日志'
						this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
						timeout: 120000,
						data: {
							agent_id: this.agentId,
							session_id: this.sessionId,
							message: apiMessage,
							stream: useStream ? true : false,
							format: 'text'
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
							this.messages[botMsgIndex].html = markdownToHtml(this.messages[botMsgIndex].text)
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
		background: linear-gradient(180deg, rgba(241, 165, 92, 0.52) 0%, rgba(249, 233, 200, 0.96) 62%, #fff7ef 100%);
	}

	.sys-head {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 20;
		background: #fff3e6;
		box-shadow: 0 10rpx 24rpx rgba(31, 35, 41, 0.10);
		.bg {
			display: none;
		}
	}

	.sys-nav {
		height: 43px;
		display: flex;
		align-items: center;
	}

	.sys-back,
	.sys-side {
		width: 88rpx;
		height: 43px;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.sys-back .iconfont {
		font-size: 34rpx;
		color: #111827;
	}

	.sys-title {
		flex: 1;
		text-align: center;
		line-height: 43px;
		font-size: 36rpx;
		color: #333;
		font-weight: 600;
	}
	
	.chat {
		position: fixed;
		left: 0;
		right: 0;
		top: 0;
		bottom: 260rpx;
	}

	.chatInner {
		padding: 18rpx 24rpx 24rpx;
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
		align-items: flex-end;
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
		font-size: 30rpx;
		line-height: 44rpx;
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
		font-size: 30rpx;
		line-height: 44rpx;
		color: rgba(31, 35, 41, 0.92);
	}
	
	.introWelcome {
		margin-top: 18rpx;
		text-align: center;
	}
	
	.introWelcomeText {
		font-size: 28rpx;
		line-height: 44rpx;
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
		display: flex;
		flex-direction: column;
		gap: 12rpx;
	}

	.bubbleFile {
		width: 100%;
		border-radius: 12rpx;
		overflow: hidden;
	}

	.bubbleImg {
		width: 100%;
		max-width: 500rpx;
		display: block;
		border-radius: 12rpx;
	}

	.bubbleOtherFile {
		display: flex;
		align-items: center;
		padding: 20rpx;
		background: #f5f6f7;
		border-radius: 12rpx;
		gap: 16rpx;
		width: 400rpx;
	}

	.bubbleOtherFile .fileIcon {
		font-size: 40rpx;
		color: #646a73;
	}

	.bubbleOtherFile .fileName {
		font-size: 26rpx;
		color: #1f2329;
		flex: 1;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.msg.user .bubbleOtherFile {
		background: rgba(255, 255, 255, 0.15);
	}

	.msg.user .bubbleOtherFile .fileIcon,
	.msg.user .bubbleOtherFile .fileName {
		color: #fff;
	}

	.msg.user .bubble {
		background: #da7e28;
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
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 700;
		color: rgba(31, 35, 41, 0.72);
	}

	.replyCardBody {
		padding: 16rpx 16rpx 14rpx;
	}

	.bubbleText {
		font-size: 32rpx;
		line-height: 48rpx;
		color: #1f2329;
	}

	.bubbleAiTag {
		margin-top: 8rpx;
		align-self: flex-end;
		font-size: 24rpx;
		line-height: 28rpx;
		color: rgba(31, 35, 41, 0.45);
	}

	.msg.user .bubbleText {
		color: rgba(255, 255, 255, 0.96);
	}

	.fmt {
		font-size: 32rpx;
		line-height: 48rpx;
		color: #1f2329;
		word-break: break-word;
		::v-deep p {
			margin: 0 0 10rpx 0;
		}
		::v-deep h1 {
			font-size: 38rpx;
			line-height: 48rpx;
			font-weight: 800;
			margin: 8rpx 0 12rpx 0;
		}
		::v-deep h2 {
			font-size: 36rpx;
			line-height: 46rpx;
			font-weight: 800;
			margin: 8rpx 0 12rpx 0;
		}
		::v-deep h3 {
			font-size: 34rpx;
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
			font-size: 26rpx;
			font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		}
		::v-deep pre code {
			padding: 0;
			background: transparent;
			font-size: 26rpx;
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
		padding: 16rpx 18rpx 16rpx;
		background: #fff;
		box-shadow: 0rpx -5rpx 20rpx rgba(31, 35, 41, 0.10);
		z-index: 9999;
		pointer-events: auto;
	}

	.composerInner {
		display: flex;
		flex-direction: column;
		background: transparent;
		border-radius: 24rpx;
		border: none;
		overflow: visible;
		box-shadow: none;
		pointer-events: auto;
	}

	.pendingFilePreview {
		position: relative;
		padding: 24rpx 24rpx 0;
		display: flex;
		align-items: center;
		z-index: 10;
		pointer-events: auto;
	}

	.previewImg {
		width: 140rpx;
		height: 140rpx;
		border-radius: 12rpx;
		border: 1rpx solid rgba(0, 0, 0, 0.05);
	}

	.previewFile {
		width: 140rpx;
		height: 140rpx;
		background: #f5f6f7;
		border-radius: 12rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 10rpx;
		box-sizing: border-box;
		gap: 4rpx;
		pointer-events: auto;
	}

	.previewFile .fileIcon {
		font-size: 40rpx;
		color: #646a73;
		pointer-events: none;
	}

	.previewFile .fileName {
		font-size: 20rpx;
		color: #646a73;
		width: 100%;
		text-align: center;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		pointer-events: none;
	}

	.removeFile {
		position: absolute;
		top: 14rpx;
		left: 144rpx;
		width: 44rpx; /* 调大一点 */
		height: 44rpx;
		background: rgba(0, 0, 0, 0.5);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 11;
		pointer-events: auto;
	}

	.removeIcon {
		color: #fff;
		font-size: 24rpx;
		pointer-events: none;
	}

	.composerButtons {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 2rpx;
		padding: 8rpx 15rpx 12rpx;
		z-index: 10;
		pointer-events: auto;
	}

	.addBadge {
		width: 56rpx;
		height: 56rpx;
		border-radius: 12rpx;
		background: transparent;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
		pointer-events: auto;
		align-self: center;
	}

	.addIcon {
		font-size: 54rpx;
		color: #e98f36;
		pointer-events: none;
	}

	.leftBadge {
		width: 56rpx;
		height: 56rpx;
		border-radius: 12rpx;
		background: transparent;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
		pointer-events: auto;
		align-self: center;
	}

	.leftBadge.disabled {
		opacity: 0.35;
	}

	.leftIcon {
		font-size: 54rpx;
		color: #e98f36;
		pointer-events: none;
	}
	.ai-textarea {
		flex: 0.92;
		min-width: 0;
		min-height: 90rpx;
		max-height: 150rpx;
		padding: 14rpx 18rpx;
		background: #f5f6f7;
		border: 1rpx solid rgba(31, 35, 41, 0.12);
		border-radius: 12rpx;
		font-size: 32rpx;
		color: #1f2329;
		line-height: 1.4;
		box-sizing: border-box;
		word-break: break-all;
		white-space: pre-wrap;
	}

	.send {
		height: 56rpx;
		width: 56rpx;
		padding: 0;
		border-radius: 12rpx;
		background: transparent;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
		align-self: center;
	}

	.send.disabled {
		background: transparent;
		opacity: 0.35;
	}

	.sendIcon {
		font-size: 54rpx;
		color: #e98f36;
		pointer-events: none;
	}

	.send.disabled .sendIcon {
		color: #e98f36;
	}

	.hintRow {
		// margin-top: 12rpx;
		display: flex;
		gap: 12rpx;
		overflow-x: auto;
		white-space: nowrap;
		padding-bottom: 12rpx;
	}

	.hintChip {
		flex: none;
		padding: 14rpx 18rpx;
		border-radius: 999rpx;
		background: rgba(241, 165, 92, 0.08);
		border: 1rpx solid rgba(241, 165, 92, 0.16);
	}

	.hintChipText {
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 700;
		color: #a95608;
	}
	
	.safePad {
		height: env(safe-area-inset-bottom);
		pointer-events: none;
		background-color: white;
	}
</style>
