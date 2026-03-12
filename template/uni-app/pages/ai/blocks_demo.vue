<template>
	<view class="page">
		<view class="tabs">
			<view class="tab" v-for="(c, i) in cases" :key="i" :class="{ on: i === active }" @click="active = i">
				<text class="tabText">{{ c.name }}</text>
			</view>
		</view>
		<scroll-view class="body" scroll-y="true">
			<view class="card">
				<BlocksRenderer :blocks="cases[active].blocks" :fallback-text="cases[active].fallback" :message-id="'demo_' + active" :progressive="true"></BlocksRenderer>
			</view>
			<view class="meta">
				<text class="metaText">blocks: {{ (cases[active].blocks || []).length }}</text>
			</view>
		</scroll-view>
	</view>
</template>

<script>
	import BlocksRenderer from '@/components/aiBlocks/BlocksRenderer.vue'

	export default {
		components: { BlocksRenderer },
		data() {
			return {
				active: 0,
				cases: [
					{
						name: '短文本',
						fallback: '',
						blocks: [
							{ type: 'h2', text: '三角模型分析' },
							{ type: 'p', inlines: [{ type: 'text', text: '你好！这是一个短文本示例。' }] }
						]
					},
					{
						name: '多段落',
						fallback: '',
						blocks: [
							{ type: 'h2', text: '功能价值与人群定位' },
							{ type: 'p', inlines: [{ type: 'text', text: '功能价值：提供教程、测评与避坑指南。' }] },
							{ type: 'list', ordered: false, items: [
								{ inlines: [{ type: 'text', text: '定位：新手友好' }] },
								{ inlines: [{ type: 'text', text: '表达：亲和力与专业度' }] }
							] }
						]
					},
					{
						name: '代码块',
						fallback: '',
						blocks: [
							{ type: 'h2', text: '示例代码' },
							{ type: 'code', lang: 'js', text: 'function hello(){\n  console.log(\"hello\")\n}\n\nhello()\n' }
						]
					},
					{
						name: '引用卡片',
						fallback: '',
						blocks: [
							{ type: 'p', inlines: [{ type: 'text', text: '这里有一条引用卡片：' }] },
							{ type: 'quote', title: '提示', blocks: [
								{ type: 'p', inlines: [{ type: 'text', text: '本服务为AI生成内容，结果仅供参考。', bold: true }] }
							] }
						]
					},
					{
						name: '图片',
						fallback: '当前环境无真实图片，可用实际返回验证。',
						blocks: [
							{ type: 'image', urls: ['https://dummyimage.com/900x500/eee/666.png&text=IMAGE'], aspectRatio: 1.8 }
						]
					}
				]
			}
		},
		onLoad() {
			uni.setNavigationBarTitle({ title: '渲染演示' })
		}
	}
</script>

<style lang="scss" scoped>
	.page {
		min-height: 100vh;
		background: #f4f6f8;
	}

	.tabs {
		display: flex;
		gap: 12rpx;
		padding: 18rpx 18rpx 0;
		overflow-x: auto;
		white-space: nowrap;
	}

	.tab {
		flex: none;
		padding: 12rpx 16rpx;
		border-radius: 999rpx;
		background: #fff;
		border: 1rpx solid rgba(0, 0, 0, 0.06);
	}

	.tab.on {
		background: rgba(241, 165, 92, 0.16);
		border-color: rgba(241, 165, 92, 0.28);
	}

	.tabText {
		font-size: 24rpx;
		color: rgba(31, 35, 41, 0.85);
	}

	.body {
		position: fixed;
		left: 0;
		right: 0;
		top: 90rpx;
		bottom: 0;
	}

	.card {
		margin: 18rpx;
		padding: 18rpx 18rpx;
		border-radius: 18rpx;
		background: #fff;
		box-shadow: 0rpx 10rpx 30rpx rgba(0, 0, 0, 0.06);
	}

	.meta {
		padding: 0 18rpx 24rpx;
	}

	.metaText {
		font-size: 22rpx;
		color: rgba(31, 35, 41, 0.45);
	}
</style>

