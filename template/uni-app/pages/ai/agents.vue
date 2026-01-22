<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<!-- #ifdef MP || APP-PLUS -->
		<view class="sys-head">
			<view class="sys-bar" :style="{ height: sysHeight }"></view>
			<!-- #ifdef MP -->
			<view class="sys-title">智能体矩阵</view>
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
			<scroll-view class="cateBar" scroll-x="true" show-scrollbar="false">
				<view class="cateInner">
					<view class="cateItem" :class="{ active: activeCate === c.key }" v-for="c in categories" :key="c.key" @click="setCate(c.key)">
						<text class="cateText">{{ c.name }}</text>
						<view class="cateLine" v-if="activeCate === c.key"></view>
					</view>
				</view>
			</scroll-view>
		</view>

		<view class="list">
			<view class="grid" v-if="filteredAgents.length">
				<view class="agentCard" v-for="a in filteredAgents" :key="a.id" @click="goChat(a)">
					<view class="cardHeader">
						<view class="agentIcon">
							<text class="agentIconText">{{ a.abbr }}</text>
						</view>
						<view class="agentMeta">
							<text class="agentName">{{ a.name }}</text>
							<view class="agentTag">
								<text class="agentTagText">{{ a.cateName }}</text>
							</view>
						</view>
						<view class="enterIcon">
							<text class="iconfont icon-jinru1"></text>
						</view>
					</view>
					
					<text class="agentDesc">{{ a.desc }}</text>
					
					<view class="agentFoot">
						<view class="miniChip" v-for="(t, i) in a.tags" :key="i">
							<text class="miniChipText"># {{ t }}</text>
						</view>
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
				activeCate: 'all',
				keyword: '',
				categories: [{
					key: 'all',
					name: '全部'
				}, {
					key: 'comm',
					name: '亲子沟通'
				}, {
					key: 'study',
					name: '学习习惯'
				}, {
					key: 'emotion',
					name: '情绪管理'
				}, {
					key: 'teen',
					name: '青春期'
				}],
				agents: [{
					id: 'a_comm_1',
					cate: 'comm',
					cateName: '亲子沟通',
					abbr: '沟',
					name: '沟通教练',
					desc: '把冲突变成合作：用结构化对话化解顶嘴、争执与冷战。',
					tags: ['共情', '边界', '修复']
				}, {
					id: 'a_study_1',
					cate: 'study',
					cateName: '学习习惯',
					abbr: '学',
					name: '习惯规划师',
					desc: '围绕作业拖拉、时间管理与执行力，给到可落地的家庭方案。',
					tags: ['计划', '执行', '反馈']
				}, {
					id: 'a_emotion_1',
					cate: 'emotion',
					cateName: '情绪管理',
					abbr: '情',
					name: '情绪陪伴官',
					desc: '帮助父母先稳住自己，再陪孩子把情绪说出来、走出来。',
					tags: ['稳定', '倾听', '复盘']
				}, {
					id: 'a_teen_1',
					cate: 'teen',
					cateName: '青春期',
					abbr: '青',
					name: '青春期顾问',
					desc: '应对对抗、隐私、手机与学业压力，兼顾关系与规则。',
					tags: ['规则', '信任', '协商']
				}]
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
					const cateOk = this.activeCate === 'all' || a.cate === this.activeCate
					if (!cateOk) return false
					if (!kw) return true
					const hay = `${a.name} ${a.desc} ${a.cateName} ${(a.tags || []).join(' ')}`
					return hay.indexOf(kw) > -1
				})
			}
		},
		methods: {
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
			goChat(agent) {
				uni.navigateTo({
					url: `/pages/ai/chat?agentId=${encodeURIComponent(agent.id)}&title=${encodeURIComponent(agent.name)}`
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
		background: linear-gradient(135deg, var(--view-main-start) 0%, var(--view-main-over) 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4rpx 10rpx rgba(241, 165, 92, 0.3);
	}

	.searchBtnText {
		font-size: 26rpx;
		font-weight: 700;
		color: #fff;
	}

	.cateBar {
		margin-top: 24rpx;
		width: 100%;
		white-space: nowrap;
	}

	.cateInner {
		display: flex;
		gap: 32rpx;
		padding: 0 8rpx 12rpx;
	}

	.cateItem {
		position: relative;
		padding: 10rpx 0;
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
		gap: 20rpx;
	}

	.agentCard {
		width: calc((100% - 20rpx) / 2);
		position: relative;
		padding: 24rpx;
		border-radius: 24rpx;
		background: rgba(255, 255, 255, 0.9);
		backdrop-filter: blur(10px);
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
		display: flex;
		flex-direction: column;
	}

	.agentCard:active {
		transform: scale(0.98);
		transition: transform 0.1s;
	}

	.cardHeader {
		display: flex;
		align-items: flex-start;
		gap: 16rpx;
		margin-bottom: 16rpx;
	}

	.agentIcon {
		width: 80rpx;
		height: 80rpx;
		border-radius: 20rpx;
		background: linear-gradient(135deg, rgba(241, 165, 92, 0.15) 0%, rgba(241, 165, 92, 0.05) 100%);
		border: 1rpx solid rgba(241, 165, 92, 0.2);
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.agentIconText {
		font-size: 36rpx;
		font-weight: 900;
		color: #a95608;
	}

	.agentMeta {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		height: 80rpx;
		padding: 2rpx 0;
	}

	.agentName {
		font-size: 30rpx;
		font-weight: 800;
		color: #1f2329;
		line-height: 1.2;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.agentTag {
		display: inline-flex;
	}

	.agentTagText {
		font-size: 20rpx;
		color: #a95608;
		background: rgba(241, 165, 92, 0.1);
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		font-weight: 600;
	}
	
	.enterIcon {
		position: absolute;
		top: 24rpx;
		right: 24rpx;
		opacity: 0.3;
	}
	
	.enterIcon .iconfont {
		font-size: 28rpx;
	}

	.agentDesc {
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.6);
		line-height: 1.5;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		height: 72rpx; /* fixed height for alignment */
		margin-bottom: 16rpx;
	}

	.agentFoot {
		margin-top: auto;
		display: flex;
		gap: 8rpx;
		flex-wrap: wrap;
	}

	.miniChip {
		padding: 4rpx 10rpx;
		border-radius: 8rpx;
		background: #f6f7f8;
	}

	.miniChipText {
		font-size: 20rpx;
		color: rgba(31, 35, 41, 0.6);
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
