<template>
	<view class="page" :style="[colorStyle]">
		<scroll-view class="scroll" scroll-y="true" :style="{ height: scrollHeight }">
			<view class="wrap">
				<view class="card" v-if="post">
					<view class="head">
						<view class="avatar" :style="{ background: avatarColor }">
							<text class="avatarTxt">{{ postAuthorInitial }}</text>
						</view>
						<view class="meta">
							<text class="author">{{ post.author }}</text>
							<text class="time">{{ post.time }}</text>
						</view>
						<view class="tagPill" v-if="post.tag">
							<text class="tagText"># {{ post.tag }}</text>
						</view>
						<view class="more" v-if="isMine" @click.stop="openPostActions">
							<text class="moreText">···</text>
						</view>
					</view>

					<text class="title">{{ post.title }}</text>
					<text class="content">{{ post.content }}</text>

					<view class="stats">
						<view class="statItem">
							<text class="iconfont icon-liulan"></text>
							<text class="statNum">{{ post.views || 0 }}</text>
						</view>
						<view class="statItem">
							<text class="iconfont icon-pinglun"></text>
							<text class="statNum">{{ post.comments || 0 }}</text>
						</view>
						<view class="statItem" :class="{ active: liked }" @click.stop="toggleLike">
							<text class="iconfont" :class="liked ? 'icon-yidianzan' : 'icon-dianzan'"></text>
							<text class="statNum">{{ post.likes || 0 }}</text>
						</view>
					</view>
				</view>

				<view class="empty" v-else>
					<text class="emptyTitle">帖子不存在或已删除</text>
				</view>

				<view class="commentCard" v-if="post">
					<text class="commentTitle">评论</text>
					<view class="commentItem" v-for="c in comments" :key="c.id" @longpress="openCommentActions(c)">
						<view class="commentHead">
							<view class="commentAvatar" :style="{ background: c.avatarColor }">
								<text class="commentAvatarTxt">{{ c.authorInitial || 'A' }}</text>
							</view>
							<view class="commentMeta">
								<text class="commentAuthor">{{ c.author }}</text>
								<text class="commentTime">{{ c.time }}</text>
							</view>
						</view>
						<text class="commentContent">{{ c.content }}</text>
					</view>
					<view class="emptyComment" v-if="comments.length === 0">
						<text class="emptyCommentText">还没有评论，先说两句吧</text>
					</view>
				</view>
			</view>
		</scroll-view>

		<view class="composer" v-if="post">
			<view class="composerInner">
				<input class="input" v-model="draft" :adjust-position="true" confirm-type="send" placeholder="写下你的评论…" @confirm="sendComment" />
				<view class="send" :class="{ disabled: !draftTrim }" @click="sendComment">
					<text class="iconfont icon-fasong sendIcon"></text>
				</view>
			</view>
			<view class="safePad"></view>
		</view>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import Cache from '@/utils/cache'
	import {
		toLogin
	} from '@/libs/login.js'
	import {
		getForumPostDetail,
		getForumPostComments,
		toggleForumPostLike,
		createForumComment,
		deleteForumPost,
		deleteForumComment
	} from '@/api/api.js'
	import {
		LOGIN_STATUS,
		UID,
		USER_INFO
	} from '@/config/cache'

	const FORUM_TAB_NAME = {
		kinder: '幼小段',
		upper_primary: '小学高年级',
		teen: '青少年',
		parent_growth: '父母个人成长',
		mini_program_suggest: '小程序建议'
	}

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

	function uid() {
		return `${Date.now()}_${Math.random().toString(16).slice(2)}`
	}

	function safeDecode(v) {
		if (typeof v !== 'string') return v
		try {
			return decodeURIComponent(v)
		} catch (e) {
			return v
		}
	}

	function parseUrlParams(param, k, p) {
		if (typeof param !== 'string') return {}
		k = k ? k : '&'
		p = p ? p : '='
		let value = {}
		if (param.indexOf(k) !== -1) {
			param = param.split(k)
			for (let i = 0; i < param.length; i++) {
				if (param[i].indexOf(p) !== -1) {
					const item = param[i].split(p)
					value[item[0]] = item[1]
				}
			}
		} else if (param.indexOf(p) !== -1) {
			const item = param.split(p)
			value[item[0]] = item[1]
		} else {
			return param
		}
		return value
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

	function formatTime(ts) {
		const n = toUnixSeconds(ts)
		if (!n) return ''
		const d = new Date(n * 1000)
		const pad = (n) => String(n).padStart(2, '0')
		return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
	}

	function pickAvatarColor(seed) {
		const colors = ['#FF9A9E', '#FECFEF', '#a18cd1', '#fbc2eb', '#fad0c4', '#ffecd2']
		return colors[seed % colors.length]
	}

	function getInitial(name) {
		const s = (name === undefined || name === null) ? '' : String(name).trim()
		return s ? s.slice(0, 1) : 'A'
	}

	function getErrorMsg(e) {
		if (e && typeof e === 'object' && e.msg) return String(e.msg)
		if (typeof e === 'string') return e
		return ''
	}

	export default {
		mixins: [colors],
		data() {
			return {
				postId: '',
				post: null,
				comments: [],
				draft: '',
				liked: false,
				shareCover: '',
				likePending: false
			}
		},
		computed: {
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
			draftTrim() {
				return (this.draft || '').trim()
			},
			postAuthorInitial() {
				return this.post ? getInitial(this.post.author) : 'A'
			},
			isMine() {
				if (!this.post) return false
				if (this.post.isMine !== undefined && this.post.isMine !== null) return !!this.post.isMine
				const {
					uidValue,
					author
				} = this.currentUser
				if (this.post.authorUid !== undefined && this.post.authorUid !== null && uidValue) return String(this.post.authorUid) === String(uidValue)
				if (this.post.author) return String(this.post.author) === String(author)
				return false
			},
			avatarColor() {
				const seed = String(this.postId || '').split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0)
				return pickAvatarColor(seed)
			},
			scrollHeight() {
				return 'calc(100vh - 170rpx)'
			}
		},
		onLoad(options) {
			let postId = ''
			if (options) {
				postId = options.id || options.postId || options.post_id || ''
			}
			postId = safeDecode(postId) || ''
			//#ifdef MP
			if (!postId && options && options.scene) {
				const scene = safeDecode(options.scene)
				let value = {}
				if (this.$util && this.$util.getUrlParams) value = this.$util.getUrlParams(scene)
				else value = parseUrlParams(scene)
				if (typeof value === 'object' && value) postId = value.id || value.postId || value.post_id || ''
				else if (typeof value === 'string') postId = value
				postId = safeDecode(postId) || ''
			}
			//#endif
			this.postId = postId ? String(postId) : ''
			if (this.postId) {
				const previewRaw = Cache.get(`FORUM_POST_PREVIEW_${this.postId}`)
				const preview = parseJson(previewRaw, null)
				if (preview && typeof preview === 'object') {
					this.post = {
						...preview,
						tag: FORUM_TAB_NAME[preview.tab] || '',
						time: preview && preview.time ? preview.time : formatTime((preview && (preview.add_time || preview.createdAtTs || preview.createdAt)) || 0)
					}
					this.liked = !!preview.liked
				}
			}
			this.loadPost()
			this.loadComments()
			//#ifdef MP
			uni.getImageInfo({
				src: '/static/images/jf-head.png',
				success: (res) => {
					this.shareCover = res.path
				},
				fail: () => {
					this.shareCover = '/static/images/jf-head.png'
				}
			})
			//#endif
		},
		//#ifdef MP
		onShareAppMessage() {
			if (!this.post) return {}
			return {
				title: this.post.title || '论坛',
				path: `/pages/forum/detail?id=${encodeURIComponent(this.postId)}`,
				imageUrl: this.shareCover || '/static/images/jf-head.png'
			}
		},
		onShareTimeline() {
			if (!this.post) return {}
			return {
				title: this.post.title || '论坛',
				query: {
					id: this.postId
				},
				imageUrl: this.shareCover || '/static/images/jf-head.png'
			}
		},
		//#endif
		methods: {
			async loadPost() {
				if (!this.postId) {
					this.post = null
					uni.showToast({
						title: '缺少帖子ID',
						icon: 'none'
					})
					return
				}
				try {
					const res = await getForumPostDetail(this.postId)
					const post = res && res.data ? res.data : null
					if (!post) {
						if (!this.post) this.post = null
						uni.showToast({
							title: '帖子不存在或已删除',
							icon: 'none'
						})
						return
					}
					this.post = {
						...post,
						tag: FORUM_TAB_NAME[post.tab] || '',
						time: post && post.time ? post.time : formatTime((post && (post.add_time || post.createdAtTs || post.createdAt)) || 0)
					}
					this.liked = !!post.liked
					Cache.set(`FORUM_POST_PREVIEW_${this.postId}`, this.post, 300)
				} catch (e) {
					if (!this.post) this.post = null
					const msg = getErrorMsg(e)
					uni.showToast({
						title: msg || '加载失败',
						icon: 'none'
					})
				}
			},
			async loadComments() {
				if (!this.postId) {
					this.comments = []
					return
				}
				try {
					const res = await getForumPostComments(this.postId, {
						page: 1,
						limit: 50
					})
					const data = res && res.data ? res.data : {}
					const list = Array.isArray(data.list) ? data.list : []
					this.comments = list.map((c) => ({
						...c,
						time: c && c.time ? c.time : formatTime((c && (c.add_time || c.createdAtTs || c.createdAt)) || 0),
						authorInitial: c && c.authorInitial ? c.authorInitial : getInitial(c ? c.author : ''),
						avatarColor: pickAvatarColor(String(c && c.author ? c.author : '').split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0))
					}))
				} catch (e) {
					this.comments = []
					const msg = getErrorMsg(e)
					uni.showToast({
						title: msg || '加载失败',
						icon: 'none'
					})
				}
			},
			openPostActions() {
				if (!this.isMine || !this.post) return
				uni.showActionSheet({
					itemList: ['编辑', '删除'],
					success: (res) => {
						if (res.tapIndex === 0) {
							uni.navigateTo({
								url: `/pages/forum/publish?id=${encodeURIComponent(this.postId)}`
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
									deleteForumPost(this.postId).then(() => {
										uni.showToast({
											title: '已删除',
											icon: 'success'
										})
										setTimeout(() => {
											uni.navigateBack()
										}, 250)
									}).catch((e) => {
										const msg = getErrorMsg(e)
										uni.showToast({
											title: msg || '删除失败',
											icon: 'none'
										})
									})
								}
							})
						}
					}
				})
			},
			isMineComment(c) {
				const {
					uidValue,
					author
				} = this.currentUser
				if (c && c.authorUid !== undefined && c.authorUid !== null && uidValue) return String(c.authorUid) === String(uidValue)
				if (c && c.author) return String(c.author) === String(author)
				return false
			},
			openCommentActions(c) {
				if (!this.post || !c || !this.isMineComment(c)) return
				uni.showActionSheet({
					itemList: ['删除'],
					success: () => {
						uni.showModal({
							title: '确认删除',
							content: '确定要删除这条评论吗？',
							confirmText: '删除',
							confirmColor: '#E14C4C',
							success: (r) => {
								if (!r.confirm) return
								deleteForumComment(c.id).then(() => {
									this.comments = this.comments.filter((x) => String(x.id) !== String(c.id))
									if (this.post) this.post = {
										...this.post,
										comments: Math.max(0, (this.post.comments || 0) - 1)
									}
									uni.showToast({
										title: '已删除',
										icon: 'success'
									})
								}).catch((e) => {
									const msg = getErrorMsg(e)
									uni.showToast({
										title: msg || '删除失败',
										icon: 'none'
									})
								})
							}
						})
					}
				})
			},
			async toggleLike() {
				if (!this.post) return
				if (!Cache.get(LOGIN_STATUS)) {
					toLogin()
					return
				}
				if (this.likePending) return
				this.likePending = true
				const prevLiked = !!this.liked
				const prevLikes = Number(this.post.likes) || 0
				const optimisticLiked = !prevLiked
				const optimisticLikes = Math.max(0, prevLikes + (optimisticLiked ? 1 : -1))
				this.liked = optimisticLiked
				this.post = {
					...this.post,
					likes: optimisticLikes
				}
				try {
					const res = await toggleForumPostLike(this.postId)
					const data = res && res.data ? res.data : {}
					this.liked = !!data.liked
					this.post = {
						...this.post,
						likes: data.likes !== undefined ? data.likes : this.post.likes,
						comments: data.comments !== undefined ? data.comments : this.post.comments,
						views: data.views !== undefined ? data.views : this.post.views
					}
				} catch (e) {
					this.liked = prevLiked
					this.post = {
						...this.post,
						likes: prevLikes
					}
					const msg = getErrorMsg(e)
					uni.showToast({
						title: msg || '操作失败',
						icon: 'none'
					})
				} finally {
					this.likePending = false
				}
			},
			async sendComment() {
				if (!this.post || !this.draftTrim) return
				try {
					await createForumComment(this.postId, {
						content: this.draftTrim
					})
					this.draft = ''
					this.post = {
						...this.post,
						comments: (this.post.comments || 0) + 1
					}
					await this.loadComments()
				} catch (e) {
					const msg = getErrorMsg(e)
					uni.showToast({
						title: msg || '评论失败',
						icon: 'none'
					})
				}
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

	.scroll {
		position: relative;
		z-index: 1;
	}

	.wrap {
		padding: 20rpx 24rpx 220rpx;
	}

	.card {
		background: rgba(255, 255, 255, 0.92);
		border-radius: 24rpx;
		padding: 24rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
		backdrop-filter: blur(10px);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
	}

	.head {
		display: flex;
		align-items: center;
		gap: 16rpx;
		margin-bottom: 18rpx;
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
		flex: none;
	}

	.avatarTxt {
		line-height: 1;
	}

	.meta {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.author {
		font-size: 28rpx;
		font-weight: 800;
		color: #1f2329;
	}

	.time {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.4);
		margin-top: 2rpx;
	}

	.tagPill {
		padding: 6rpx 16rpx;
		background: rgba(241, 165, 92, 0.1);
		border-radius: 8rpx;
		flex: none;
	}

	.tagText {
		font-size: 22rpx;
		color: #a95608;
		font-weight: 700;
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

	.title {
		display: block;
		font-size: 34rpx;
		font-weight: 900;
		color: #1f2329;
		line-height: 1.4;
	}

	.content {
		margin-top: 14rpx;
		display: block;
		font-size: 28rpx;
		line-height: 44rpx;
		color: rgba(31, 35, 41, 0.78);
		white-space: pre-wrap;
	}

	.stats {
		margin-top: 18rpx;
		display: flex;
		align-items: center;
		gap: 32rpx;
	}

	.statItem {
		display: flex;
		align-items: center;
		gap: 8rpx;
		color: rgba(31, 35, 41, 0.54);
	}

	.statItem.active {
		color: #e93323;
	}

	.statItem .iconfont {
		font-size: 30rpx;
	}

	.statNum {
		font-size: 26rpx;
	}

	.commentCard {
		margin-top: 20rpx;
		background: rgba(255, 255, 255, 0.92);
		border-radius: 24rpx;
		padding: 24rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
		backdrop-filter: blur(10px);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
	}

	.commentTitle {
		display: block;
		font-size: 28rpx;
		font-weight: 900;
		color: #1f2329;
		margin-bottom: 14rpx;
	}

	.commentItem {
		padding: 16rpx 0;
		border-top: 1rpx solid rgba(0, 0, 0, 0.04);
	}

	.commentItem:first-of-type {
		border-top: none;
		padding-top: 0;
	}

	.commentHead {
		display: flex;
		align-items: center;
		gap: 14rpx;
	}

	.commentAvatar {
		width: 54rpx;
		height: 54rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: none;
	}

	.commentAvatarTxt {
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.9);
		font-weight: 800;
	}

	.commentMeta {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.commentAuthor {
		font-size: 26rpx;
		font-weight: 800;
		color: #1f2329;
	}

	.commentTime {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.42);
		margin-top: 2rpx;
	}

	.commentContent {
		margin-top: 10rpx;
		display: block;
		font-size: 26rpx;
		line-height: 40rpx;
		color: rgba(31, 35, 41, 0.74);
		white-space: pre-wrap;
	}

	.emptyComment {
		padding: 18rpx 0 0;
		text-align: center;
	}

	.emptyCommentText {
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.5);
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
		background: rgba(255, 255, 255, 0.9);
		backdrop-filter: blur(12px);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
		box-shadow: 0rpx 18rpx 46rpx rgba(31, 35, 41, 0.16);
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

	.empty {
		padding: 90rpx 24rpx;
		text-align: center;
	}

	.emptyTitle {
		font-size: 30rpx;
		font-weight: 800;
		color: rgba(31, 35, 41, 0.75);
	}
</style>
