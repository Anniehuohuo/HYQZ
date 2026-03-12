<template>
	<view class="page" :style="[colorStyle]">
		<view class="wrap">
			<view class="card">
				<text class="label">分区</text>
				<view class="seg">
					<view class="segItem" v-for="t in tabs" :key="t.key" :class="{ active: tab === t.key }" @click="tab = t.key">
						<text class="segText">{{ t.name }}</text>
					</view>
				</view>

				<text class="label">标题</text>
				<input class="titleInput" v-model="title" maxlength="40" placeholder="一句话说清楚你的问题/经验" />

				<text class="label">内容</text>
				<textarea class="contentInput" v-model="content" maxlength="1000" placeholder="把场景写清楚：谁、发生了什么、你怎么做的、你希望得到什么帮助…" />
			</view>
		</view>

		<view class="bottom">
			<view class="btnRow">
				<view class="btn ghost" :class="{ disabled: !canDraft }" @click="saveDraft(true)">
					<text class="btnText ghostText">存草稿</text>
				</view>
				<view class="btn primary" :class="{ disabled: !canSubmit }" @click="submit">
					<text class="btnText">{{ editingPostId ? '保存' : '发布' }}</text>
				</view>
			</view>
			<view class="safePad"></view>
		</view>
	</view>
</template>

