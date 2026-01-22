<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<scroll-view class="chat" :style="{ height: chatHeight }" scroll-y="true" :scroll-top="scrollTop" @scrolltolower="noop" :lower-threshold="60">
			<view class="chatInner">
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
										<text class="cardBtnText">购买</text>
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
		<pageFooter @newDataStatus="newDataStatus" v-show="showBar"></pageFooter>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
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
				agentId: '',
				title: '',
				draft: '',
				sending: false,
				scrollTop: 0,
				messages: [],
				suggestions: ['孩子顶嘴很严重', '写作业太拖拉', '情绪失控后怎么修复', '手机规则怎么立']
			}
		},
		computed: {
			pagePad() {
				if (this.isFooter) {
					return {
						paddingBottom: `${this.pdHeight * 2 + 120}rpx`
					}
				}
				return {}
			},
			footerOffsetRpx() {
				if (!this.isFooter) return 0
				return this.pdHeight * 2 + 100
			},
			footerBottom() {
				if (!this.isFooter) return '0rpx'
				return `${this.footerOffsetRpx}rpx`
			},
			chatHeight() {
				if (!this.isFooter) return 'calc(100vh - 260rpx)'
				return `calc(100vh - 260rpx - ${this.footerOffsetRpx}rpx)`
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
			this.messages = [{
				id: uid(),
				role: 'bot',
				text: '把发生的场景描述给我：谁、在什么时间、说了什么、你怎么回应的？我会给你一套可执行的沟通步骤。'
			}]
			if (prefill) {
				this.draft = prefill
				this.send()
			}
		},
		methods: {
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
			goAgents() {
				uni.navigateTo({
					url: '/pages/ai/agents'
				})
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
				const text = this.draftTrim
				this.draft = ''
				this.messages.push({
					id: uid(),
					role: 'user',
					text
				})
				this.sending = true
				this.scrollToBottom()

				setTimeout(() => {
					const cards = [{
						id: 101,
						title: '3步建立家庭沟通规则',
						desc: '从“讲道理”到“能执行”的落地模板',
						price: '19.9'
					}]
					this.messages.push({
						id: uid(),
						role: 'bot',
						text: `我理解你的困扰。我们先把目标定清楚：你更希望孩子做到“立刻停止顶嘴”，还是“能表达但不伤人”？我会按“先共情-再边界-再选择题”的顺序给你一句句可直接照读的表达。`,
						cards
					})
					this.sending = false
					this.scrollToBottom()
				}, 650)
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

	.cards {
		margin-top: 12rpx;
		width: 620rpx;
	}

	.card {
		display: flex;
		border-radius: 18rpx;
		overflow: hidden;
		background: #fff;
		border: 1rpx solid rgba(0, 0, 0, 0.06);
	}

	.cardCover {
		width: 170rpx;
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.18) 0%, rgba(241, 165, 92, 0.07) 100%);
		position: relative;
	}

	.cardLabel {
		position: absolute;
		left: 12rpx;
		top: 12rpx;
		padding: 8rpx 12rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.9);
	}

	.cardLabelText {
		font-size: 22rpx;
		color: #a95608;
	}

	.cardBody {
		flex: 1;
		padding: 16rpx 16rpx 14rpx;
	}

	.cardTitle {
		display: block;
		font-size: 28rpx;
		font-weight: 900;
		color: #1f2329;
	}

	.cardDesc {
		margin-top: 6rpx;
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.62);
	}

	.cardFoot {
		margin-top: 12rpx;
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
		padding: 10rpx 16rpx;
		border-radius: 16rpx;
		background: rgba(241, 165, 92, 0.08);
		border: 1rpx solid rgba(241, 165, 92, 0.20);
	}

	.cardBtnText {
		font-size: 24rpx;
		font-weight: 800;
		color: #a95608;
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
