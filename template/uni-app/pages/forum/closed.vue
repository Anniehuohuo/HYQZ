<template>
	<view class="page">
		<view class="card">
			<view class="title">论坛暂未开放</view>
			<view class="desc">该功能暂未开放，敬请期待。</view>
			<view class="actions">
				<button class="btn" hover-class="none" @tap="goBack">返回</button>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'forum_closed',
		methods: {
			goBack() {
				const pages = getCurrentPages() || []
				if (pages.length > 1) {
					uni.navigateBack()
				} else {
					uni.switchTab({
						url: '/pages/user/index'
					})
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page {
		min-height: 100vh;
		background: #f4f6f8;
		padding: 30rpx 24rpx;
		box-sizing: border-box;
	}

	.card {
		background: #fff;
		border-radius: 20rpx;
		padding: 32rpx 26rpx;
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
	}

	.title {
		font-size: 34rpx;
		line-height: 46rpx;
		font-weight: 800;
		color: #1f2329;
	}

	.desc {
		margin-top: 14rpx;
		font-size: 26rpx;
		line-height: 38rpx;
		color: rgba(31, 35, 41, 0.62);
	}

	.actions {
		margin-top: 26rpx;
		display: flex;
		justify-content: flex-end;
	}

	.btn {
		background: rgba(241, 165, 92, 0.22);
		color: rgba(31, 35, 41, 0.92);
		font-size: 28rpx;
		line-height: 40rpx;
		padding: 14rpx 22rpx;
		border-radius: 16rpx;
	}
</style>