<script>
	import colors from '@/mixins/color.js'
	import {
		getForumPostEdit,
		createForumPost,
		updateForumPost,
		getForumDraftDetail,
		saveForumDraft,
		deleteForumDraft,
		siteConfig
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

	export default {
		mixins: [colors],
		data() {
			return {
				editingPostId: '',
				draftId: '',
				draftTimer: 0,
				lastDraftFingerprint: '',
				tab: 'kinder',
				tabs: FORUM_TABS,
				title: '',
				content: '',
				forumEnabled: true,
				forumChecked: false
			}
		},
		onLoad(options) {
			this.ensureForumEnabled().then((ok) => {
				if (!ok) return
				this.initPage(options)
			})
		},
		onUnload() {
			if (this.draftTimer) clearTimeout(this.draftTimer)
		},
		computed: {
			canSubmit() {
				return (this.title || '').trim().length >= 4 && (this.content || '').trim().length >= 10
			},
			canDraft() {
				return ((this.title || '').trim().length > 0) || ((this.content || '').trim().length > 0)
			}
		},
		watch: {
			tab() {
				this.scheduleDraftSave()
			},
			title() {
				this.scheduleDraftSave()
			},
			content() {
				this.scheduleDraftSave()
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
			initPage(options) {
			const draftId = (options && options.draftId) ? decodeURIComponent(options.draftId) : ''
			const postId = (options && options.id) ? decodeURIComponent(options.id) : ''
			if (draftId) {
				this.loadDraft(draftId)
				return
			}
			if (postId) {
				this.loadPost(postId)
			}
			},
			fingerprintDraft() {
				const payload = {
					draftId: this.draftId || '',
					postId: this.editingPostId || '',
					tab: this.tab || '',
					title: (this.title || '').trim(),
					content: (this.content || '').trim()
				}
				return JSON.stringify(payload)
			},
			scheduleDraftSave() {
				if (!this.canDraft) return
				if (this.draftTimer) clearTimeout(this.draftTimer)
				this.draftTimer = setTimeout(() => {
					this.saveDraft(false)
				}, 450)
			},
			loadDraft(draftId) {
				getForumDraftDetail(draftId).then((res) => {
					const d = res && res.data ? res.data : null
					if (!d) return
					this.draftId = String(d.id || '')
					this.editingPostId = d.postId ? String(d.postId) : ''
					this.tab = d.tab || 'kinder'
					this.title = d.title || ''
					this.content = d.content || ''
					this.lastDraftFingerprint = this.fingerprintDraft()
				}).catch((e) => {
					uni.showToast({
						title: typeof e === 'string' ? e : '草稿加载失败',
						icon: 'none'
					})
				})
			},
			loadPost(postId) {
				getForumPostEdit(postId).then((res) => {
					const p = res && res.data ? res.data : null
					if (!p) return
					this.editingPostId = String(p.id || '')
					this.tab = p.tab || 'kinder'
					this.title = p.title || ''
					this.content = p.content || ''
					this.lastDraftFingerprint = this.fingerprintDraft()
				}).catch((e) => {
					uni.showToast({
						title: typeof e === 'string' ? e : '加载失败',
						icon: 'none'
					})
				})
			},
			saveDraft(showToast) {
				if (!this.canDraft) return
				const fp = this.fingerprintDraft()
				if (!showToast && fp === this.lastDraftFingerprint) return
				this.lastDraftFingerprint = fp

				saveForumDraft({
					id: this.draftId ? Number(this.draftId) : 0,
					postId: this.editingPostId ? Number(this.editingPostId) : 0,
					tab: this.tab,
					title: (this.title || '').trim(),
					content: (this.content || '').trim()
				}).then((res) => {
					const data = res && res.data ? res.data : {}
					this.draftId = String(data.id || this.draftId || '')
					if (showToast) {
						uni.showToast({
							title: '已保存到草稿箱',
							icon: 'success'
						})
					}
				}).catch((e) => {
					if (showToast) {
						uni.showToast({
							title: typeof e === 'string' ? e : '保存失败',
							icon: 'none'
						})
					}
				})
			},
			removeDraft(id) {
				return deleteForumDraft(id)
			},
			async submit() {
				if (!this.canSubmit) {
					uni.showToast({
						title: '标题至少4字，内容至少10字',
						icon: 'none'
					})
					return
				}
				try {
					const payload = {
						tab: this.tab,
						title: (this.title || '').trim(),
						content: (this.content || '').trim()
					}
					if (this.editingPostId) {
						await updateForumPost(this.editingPostId, payload)
					} else {
						await createForumPost(payload)
					}
					if (this.draftId) {
						await this.removeDraft(this.draftId)
					}
					uni.showToast({
						title: this.editingPostId ? '保存成功' : '发布成功',
						icon: 'success'
					})
					setTimeout(() => {
						uni.navigateBack()
					}, 350)
				} catch (e) {
					uni.showToast({
						title: typeof e === 'string' ? e : '提交失败',
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

	.label {
		display: block;
		font-size: 24rpx;
		font-weight: 800;
		color: rgba(31, 35, 41, 0.62);
		margin: 10rpx 0 10rpx;
	}

	.seg {
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
		margin-bottom: 8rpx;
	}

	.segItem {
		flex: none;
		width: calc((100% - 12rpx) / 2);
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
		font-size: 28rpx;
		font-weight: 900;
		color: #1f2329;
	}

	.titleInput {
		height: 88rpx;
		padding: 0 18rpx;
		border-radius: 18rpx;
		background: rgba(255, 255, 255, 0.95);
		border: 1rpx solid rgba(0, 0, 0, 0.06);
		font-size: 28rpx;
		color: #1f2329;
	}

	.contentInput {
		margin-top: 6rpx;
		width: 100%;
		min-height: 340rpx;
		padding: 18rpx;
		border-radius: 18rpx;
		background: rgba(255, 255, 255, 0.95);
		border: 1rpx solid rgba(0, 0, 0, 0.06);
		font-size: 28rpx;
		color: #1f2329;
		line-height: 44rpx;
	}

	.bottom {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		padding: 14rpx 18rpx 0;
		z-index: 2;
	}

	.btnRow {
		display: flex;
		gap: 12rpx;
	}

	.btn {
		height: 96rpx;
		border-radius: 22rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 12rpx 32rpx rgba(31, 35, 41, 0.3);
		flex: 1;
	}

	.btn.primary {
		background: linear-gradient(135deg, #1f2329 0%, #3a3f4a 100%);
	}

	.btn.ghost {
		background: rgba(31, 35, 41, 0.06);
		box-shadow: none;
	}

	.btn.disabled {
		opacity: 0.5;
	}

	.btnText {
		font-size: 32rpx;
		font-weight: 900;
		color: #fff;
	}

	.ghostText {
		color: rgba(31, 35, 41, 0.8);
	}

	.safePad {
		height: env(safe-area-inset-bottom);
	}
</style>
