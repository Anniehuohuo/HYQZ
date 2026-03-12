<template>
	<view class="page" :style="[colorStyle, pagePad]">
		<!-- #ifdef MP || APP-PLUS -->
		<view class="sys-head">
			<view class="sys-bar" :style="{ height: sysHeight }"></view>
			<!-- #ifdef MP -->
			<view class="sys-title">论坛</view>
			<!-- #endif -->
			<view class="bg"></view>
		</view>
		<!-- #endif -->

		<view class="hero">
			<view class="topTabs">
				<view class="topTab" :class="{ active: activeGroup === 'manage' }" @tap="setActiveGroup('manage')">
					<text class="topTabText">我管理的</text>
					<view class="topTabLine" v-if="activeGroup === 'manage'"></view>
				</view>
				<view class="topTab" :class="{ active: activeGroup === 'discover' }" @tap="setActiveGroup('discover')">
					<text class="topTabText">发现家长圈</text>
					<view class="topTabLine" v-if="activeGroup === 'discover'"></view>
				</view>
			</view>

			<view class="filters">
				<picker mode="selector" :range="sortOptions" range-key="label" @change="onSortChange">
					<view class="filterPill">
						<text class="filterText">{{ currentSortLabel }}</text>
						<text class="filterArrow">▼</text>
					</view>
				</picker>
				<picker mode="selector" :range="categoryOptions" range-key="label" @change="onCategoryChange">
					<view class="filterPill">
						<text class="filterText">{{ currentCategoryLabel }}</text>
						<text class="filterArrow">▼</text>
					</view>
				</picker>
			</view>
		</view>

		<view class="list">
			<view class="post" v-for="(p, index) in displayedPosts" :key="p.id">
				<view class="postHeader">
					<view class="avatar" :style="{ background: getAvatarColor(index) }" @tap="openPost(p)">
						<text class="avatarTxt">{{ p.authorInitial || 'A' }}</text>
					</view>
					<view class="userInfo" @tap="openPost(p)">
						<text class="userName">{{ p.author }}</text>
						<text class="postTime">{{ p.time }}</text>
					</view>
					<view class="more" v-if="isMinePost(p)" @tap.stop="openPostActions(p)">
						<text class="moreText">···</text>
					</view>
				</view>
				
				<view class="postBody">
					<text class="postTitle" @tap="openPost(p)">{{ p.title }}</text>
					<text class="postDesc" @tap="openPost(p)">{{ p.desc }}</text>
				</view>
				
				<view class="postFooter" @tap.stop>
					<view class="tagPill" v-if="p.tag">
						<text class="tagText"># {{ p.tag }}</text>
					</view>
					<view class="stats" @tap.stop>
						<view class="statItem">
							<text class="iconfont icon-liulan"></text>
							<text class="statNum">{{ p.views || 0 }}</text>
						</view>
						<view class="statItem">
							<text class="iconfont icon-pinglun"></text>
							<text class="statNum">{{ p.comments }}</text>
						</view>
						<!-- #ifdef MP-WEIXIN -->
						<button class="statItem likeBtn" :class="{ active: !!p.liked }" hover-class="none" @tap.stop.prevent="toggleLike(p)">
						<!-- #endif -->
						<!-- #ifndef MP-WEIXIN -->
						<view class="statItem" :class="{ active: !!p.liked }" @tap.stop.prevent="toggleLike(p)">
						<!-- #endif -->
							<text class="iconfont" :class="p.liked ? 'icon-yidianzan' : 'icon-dianzan'"></text>
							<text class="statNum">{{ p.likes }}</text>
						<!-- #ifdef MP-WEIXIN -->
						</button>
						<!-- #endif -->
						<!-- #ifndef MP-WEIXIN -->
						</view>
						<!-- #endif -->
					</view>
				</view>
			</view>

			<view class="empty" v-if="displayedPosts.length === 0">
				<text class="emptyTitle">暂时没有内容</text>
				<text class="emptyDesc">{{ activeGroup === 'manage' ? '还没有创建帖子' : '先看看其它内容，或发一条问题' }}</text>
			</view>
		</view>

		<view class="fab" @tap="createPost">
			<text class="iconfont icon-fabu"></text>
			<text class="fabText">发帖</text>
		</view>
		<pageFooter @newDataStatus="newDataStatus" v-show="showBar"></pageFooter>
	</view>
