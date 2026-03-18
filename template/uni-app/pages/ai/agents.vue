<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<!-- #ifdef MP || APP-PLUS -->
		<view class="sys-head">
			<view class="sys-bar" :style="{ height: sysHeight }"></view>
			<!-- #ifdef MP -->
			<view class="sys-title">{{ pageTitle }}</view>
			<!-- #endif -->
			<view class="bg"></view>
		</view>
		<!-- #endif -->

		<view class="top">
			<view class="searchBox">
				<view class="searchIcon">
					<text class="iconfont icon-sousuo"></text>
				</view>
				<input class="searchInput" v-model="keyword" confirm-type="search" placeholder="搜索：亲子沟通 / 学习习惯 / 情绪管理" @confirm="applyFilter" />
				<view class="searchBtn" @click="applyFilter">
					<text class="searchBtnText">搜索</text>
				</view>
			</view>
			<view class="cateBar">
				<view class="cateInner">
					<scroll-view class="cateScroll" scroll-x show-scrollbar="false">
						<view class="cateTrack">
							<view class="cateItem" :class="{ active: activeCate === c.key }" v-for="c in categories" :key="c.key" @click="setCate(c.key)">
							<text class="cateText">{{ c.name }}</text>
							<view class="cateLine" v-if="activeCate === c.key"></view>
						</view>
						</view>
					</scroll-view>
				</view>
			</view>
		</view>

		<view class="list">
			<view class="grid" v-if="filteredAgents.length">
				<view class="agentCard" :class="{ locked: !a.unlocked, unlocked: !!a.unlocked }" v-for="a in filteredAgents" :key="a.id" @click="goChat(a)">
					<view class="cardContent">
						<view class="cardHeader">
							<view class="agentMeta">
								<text class="agentName">{{ getDisplayName(a.name) }}</text>
								<view class="agentTag">
									<text class="agentTagText">{{ a.cateName }}</text>
								</view>
							</view>
						</view>

						<text class="agentDesc">{{ getDisplayDesc(a.desc) }}</text>
					</view>

					<view v-if="!a.unlocked" class="lockedMask" aria-hidden="true"></view>
					<view class="statusIcon" aria-hidden="true">
						<text class="iconfont" :class="a.unlocked ? 'icon-ic-complete1' : 'icon-suozi'"></text>
					</view>
				</view>
			</view>

			<view class="empty" v-else>
				<text class="emptyTitle">没有匹配的智能体</text>
				<text class="emptyDesc">换个关键词或分类试试</text>
				<view class="emptyBtn" @click="reset">
					<text class="emptyBtnText">重置筛选</text>
				</view>
			</view>
		</view>
		<pageFooter @newDataStatus="newDataStatus" v-show="showBar"></pageFooter>
	</view>
</template>

