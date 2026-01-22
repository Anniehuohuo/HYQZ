<template>
	<view class="page" :style="[colorStyle]">
		<view class="hero">
			<text class="title">我的论坛</text>
			<view class="seg">
				<view class="segItem" :class="{ active: active === 'posts' }" @click="active = 'posts'">
					<text class="segText">我的帖子</text>
				</view>
				<view class="segItem" :class="{ active: active === 'comments' }" @click="active = 'comments'">
					<text class="segText">我的评论</text>
				</view>
				<view class="segItem" :class="{ active: active === 'drafts' }" @click="active = 'drafts'">
					<text class="segText">草稿箱</text>
				</view>
			</view>
		</view>

		<view class="wrap">
			<view v-if="active === 'posts'">
				<view class="post" v-for="(p, index) in myPosts" :key="p.id" @click="openPost(p)">
					<view class="postHeader">
						<view class="avatar" :style="{ background: getAvatarColor(index) }">
							<text class="avatarTxt">{{ p.authorInitial || 'A' }}</text>
						</view>
						<view class="userInfo">
							<text class="userName">{{ p.author }}</text>
							<text class="postTime">{{ p.time }}</text>
						</view>
					</view>
					<view class="postBody">
						<text class="postTitle">{{ p.title }}</text>
						<text class="postDesc">{{ p.desc }}</text>
					</view>
					<view class="postFooter">
						<view class="tagPill" v-if="p.tag">
							<text class="tagText"># {{ p.tag }}</text>
						</view>
						<view class="actions" @click.stop>
							<view class="actionBtn" @click.stop="editPost(p)">
								<text class="actionText">编辑</text>
							</view>
							<view class="actionBtn danger" @click.stop="deletePost(p)">
								<text class="actionText">删除</text>
							</view>
						</view>
					</view>
				</view>

				<view class="empty" v-if="myPosts.length === 0">
					<text class="emptyTitle">还没有发布帖子</text>
					<text class="emptyDesc">去论坛发一条经验或问题吧</text>
					<view class="primaryBtn" @click="goPublish">
						<text class="primaryBtnText">去发帖</text>
					</view>
				</view>
			</view>

			<view v-else-if="active === 'comments'">
				<view class="commentItem" v-for="c in myComments" :key="c.id">
					<view class="commentTop" @click="openPostById(c.postId)">
						<text class="commentPostTitle">{{ c.postTitle || '帖子' }}</text>
						<text class="commentTime">{{ c.time }}</text>
					</view>
					<text class="commentContent" @click="openPostById(c.postId)">{{ c.content }}</text>
					<view class="commentActions">
						<view class="actionBtn danger" @click="deleteComment(c)">
							<text class="actionText">删除</text>
						</view>
					</view>
				</view>

				<view class="empty" v-if="myComments.length === 0">
					<text class="emptyTitle">还没有评论</text>
					<text class="emptyDesc">去帖子详情里说两句吧</text>
				</view>
			</view>

			<view v-else>
				<view class="draftItem" v-for="d in drafts" :key="d.id" @click="openDraft(d)">
					<view class="draftMain">
						<text class="draftTitle">{{ d.title || '（无标题）' }}</text>
						<text class="draftTime">{{ d.updatedTime }}</text>
					</view>
					<view class="draftOps" @click.stop>
						<view class="actionBtn danger" @click.stop="deleteDraft(d)">
							<text class="actionText">删除</text>
						</view>
					</view>
				</view>

				<view class="empty" v-if="drafts.length === 0">
					<text class="emptyTitle">草稿箱为空</text>
					<text class="emptyDesc">编辑内容时会自动保存草稿</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import {
		getForumMyPosts,
		getForumMyComments,
		getForumDrafts,
		deleteForumPost,
		deleteForumComment,
		deleteForumDraft
	} from '@/api/api.js'

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

	export default {
		mixins: [colors],
		data() {
			return {
				active: 'posts',
				posts: [],
				comments: [],
				drafts: [],
				page: 1,
				limit: 20
			}
		},
		onShow() {
			this.loadActive()
		},
		watch: {
			active() {
				this.loadActive()
			}
		},
		computed: {
			myPosts() {
				return this.posts.slice().sort((a, b) => toUnixSeconds(b.add_time || b.createdAtTs || b.createdAt) - toUnixSeconds(a.add_time || a.createdAtTs || a.createdAt))
			},
			myComments() {
				return this.comments.slice().sort((a, b) => toUnixSeconds(b.add_time || b.createdAtTs || b.createdAt) - toUnixSeconds(a.add_time || a.createdAtTs || a.createdAt))
			}
		},
		methods: {
			loadActive() {
				if (this.active === 'posts') return this.loadMyPosts()
				if (this.active === 'comments') return this.loadMyComments()
				return this.loadDrafts()
			},
			async loadMyPosts() {
				try {
					const res = await getForumMyPosts({
						page: this.page,
						limit: this.limit
					})
					const data = res && res.data ? res.data : {}
					const list = Array.isArray(data.list) ? data.list : []
					this.posts = list.map((p) => ({
						...p,
						tag: FORUM_TAB_NAME[p.tab] || '',
						time: p && p.time ? p.time : formatTime((p && (p.add_time || p.createdAtTs || p.createdAt)) || 0)
					}))
				} catch (e) {
					this.posts = []
					uni.showToast({
						title: typeof e === 'string' ? e : '加载失败',
						icon: 'none'
					})
				}
			},
			async loadMyComments() {
				try {
					const res = await getForumMyComments({
						page: this.page,
						limit: this.limit
					})
					const data = res && res.data ? res.data : {}
					const list = Array.isArray(data.list) ? data.list : []
					this.comments = list.map((c) => ({
						...c,
						time: c && c.time ? c.time : formatTime((c && (c.add_time || c.createdAtTs || c.createdAt)) || 0)
					}))
				} catch (e) {
					this.comments = []
					uni.showToast({
						title: typeof e === 'string' ? e : '加载失败',
						icon: 'none'
					})
				}
			},
			async loadDrafts() {
				try {
					const res = await getForumDrafts({
						page: this.page,
						limit: this.limit
					})
					const data = res && res.data ? res.data : {}
					this.drafts = Array.isArray(data.list) ? data.list : []
				} catch (e) {
					this.drafts = []
					uni.showToast({
						title: typeof e === 'string' ? e : '加载失败',
						icon: 'none'
					})
				}
			},
			getAvatarColor(index) {
				const colors = ['#FF9A9E', '#FECFEF', '#a18cd1', '#fbc2eb', '#fad0c4', '#ffecd2']
				return colors[index % colors.length]
			},
			openPost(p) {
				if (p && p.id) {
					Cache.set(`FORUM_POST_PREVIEW_${String(p.id)}`, p, 300)
				}
				uni.navigateTo({
					url: `/pages/forum/detail?id=${encodeURIComponent(p.id)}`
				})
			},
			openPostById(postId) {
				uni.navigateTo({
					url: `/pages/forum/detail?id=${encodeURIComponent(postId)}`
				})
			},
			editPost(p) {
				uni.navigateTo({
					url: `/pages/forum/publish?id=${encodeURIComponent(p.id)}`
				})
			},
			deletePost(p) {
				uni.showModal({
					title: '确认删除',
					content: '删除后不可恢复，确定要删除这个帖子吗？',
					confirmText: '删除',
					confirmColor: '#E14C4C',
					success: async (res) => {
						if (!res.confirm) return
						try {
							await deleteForumPost(p.id)
							await this.loadMyPosts()
							uni.showToast({
								title: '已删除',
								icon: 'success'
							})
						} catch (e) {
							uni.showToast({
								title: typeof e === 'string' ? e : '删除失败',
								icon: 'none'
							})
						}
					}
				})
			},
			deleteComment(c) {
				uni.showModal({
					title: '确认删除',
					content: '确定要删除这条评论吗？',
					confirmText: '删除',
					confirmColor: '#E14C4C',
					success: async (res) => {
						if (!res.confirm) return
						try {
							await deleteForumComment(c.id)
							await this.loadMyComments()
							uni.showToast({
								title: '已删除',
								icon: 'success'
							})
						} catch (e) {
							uni.showToast({
								title: typeof e === 'string' ? e : '删除失败',
								icon: 'none'
							})
						}
					}
				})
			},
			openDraft(d) {
				uni.navigateTo({
					url: `/pages/forum/publish?draftId=${encodeURIComponent(d.id)}`
				})
			},
			deleteDraft(d) {
				uni.showModal({
					title: '确认删除',
					content: '确定要删除这个草稿吗？',
					confirmText: '删除',
					confirmColor: '#E14C4C',
					success: async (res) => {
						if (!res.confirm) return
						try {
							await deleteForumDraft(d.id)
							await this.loadDrafts()
							uni.showToast({
								title: '已删除',
								icon: 'success'
							})
						} catch (e) {
							uni.showToast({
								title: typeof e === 'string' ? e : '删除失败',
								icon: 'none'
							})
						}
					}
				})
			},
			goPublish() {
				uni.navigateTo({
					url: `/pages/forum/publish`
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

	.hero {
		padding: 24rpx 24rpx 10rpx;
	}

	.title {
		display: block;
		font-size: 34rpx;
		font-weight: 900;
		color: #1f2329;
		margin-bottom: 18rpx;
	}

	.seg {
		display: flex;
		gap: 12rpx;
	}

	.segItem {
		flex: 1;
		height: 72rpx;
		border-radius: 18rpx;
		background: rgba(241, 165, 92, 0.08);
		border: 1rpx solid rgba(241, 165, 92, 0.16);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.segItem.active {
		background: linear-gradient(135deg, rgba(255, 154, 158, 0.22) 0%, rgba(250, 208, 196, 0.22) 100%);
		border-color: rgba(255, 154, 158, 0.35);
	}

	.segText {
		font-size: 26rpx;
		font-weight: 900;
		color: #1f2329;
	}

	.wrap {
		padding: 10rpx 24rpx 40rpx;
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
		flex: none;
	}

	.avatarTxt {
		line-height: 1;
	}

	.userInfo {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.userName {
		font-size: 28rpx;
		font-weight: 800;
		color: #1f2329;
	}

	.postTime {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.4);
		margin-top: 2rpx;
	}

	.postBody {
		margin-bottom: 16rpx;
	}

	.postTitle {
		display: block;
		font-size: 32rpx;
		font-weight: 900;
		color: #1f2329;
		line-height: 1.4;
	}

	.postDesc {
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.6);
		line-height: 1.6;
		margin-top: 8rpx;
	}

	.postFooter {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10rpx;
	}

	.tagPill {
		padding: 6rpx 16rpx;
		background: rgba(241, 165, 92, 0.1);
		border-radius: 8rpx;
	}

	.tagText {
		font-size: 22rpx;
		color: #a95608;
		font-weight: 700;
	}

	.actions,
	.commentActions,
	.draftOps {
		display: flex;
		gap: 10rpx;
	}

	.actionBtn {
		height: 54rpx;
		padding: 0 18rpx;
		border-radius: 14rpx;
		background: rgba(31, 35, 41, 0.06);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.actionBtn.danger {
		background: rgba(225, 76, 76, 0.1);
	}

	.actionText {
		font-size: 24rpx;
		font-weight: 800;
		color: rgba(31, 35, 41, 0.75);
	}

	.actionBtn.danger .actionText {
		color: #E14C4C;
	}

	.commentItem {
		background: rgba(255, 255, 255, 0.92);
		border-radius: 18rpx;
		padding: 18rpx;
		margin-bottom: 16rpx;
		border: 1rpx solid rgba(0, 0, 0, 0.05);
	}

	.commentTop {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12rpx;
	}

	.commentPostTitle {
		font-size: 26rpx;
		font-weight: 900;
		color: #1f2329;
		flex: 1;
		min-width: 0;
	}

	.commentTime {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.45);
		flex: none;
	}

	.commentContent {
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.7);
		line-height: 1.6;
		margin: 10rpx 0 12rpx;
	}

	.draftItem {
		background: rgba(255, 255, 255, 0.92);
		border-radius: 18rpx;
		padding: 18rpx;
		margin-bottom: 16rpx;
		border: 1rpx solid rgba(0, 0, 0, 0.05);
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10rpx;
	}

	.draftMain {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.draftTitle {
		font-size: 28rpx;
		font-weight: 900;
		color: #1f2329;
	}

	.draftTime {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.45);
		margin-top: 6rpx;
	}

	.empty {
		padding: 120rpx 20rpx 20rpx;
		text-align: center;
	}

	.emptyTitle {
		display: block;
		font-size: 30rpx;
		font-weight: 900;
		color: #1f2329;
	}

	.emptyDesc {
		display: block;
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.55);
		margin-top: 12rpx;
	}

	.primaryBtn {
		margin: 22rpx auto 0;
		width: 260rpx;
		height: 78rpx;
		border-radius: 20rpx;
		background: linear-gradient(135deg, #1f2329 0%, #3a3f4a 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 12rpx 32rpx rgba(31, 35, 41, 0.25);
	}

	.primaryBtnText {
		font-size: 28rpx;
		font-weight: 900;
		color: #fff;
	}
</style>