</template>

<script>
	let sysHeight = '0px'
	try {
		const sysInfo = uni.getSystemInfoSync() || {}
		sysHeight = (sysInfo.statusBarHeight || 0) + 'px'
	} catch (e) {}
	import colors from '@/mixins/color.js'
	import pageFooter from '@/components/pageFooter/index.vue'
	import Cache from '@/utils/cache'
	import {
		toLogin
	} from '@/libs/login.js'
	import {
		getForumPosts,
		getForumMyPosts,
		deleteForumPost,
		toggleForumPostLike,
		siteConfig
	} from '@/api/api.js'
	import {
		LOGIN_STATUS,
		UID,
		USER_INFO
	} from '@/config/cache'

	const FORUM_TABS = [{
		key: 'kinder',
		name: '幼小段'
	}, {
		key: 'upper_primary',
		name: '小学高年级'
	}, {
		key: 'teen',
		name: '青少年'
	}, {
		key: 'parent_growth',
		name: '父母个人成长'
	}, {
		key: 'mini_program_suggest',
		name: '小程序建议'
	}]
	const FORUM_TAB_NAME = FORUM_TABS.reduce((acc, t) => {
		acc[t.key] = t.name
		return acc
	}, {})

	function parseJson(value, fallback) {
		if (!value) return fallback
		if (typeof value === 'string') {
			try {
				return JSON.parse(value)
			} catch (e) {
				return fallback
			}
		}
		return value
	}

	function formatTime(ts) {
		let v = ts
		if (v === undefined || v === null || v === '' || v === false) return ''
		if (typeof v === 'string') {
			const s = v.trim()
			if (s && !/^\d+$/.test(s)) v = s.replace(/-/g, '/')
		}
		let n = Number(v)
		if (!Number.isFinite(n)) return ''
		if (n > 1e12) n = Math.floor(n / 1000)
		const d = new Date(n * 1000)
		const pad = (n) => String(n).padStart(2, '0')
		return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
	}

	function toUnixSeconds(v) {
		if (v === undefined || v === null || v === '' || v === false) return 0
		if (typeof v === 'number') return v > 1e12 ? Math.floor(v / 1000) : v
		if (typeof v === 'string') {
			const s = v.trim()
			if (s === '') return 0
			if (/^\d+$/.test(s)) {
				const n = Number(s)
				return n > 1e12 ? Math.floor(n / 1000) : n
			}
			const ms = Date.parse(s.replace(/-/g, '/'))
			return Number.isFinite(ms) ? Math.floor(ms / 1000) : 0
		}
		return 0
	}

	function getInitial(name) {
		const s = (name === undefined || name === null) ? '' : String(name).trim()
		return s ? s.slice(0, 1) : 'A'
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
				sysHeight: sysHeight,
				activeGroup: 'discover',
				sortKey: 'default',
				categoryKey: 'all',
				posts: [],
				page: 1,
				limit: 20,
				innerActionAt: 0,
				innerActionPostId: 0,
				likePendingMap: {},
				forumEnabled: true,
				forumChecked: false
			}
		},
		onShow() {
			this.ensureForumEnabled().then((ok) => {
				if (ok) this.loadPosts()
			})
		},
		computed: {
			sortOptions() {
				return [
					{ key: 'default', label: '综合排序' },
					{ key: 'new', label: '最新发布' },
					{ key: 'hot', label: '最热' },
					{ key: 'comment', label: '最多评论' }
				]
			},
			categoryOptions() {
				return [{ key: 'all', label: '全部' }].concat(FORUM_TABS.map(t => ({ key: t.key, label: t.name })))
			},
			currentSortLabel() {
				const found = this.sortOptions.find(o => o.key === this.sortKey)
				return found ? found.label : '综合排序'
			},
			currentCategoryLabel() {
				const found = this.categoryOptions.find(o => o.key === this.categoryKey)
				return found ? found.label : '全部'
			},
			currentUser() {
				const uidValue = Cache.get(UID) || 0
				const userRaw = Cache.get(USER_INFO)
				const user = parseJson(userRaw, {})
				const author = (user && (user.nickname || user.real_name)) ? (user.nickname || user.real_name) : (uidValue ? `用户${uidValue}` : '游客')
				return {
					uidValue,
					author
				}
			},
			pagePad() {
				if (this.isFooter) {
					return {
						paddingBottom: `${this.pdHeight * 2 + 120}rpx`
					}
				}
				return {}
			},
			displayedPosts() {
				let list = Array.isArray(this.posts) ? this.posts.slice() : []
				if (this.categoryKey !== 'all') {
					list = list.filter(p => p && p.tab === this.categoryKey)
				}
				if (this.sortKey === 'hot') {
					return list.sort((a, b) => {
						const av = (Number(a.views) || 0) + (Number(a.likes) || 0) + (Number(a.comments) || 0)
						const bv = (Number(b.views) || 0) + (Number(b.likes) || 0) + (Number(b.comments) || 0)
						return bv - av
					})
				}
				if (this.sortKey === 'comment') {
					return list.sort((a, b) => (Number(b.comments) || 0) - (Number(a.comments) || 0))
				}
				return list.sort((a, b) => toUnixSeconds(b.add_time || b.createdAtTs || b.createdAt) - toUnixSeconds(a.add_time || a.createdAtTs || a.createdAt))
			}
		},
		methods: {
			ensureForumEnabled() {
				if (this.forumChecked) return Promise.resolve(!!this.forumEnabled)
				return siteConfig().then((res) => {
					const d = res && res.data ? res.data : {}
					const enabled = Number(d.forum_enabled || 0) === 1
					this.forumEnabled = enabled
					this.forumChecked = true
					if (!enabled) {
						uni.redirectTo({
							url: '/pages/forum/closed'
						})
					}
					return enabled
				}).catch(() => {
					this.forumEnabled = false
					this.forumChecked = true
					uni.redirectTo({
						url: '/pages/forum/closed'
					})
					return false
				})
			},
			noop() {},
			async loadPosts() {
				try {
					let res
					if (this.activeGroup === 'manage') {
						res = await getForumMyPosts({
							page: this.page,
							limit: this.limit
						})
					} else {
						const params = {
							page: this.page,
							limit: this.limit
						}
						if (this.categoryKey !== 'all') params.tab = this.categoryKey
						res = await getForumPosts(params)
					}
					const data = res && res.data ? res.data : {}
					const list = Array.isArray(data.list) ? data.list : []
					this.posts = list.map((p) => ({
						...p,
						tag: FORUM_TAB_NAME[p.tab] || '',
						time: p && p.time ? p.time : formatTime((p && (p.add_time || p.createdAtTs || p.createdAt)) || 0),
						liked: !!(p && (p.liked !== undefined ? p.liked : (p.is_like !== undefined ? p.is_like : p.isLike)))
					}))
				} catch (e) {
					this.posts = []
					uni.showToast({
						title: typeof e === 'string' ? e : '加载失败',
						icon: 'none'
					})
				}
			},
			setActiveGroup(groupKey) {
				if (this.activeGroup === groupKey) return
				if (groupKey === 'manage' && !Cache.get(LOGIN_STATUS)) {
					toLogin()
					return
				}
				this.activeGroup = groupKey
				this.page = 1
				this.loadPosts()
			},
			onSortChange(e) {
				const idx = Number(e && e.detail ? e.detail.value : 0) || 0
				const opt = this.sortOptions[idx]
				this.sortKey = opt && opt.key ? opt.key : 'default'
			},
			onCategoryChange(e) {
				const idx = Number(e && e.detail ? e.detail.value : 0) || 0
				const opt = this.categoryOptions[idx]
				this.categoryKey = opt && opt.key ? opt.key : 'all'
				this.page = 1
				if (this.activeGroup === 'discover') this.loadPosts()
			},
			newDataStatus(val, num) {
				this.isFooter = !!val
				this.showBar = !!val
				this.pdHeight = num || 0
			},
			getAvatarColor(index) {
				const colors = ['#FF9A9E', '#FECFEF', '#a18cd1', '#fbc2eb', '#fad0c4', '#ffecd2'];
				return colors[index % colors.length];
			},
			isMinePost(p) {
				const user = this.currentUser || {
					uidValue: 0,
					author: ''
				}
				if (!p) return false
				if (p.authorUid !== undefined && p.authorUid !== null && user.uidValue) return String(p.authorUid) === String(user.uidValue)
				if (p.author) return String(p.author) === String(user.author)
				return false
			},
			async toggleLike(p) {
				if (!p || !p.id) return
				if (!Cache.get(LOGIN_STATUS)) {
					toLogin()
					return
				}
				this.innerActionAt = Date.now()
				this.innerActionPostId = Number(p.id) || 0
				const pid = String(p.id)
				if (this.likePendingMap && this.likePendingMap[pid]) return
				this.$set(this.likePendingMap, pid, true)
				const prevLiked = !!p.liked
				const prevLikes = Number(p.likes) || 0
				const optimisticLiked = !prevLiked
				const optimisticLikes = Math.max(0, prevLikes + (optimisticLiked ? 1 : -1))
				this.posts = this.posts.map((x) => {
					if (String(x.id) !== pid) return x
					return {
						...x,
						liked: optimisticLiked,
						likes: optimisticLikes
					}
				})
				try {
					const res = await toggleForumPostLike(p.id)
					const data = res && res.data ? res.data : {}
					const liked = !!data.liked
					const likes = data.likes !== undefined ? data.likes : (p.likes || 0)
					const views = data.views !== undefined ? data.views : (p.views || 0)
					const comments = data.comments !== undefined ? data.comments : (p.comments || 0)
					this.posts = this.posts.map((x) => {
						if (String(x.id) !== String(p.id)) return x
						return {
							...x,
							liked,
							likes,
							views,
							comments
						}
					})
				} catch (e) {
					this.posts = this.posts.map((x) => {
						if (String(x.id) !== pid) return x
						return {
							...x,
							liked: prevLiked,
							likes: prevLikes
						}
					})
					const msg = (e && typeof e === 'object' && e.msg) ? e.msg : e
					uni.showToast({
						title: typeof msg === 'string' ? msg : '操作失败',
						icon: 'none'
					})
				} finally {
					this.$delete(this.likePendingMap, pid)
				}
			},
			async deletePost(p) {
				await deleteForumPost(p.id)
			},
			openPostActions(p) {
				this.innerActionAt = Date.now()
				this.innerActionPostId = Number(p && p.id ? p.id : 0) || 0
				uni.showActionSheet({
					itemList: ['编辑', '删除'],
					success: (res) => {
						if (res.tapIndex === 0) {
							uni.navigateTo({
								url: `/pages/forum/publish?id=${encodeURIComponent(p.id)}`
							})
						}
						if (res.tapIndex === 1) {
							uni.showModal({
								title: '确认删除',
								content: '删除后不可恢复，确定要删除这个帖子吗？',
								confirmText: '删除',
								confirmColor: '#E14C4C',
								success: (r) => {
									if (!r.confirm) return
									this.deletePost(p).then(() => {
										this.loadPosts()
										uni.showToast({
											title: '已删除',
											icon: 'success'
										})
									}).catch((e) => {
										uni.showToast({
											title: typeof e === 'string' ? e : '删除失败',
											icon: 'none'
										})
									})
								}
							})
						}
					}
				})
			},
			openPost(p) {
				const now = Date.now()
				if (now - (this.innerActionAt || 0) < 900 && (Number(p && p.id ? p.id : 0) || 0) === (this.innerActionPostId || 0)) {
					return
				}
				if (p && p.id) {
					Cache.set(`FORUM_POST_PREVIEW_${String(p.id)}`, p, 300)
				}
				uni.navigateTo({
					url: `/pages/forum/detail?id=${encodeURIComponent(p.id)}`
				});
			},
			createPost() {
				uni.navigateTo({
					url: `/pages/forum/publish`
				});
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
		padding-bottom: 140rpx;
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

	.hero {
		padding: 20rpx 24rpx 10rpx;
	}

	.topTabs {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 8rpx;
	}

	.topTab {
		position: relative;
		padding: 10rpx 0 16rpx;
		flex: 1;
		text-align: center;
	}

	.topTabText {
		font-size: 30rpx;
		color: rgba(31, 35, 41, 0.65);
		font-weight: 600;
	}

	.topTab.active .topTabText {
		color: var(--view-theme);
		font-weight: 800;
	}

	.topTabLine {
		position: absolute;
		left: 50%;
		bottom: 6rpx;
		transform: translateX(-50%);
		width: 56rpx;
		height: 6rpx;
		border-radius: 99rpx;
		background: var(--view-theme);
	}

	.filters {
		display: flex;
		align-items: center;
		gap: 16rpx;
		padding: 14rpx 8rpx 0;
	}

	.filterPill {
		display: flex;
		align-items: center;
		gap: 10rpx;
		padding: 12rpx 18rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.9);
		border: 1rpx solid rgba(0, 0, 0, 0.06);
	}

	.filterText {
		font-size: 26rpx;
		color: #1f2329;
	}

	.filterArrow {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.5);
		transform: translateY(-1rpx);
	}

	.list {
		padding: 10rpx 24rpx 28rpx;
	}

	.post {
		background: rgba(255, 255, 255, 0.9);
		border-radius: 24rpx;
		padding: 24rpx;
		margin-bottom: 20rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
		backdrop-filter: blur(10px);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
	}

	.postHeader {
		display: flex;
		align-items: center;
		gap: 16rpx;
		margin-bottom: 16rpx;
	}

	.avatar {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		font-weight: bold;
		font-size: 32rpx;
		box-shadow: 0 4rpx 8rpx rgba(0, 0, 0, 0.1);
	}

	.userInfo {
		flex: 1;
		display: flex;
		flex-direction: column;
	}

	.userName {
		font-size: 28rpx;
		font-weight: 700;
		color: #1f2329;
	}

	.postTime {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.4);
		margin-top: 2rpx;
	}

	.more {
		width: 64rpx;
		height: 64rpx;
		border-radius: 16rpx;
		background: rgba(31, 35, 41, 0.06);
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.moreText {
		font-size: 30rpx;
		font-weight: 900;
		color: rgba(31, 35, 41, 0.7);
		letter-spacing: 2rpx;
	}

	.postBody {
		margin-bottom: 20rpx;
		position: relative;
		z-index: 1;
	}

	.postTitle {
		display: block;
		font-size: 32rpx;
		font-weight: 800;
		color: #1f2329;
		margin-bottom: 8rpx;
		line-height: 1.4;
	}

	.postDesc {
		display: block;
		font-size: 26rpx;
		color: rgba(31, 35, 41, 0.7);
		line-height: 1.6;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 3;
		overflow: hidden;
	}

	.postFooter {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 12rpx;
		position: relative;
		z-index: 2;
		background: rgba(255, 255, 255, 0.001);
	}

	.tagPill {
		padding: 6rpx 16rpx;
		background: rgba(241, 165, 92, 0.1);
		border-radius: 8rpx;
	}

	.tagText {
		font-size: 22rpx;
		color: #a95608;
		font-weight: 600;
	}

	.stats {
		display: flex;
		align-items: center;
		gap: 32rpx;
	}

	.statItem {
		display: flex;
		align-items: center;
		gap: 6rpx;
		color: rgba(31, 35, 41, 0.5);
		padding: 10rpx;
		position: relative;
		z-index: 1;
		border-radius: 12rpx;
		min-height: 56rpx;
		min-width: 56rpx;
		background: rgba(255, 255, 255, 0.001);
	}

	.statItem.active {
		color: #e93323;
	}

	.likeBtn {
		margin: 0;
		border: 0;
		background: transparent;
		line-height: normal;
		color: inherit;
	}
	
	.likeBtn::after {
		border: none;
	}

	.statItem .iconfont {
		font-size: 28rpx;
	}

	.statNum {
		font-size: 24rpx;
	}

	.fab {
		position: fixed;
		right: 32rpx;
		bottom: 160rpx;
		height: 96rpx;
		padding: 0 40rpx;
		border-radius: 48rpx;
		background: linear-gradient(135deg, #1f2329 0%, #3a3f4a 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12rpx;
		box-shadow: 0 12rpx 32rpx rgba(31, 35, 41, 0.3);
		transition: all 0.2s;
	}

	.fab:active {
		transform: scale(0.96);
	}

	.fab .iconfont {
		color: #fff;
		font-size: 36rpx;
	}

	.fabText {
		font-size: 30rpx;
		font-weight: 700;
		color: #fff;
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
</style>