<script>
	let sysHeight = uni.getSystemInfoSync().statusBarHeight + 'px'
	import colors from '@/mixins/color.js'
	import pageFooter from '@/components/pageFooter/index.vue'
	import {
		getAgentMatrix,
		getAgentAccess
	} from '@/api/ai.js'
	import {
		toLogin
	} from '@/libs/login.js'

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
				sysHeight: sysHeight,
				pageTitle: '技能课超市',
				onlyUnlocked: false,
				activeCate: 'all',
				keyword: '',
				categories: [{
					key: 'all',
					name: '全部'
				}],
				agents: []
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
			filteredAgents() {
				const kw = (this.keyword || '').trim()
				return this.agents.filter((a) => {
					if (this.onlyUnlocked && !a.unlocked) return false
					const cateOk = this.activeCate === 'all' || a.cate === this.activeCate
					if (!cateOk) return false
					if (!kw) return true
					const hay = `${a.name} ${a.desc} ${a.cateName}`
					return hay.indexOf(kw) > -1
				})
			}
		},
		onLoad(option) {
			const onlyUnlocked = option && String(option.onlyUnlocked || '') === '1'
			this.onlyUnlocked = onlyUnlocked
			const title = option && option.title ? String(option.title) : ''
			if (title) this.pageTitle = title
			else this.pageTitle = onlyUnlocked ? '我的智能体' : '技能课超市'
			// #ifdef H5 || APP-PLUS || MP
			uni.setNavigationBarTitle({
				title: this.pageTitle
			})
			// #endif
			this.loadData()
		},
		methods: {
			loadData() {
				getAgentMatrix().then(res => {
					const list = res.data || []
					const cats = [{
						key: 'all',
						name: '全部'
					}]
					const agents = []

					list.forEach(c => {
						cats.push({
							key: c.cate_key,
							name: c.cate_name
						})
						if (c.agents && c.agents.length) {
							c.agents.forEach(a => {
								const agentName = this.pickFirstText([a.agent_name, a.name, a.title, a.abbr])
								const agentDesc = this.pickFirstText([a.description, a.desc, a.summary, a.intro])
								agents.push({
									id: a.id,
									cate: c.cate_key,
									cateName: c.cate_name,
									name: agentName,
									desc: agentDesc,
									unlocked: !!a.unlocked
								})
							})
						}
					})

					this.categories = cats
					this.agents = agents
				}).catch(err => {
					// uni.showToast({ title: err.msg || '加载失败', icon: 'none' })
				})
			},
			newDataStatus(val, num) {
				this.isFooter = !!val
				this.showBar = !!val
				this.pdHeight = num || 0
			},
			setCate(key) {
				this.activeCate = key
			},
			applyFilter() {
			},
			reset() {
				this.activeCate = 'all'
				this.keyword = ''
			},
			normalizeText(value) {
				if (value === null || value === undefined) return ''
				const text = String(value).trim()
				if (!text) return ''
				const lower = text.toLowerCase()
				if (lower === 'null' || lower === 'undefined') return ''
				return text
			},
			pickFirstText(candidates) {
				for (let i = 0; i < candidates.length; i++) {
					const text = this.normalizeText(candidates[i])
					if (text) return text
				}
				return ''
			},
			truncateText(input, maxLen) {
				const text = String(input || '')
				const limit = Number(maxLen) || 0
				if (!limit) return ''
				const chars = Array.from(text)
				if (chars.length <= limit) return text
				return chars.slice(0, limit).join('') + '...'
			},
			getDisplayName(name) {
				const safeName = this.normalizeText(name) || '未命名智能体'
				return this.truncateText(safeName, 8)
			},
			getDisplayDesc(desc) {
				const safeDesc = this.normalizeText(desc) || '暂无介绍'
				return this.truncateText(safeDesc, 20)
			},
			goChat(agent) {
				const agentId = Number(agent && agent.id) || 0
				if (!agentId) return
				getAgentAccess({
					agent_id: agentId
				}).then(res => {
					const d = res && res.data ? res.data : {}
					if (d.unlocked) {
						uni.navigateTo({
							url: `/pages/ai/chat?agentId=${encodeURIComponent(agentId)}&title=${encodeURIComponent(agent.name)}`
						})
						return
					}
					const productId = Number(d.product_id) || 0
					if (!productId) {
						uni.showToast({
							title: '未配置购买商品',
							icon: 'none'
						})
						return
					}
					uni.navigateTo({
						url: `/pages/goods_details/index?id=${encodeURIComponent(productId)}&agent_id=${encodeURIComponent(agentId)}&agent_title=${encodeURIComponent(agent.name)}`
					})
				}).catch(err => {
					const msg = err && err.msg ? err.msg : err
					if (String(msg || '').includes('请先登录')) return toLogin()
					uni.showToast({
						title: typeof msg === 'string' ? msg : '操作失败',
						icon: 'none'
					})
				})
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page {
		min-height: 100vh;
		background-color: #eef2f3;
		--bg-mesh:
			radial-gradient(circle at 0% 0%, rgba(255, 154, 158, 0.2) 0%, rgba(255, 154, 158, 0) 45%),
			radial-gradient(circle at 100% 0%, rgba(118, 75, 162, 0.1) 0%, rgba(118, 75, 162, 0) 50%),
			radial-gradient(circle at 100% 100%, rgba(255, 236, 210, 0.5) 0%, rgba(255, 236, 210, 0) 55%);
		background-image: var(--bg-mesh);
		background-repeat: no-repeat;
		background-size: cover;
	}

	.sys-head {
		position: relative;
		width: 100%;

		.bg {
			display: none;
		}

		.sys-title {
			z-index: 10;
			position: relative;
			height: 43px;
			text-align: center;
			line-height: 43px;
			font-size: 36rpx;
			color: #333;
		}
	}

	.top {
		padding: 20rpx 24rpx 10rpx;
	}

	.searchBox {
		display: flex;
		align-items: center;
		gap: 16rpx;
		background: #fff;
		border-radius: 20rpx;
		padding: 10rpx 10rpx 10rpx 24rpx;
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.03);
	}
	
	.searchIcon {
		color: rgba(31, 35, 41, 0.4);
	}
	
	.searchIcon .iconfont {
		font-size: 32rpx;
	}

	.searchInput {
		flex: 1;
		height: 64rpx;
		font-size: 28rpx;
		color: #1f2329;
	}

	.searchBtn {
		height: 64rpx;
		padding: 0 24rpx;
		border-radius: 16rpx;
		background: linear-gradient(135deg, var(--view-main-start, rgb(233,213,222)) 0%, var(--view-main-over, rgb(200, 166, 181)) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4rpx 10rpx rgba(193, 135, 162, 0.3);
		min-width: 112rpx;
		flex: none;
	}

	.searchBtnText {
		font-size: 26rpx;
		font-weight: 700;
		color: #fff;
	}

	.cateBar {
		margin-top: 24rpx;
		width: 100%;
	}

	.cateInner {
		padding: 0 8rpx 12rpx;
	}

	.cateScroll {
		width: 100%;
		white-space: nowrap;
	}

	.cateTrack {
		display: inline-flex;
		align-items: center;
		gap: 18rpx;
		padding-right: 24rpx;
	}

	.cateItem {
		position: relative;
		padding: 10rpx 4rpx;
		flex: none;
	}

	.cateText {
		font-size: 28rpx;
		color: rgba(31, 35, 41, 0.6);
		transition: all 0.2s;
	}

	.cateItem.active .cateText {
		font-size: 30rpx;
		font-weight: 800;
		color: #1f2329;
	}
	
	.cateLine {
		position: absolute;
		bottom: 0;
		left: 50%;
		transform: translateX(-50%);
		width: 40rpx;
		height: 6rpx;
		background: linear-gradient(90deg, #ff9a9e 0%, #fad0c4 100%);
		border-radius: 4rpx;
	}

	.list {
		padding: 10rpx 24rpx 28rpx;
	}

	.grid {
		display: flex;
		flex-wrap: wrap;
		justify-content: space-between;
	}

	.agentCard {
		flex: 1 1 calc(50% - 12px);
		max-width: calc(50% - 12px);
		position: relative;
		overflow: hidden;
		border-radius: 16px;
		background: rgba(255, 255, 255, 0.2);
		backdrop-filter: blur(20px);
		-webkit-backdrop-filter: blur(20px);
		box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
		border: 1px solid rgba(255, 255, 255, 0.3);
		display: flex;
		flex-direction: column;
		height: auto;
		min-height: 120px;
		margin-bottom: 20px;
	}

	.agentCard::before {
		content: '';
		display: block;
		padding-top: 56.25%;
	}
	
	.agentCard.locked {
	}

	.cardContent {
		position: absolute;
		inset: 0;
		z-index: 1;
		display: flex;
		flex-direction: column;
		height: 100%;
		padding: 14px 15px;
	}
	
	.agentCard.unlocked {
	}
	
	.agentCard.unlocked::after {
		content: '';
		position: absolute;
		inset: -30%;
		border-radius: 16px;
		background: linear-gradient(
			120deg,
			rgba(255, 152, 0, 0) 0%,
			rgba(255, 152, 0, 0.12) 22%,
			rgba(255, 200, 120, 0.22) 50%,
			rgba(255, 152, 0, 0.12) 78%,
			rgba(255, 152, 0, 0) 100%
		);
		filter: blur(10px);
		transform: translateX(-60%) rotate(-6deg);
		opacity: 0.9;
		animation: agentOrangeShimmer 3.6s ease-in-out infinite;
		pointer-events: none;
		z-index: 0;
	}

	@keyframes agentOrangeShimmer {
		0% {
			transform: translateX(-60%) rotate(-6deg);
		}
		60% {
			transform: translateX(60%) rotate(-6deg);
		}
		100% {
			transform: translateX(60%) rotate(-6deg);
		}
	}

	@media (prefers-reduced-motion: reduce) {
		.agentCard.unlocked::after {
			animation: none;
		}
	}

	.lockedMask {
		position: absolute;
		inset: 0;
		background: rgba(0, 0, 0, 0.3);
		z-index: 2;
		pointer-events: none;
	}
	
	.statusIcon {
		position: absolute;
		right: 12px;
		bottom: 12px;
		width: 24px;
		height: 24px;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 3;
		pointer-events: none;
	}

	.statusIcon .iconfont {
		font-size: 24px;
		line-height: 24px;
	}

	.agentCard.unlocked .statusIcon .iconfont {
		color: #ff9800;
	}

	.agentCard.locked .statusIcon .iconfont {
		color: rgba(255, 255, 255, 0.92);
	}

	.agentCard:active {
		transform: scale(0.98);
		transition: transform 0.1s;
	}

	.cardHeader {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 5px;
	}

	.agentAvatar {
		width: 44px;
		height: 44px;
		border-radius: 12px;
		background: rgba(255, 255, 255, 0.22);
		border: 1px solid rgba(255, 255, 255, 0.3);
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
		overflow: hidden;
	}

	.agentAvatarImg {
		width: 44px;
		height: 44px;
		border-radius: 12px;
	}

	.agentAvatarFallback {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.agentAvatarText {
		font-size: 18px;
		font-weight: 700;
		color: rgba(31, 35, 41, 0.78);
	}

	.agentMeta {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 6px;
		padding: 1px 0;
	}

	.agentName {
		font-size: 15px;
		font-weight: 600;
		color: rgba(31, 35, 41, 0.9);
		line-height: 1.25;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.agentTag {
		display: inline-flex;
		padding-top: 2px;
	}

	.agentTagText {
		font-size: 11px;
		color: rgba(31, 35, 41, 0.72);
		background: rgba(255, 255, 255, 0.18);
		padding: 3px 8px;
		border-radius: 8px;
		font-weight: 600;
	}

	.agentDesc {
		font-size: 12px;
		color: rgba(31, 35, 41, 0.68);
		line-height: 1.45;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		margin-top: 2px;
		height-min: 40px;
	}

	.empty {
		margin-top: 60rpx;
		padding: 60rpx;
		text-align: center;
	}

	.emptyTitle {
		display: block;
		font-size: 30rpx;
		font-weight: 700;
		color: rgba(31, 35, 41, 0.8);
	}

	.emptyDesc {
		margin-top: 12rpx;
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.5);
	}

	.emptyBtn {
		margin: 32rpx auto 0;
		height: 80rpx;
		border-radius: 40rpx;
		width: 240rpx;
		background: linear-gradient(135deg, var(--view-main-start) 0%, var(--view-main-over) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 8rpx 20rpx rgba(241, 165, 92, 0.3);
	}

	.emptyBtnText {
		font-size: 28rpx;
		font-weight: 700;
		color: #fff;
	}
</style>
