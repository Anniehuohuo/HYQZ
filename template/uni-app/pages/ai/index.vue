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
					<view class="msg" v-for="m in messages" :key="m.id" :class="m.role === 'user' ? 'user' : 'bot'">
						<view class="bubble">
							<text class="bubbleText">{{ m.text }}</text>
						</view>
						<view class="cards" v-if="m.cards && m.cards.length">
							<view class="card" v-for="c in m.cards" :key="c.id" @click="goProduct(c)">
								<view class="cardCover">
									<view class="cardLabel">
										<text class="cardLabelText">课件</text>
									</view>
								</view>
								<view class="cardBody">
									<text class="cardTitle">{{ c.title }}</text>
									<text class="cardDesc">{{ c.desc }}</text>
									<view class="cardFoot">
										<text class="cardPrice">￥{{ c.price }}</text>
										<view class="cardBtn">
											<text class="cardBtnText">去看看</text>
										</view>
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
			</view>
		</scroll-view>

		<view class="composer" :style="{ bottom: footerBottom }">
			<view class="composerInner">
				<view class="leftBadge">
					<text class="iconfont icon-lingxing leftIcon"></text>
				</view>
				<input class="input" v-model="draft" :adjust-position="true" confirm-type="send" placeholder="有什么问题尽管问我" @confirm="send" />
				<view class="send" :class="{ disabled: !draftTrim }" @click="send">
					<text class="iconfont icon-fasong sendIcon"></text>
				</view>
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
	import {
		HTTP_REQUEST_URL,
		HEADER
	} from '@/config/app.js'
	import pageFooter from '@/components/pageFooter/index.vue'

	function uid() {
		return `${Date.now()}_${Math.random().toString(16).slice(2)}`
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
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立'],
				recommendPool: [{
					id: 101,
					title: '3步建立家庭沟通规则',
					desc: '从“讲道理”到“能执行”的落地模板',
					price: '19.9'
				}, {
					id: 102,
					title: '拖拉磨蹭的7个引导句式',
					desc: '把催促换成可操作的提醒',
					price: '29.9'
				}, {
					id: 103,
					title: '情绪爆发后的修复对话脚本',
					desc: '把“吵完就算”变成“关系更稳”',
					price: '39.9'
				}]
			}
		},
		onLoad(options) {
			this.loadHomeAgentConfig()
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
			this.messages = [{
				id: uid(),
				role: 'bot',
				text: '把发生的场景描述给我：谁、在什么时间、说了什么、你怎么回应的？我会给你一套可执行的沟通步骤。',
				cards: [this.recommendPool[0]]
			}]
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
			}
		},
		methods: {
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
			goProduct(card) {
				uni.navigateTo({
					url: `/pages/goods_details/index?id=${card.id}`
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
				this.sending = true
				this.scrollToBottom()

				const botMsgIndex = this.messages.length
				this.messages.push({
					id: uid(),
					role: 'bot',
					text: ''
				})

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
						stream: 1
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
					const shouldRecommend = this.replyCount === 1 || this.replyCount % 2 === 0
					const cards = shouldRecommend ? [this.recommendPool[this.replyCount % this.recommendPool.length]] : []
					this.messages[botMsgIndex].cards = cards
					this.scrollToBottom()
				})
				// #endif

				// #ifndef H5
				this.requestTask = uni.request({
					url: HTTP_REQUEST_URL + '/api/ai/home_chat',
					method: 'POST',
					header: header,
					data: {
						message: text,
						stream: 1
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
						this.replyCount += 1
						const shouldRecommend = this.replyCount === 1 || this.replyCount % 2 === 0
						const cards = shouldRecommend ? [this.recommendPool[this.replyCount % this.recommendPool.length]] : []
						this.messages[botMsgIndex].cards = cards
						this.scrollToBottom()
					}
				})
				if (this.requestTask && this.requestTask.onChunkReceived) {
					this.requestTask.onChunkReceived((res) => {
						let chunk = ''
						if (typeof res.data === 'string') {
							chunk = res.data
						} else {
							try {
								chunk = new TextDecoder('utf-8').decode(new Uint8Array(res.data))
							} catch (e) {}
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
		justify-content: space-between;
	}

	.cardPrice {
		font-size: 28rpx;
		font-weight: 900;
		color: var(--view-theme);
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
</style>
