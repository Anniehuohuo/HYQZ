<template>
	<view>
		<form :style="colorStyle">
			<view class="payment-top acea-row row-column row-center-wrapper">
				<span class="name">我的算力</span>
				<view class="pic">
					<span class="pic-font"><span class="num"></span>{{ quota.balance || 0 }}<span class="unit">点</span></span>
				</view>
				<view class="sub" v-if="quota.enabled">今日免费剩余 {{ quota.free_remaining || 0 }}/{{ quota.free_limit || 0 }}，超出后每次 {{ quota.cost_per_chat || 1 }} 点</view>
			</view>
			<view class="payment">
				<view class="tip picList">
					<view class="pic-box pic-box-color acea-row row-center-wrapper row-column"
						:class="activeId === item.id ? 'pic-box-color-active' : ''" v-for="item in packages" :key="item.id"
						@click="choose(item)">
						<view class="pic-number-pic">
							{{ item.price }}<span class="pic-number">元</span>
						</view>
						<view class="pic-number">获得：{{ item.power }} 点</view>
					</view>
					<view class="tips-box">
						<view class="tips mt-30">注意事项：</view>
						<view class="tips-samll" v-for="(item, idx) in attention" :key="idx">
							{{ item }}
						</view>
					</view>
				</view>
				<button class="but bg-color" @click="submitSub">立即充值</button>
			</view>
		</form>
		<payment :payMode="payMode" :pay_close="pay_close" :is-call="true" @onChangeFun="onChangeFun"
			:totalPrice="selectedPrice"></payment>
		<view v-show="false" v-html="formContent"></view>
	</view>
</template>

<script>
	import colors from "@/mixins/color";
	import payment from '@/components/payment';
	import {
		getAiPowerQuota,
		getAiPowerRechargeConfig,
		aiPowerRecharge
	} from '@/api/ai_power.js';
	import {
		basicConfig
	} from '@/api/public.js'
	// #ifdef H5
	import Auth from '@/libs/wechat';
	// #endif

	export default {
		components: {
			payment
		},
		mixins: [colors],
		data() {
			return {
				quota: {
					enabled: 0,
					free_limit: 3,
					free_used: 0,
					free_remaining: 3,
					cost_per_chat: 1,
					balance: 0
				},
				packages: [],
				attention: [],
				activeId: 0,
				selectedPrice: 0,
				pay_close: false,
				formContent: '',
				payMode: [{
					name: '微信支付',
					icon: 'icon-weixin2',
					value: 'routine',
					title: '微信支付',
					payStatus: true
				}]
			}
		},
		onLoad() {
			this.loadConfig()
			this.loadQuota()
		},
		methods: {
			loadQuota() {
				getAiPowerQuota().then(res => {
					this.quota = res.data || this.quota
				}).catch(() => {})
			},
			loadConfig() {
				getAiPowerRechargeConfig().then(res => {
					const data = res.data || {}
					this.packages = Array.isArray(data.packages) ? data.packages : []
					this.attention = Array.isArray(data.attention) ? data.attention : []
					if (this.packages.length && !this.activeId) {
						this.choose(this.packages[0])
					}
				}).catch(() => {})
				basicConfig().then(res => {
					const payConfig = res.data.pay_config || []
					const payMode = []
					// #ifdef MP
					if (payConfig.includes('routine')) {
						payMode.push({
							name: '微信支付',
							icon: 'icon-weixin2',
							value: 'routine',
							title: '微信支付',
							payStatus: true
						})
					}
					// #endif
					// #ifdef H5
					if (payConfig.includes('weixin')) {
						payMode.push({
							name: '微信支付',
							icon: 'icon-weixin2',
							value: Auth.isWeixin() ? 'weixin' : 'weixinh5',
							title: '微信支付',
							payStatus: true
						})
					}
					// #endif
					if (payMode.length) this.payMode = payMode
				}).catch(() => {})
			},
			choose(item) {
				this.activeId = Number(item.id || 0) || 0
				this.selectedPrice = Number(item.price || 0) || 0
			},
			submitSub(e) {
				if (e && e.preventDefault) e.preventDefault()
				if (!this.activeId) return
				this.pay_close = true
			},
			onChangeFun(e) {
				const action = e && e.action ? e.action : null
				const value = e && e.value !== undefined ? e.value : null
				this.pay_close = false
				action && this[action] && this[action](value)
			},
			payCheck(type) {
				uni.showLoading({
					title: '正在支付'
				})
				aiPowerRecharge({
					rechar_id: this.activeId,
					from: type
				}).then(res => {
					const status = res.data.status
					const orderId = res.data.result.orderId
					const jsConfig = res.data.result.jsConfig
					switch (status) {
						case 'WECHAT_PAY':
							uni.hideLoading();
							// #ifdef MP
							uni.requestPayment({
								timeStamp: jsConfig.timeStamp || jsConfig.timestamp,
								nonceStr: jsConfig.nonceStr,
								package: jsConfig.package,
								signType: jsConfig.signType,
								paySign: jsConfig.paySign,
								success: () => {
									this.paySuccess(orderId)
								},
								fail: () => {
									this.$util.Tips({
										title: '取消支付'
									})
								}
							});
							// #endif
							// #ifdef H5
							this.$wechat.pay(jsConfig).then(() => {
								this.paySuccess(orderId)
							}).catch(() => {
								this.$util.Tips({
									title: '取消支付'
								})
							})
							// #endif
							break;
						case 'WECHAT_H5_PAY':
							uni.hideLoading();
							if (jsConfig && jsConfig.h5_url) {
								location.href = jsConfig.h5_url;
							} else {
								this.$util.Tips({
									title: '发起支付失败'
								})
							}
							break;
						case 'SUCCESS':
							uni.hideLoading();
							this.paySuccess(orderId)
							break;
						default:
							uni.hideLoading();
							this.$util.Tips({
								title: res.msg || '支付失败'
							})
					}
				}).catch(err => {
					uni.hideLoading()
					this.$util.Tips({
						title: err || '支付失败'
					})
				})
			},
			paySuccess(orderId) {
				this.$util.Tips({
					title: '支付成功',
					icon: 'success'
				})
				this.loadQuota()
				uni.navigateBack({
					delta: 1
				})
			}
		}
	}
</script>

<style lang="scss">
	.payment-top {
		padding: 60rpx 30rpx 40rpx;
		background: #fff;
	}

	.payment-top .name {
		font-size: 26rpx;
		color: #666;
	}

	.payment-top .pic {
		margin-top: 18rpx;
	}

	.payment-top .pic-font {
		font-size: 52rpx;
		color: #333;
		font-weight: 600;
	}

	.payment-top .unit {
		margin-left: 8rpx;
		font-size: 24rpx;
		color: #666;
	}

	.payment-top .sub {
		margin-top: 14rpx;
		font-size: 22rpx;
		color: #999;
	}

	.payment {
		padding: 24rpx 30rpx 0;
	}

	.picList {
		display: flex;
		flex-wrap: wrap;
		justify-content: space-between;
		gap: 20rpx;
	}

	.pic-box {
		width: 48%;
		padding: 28rpx 0;
		border-radius: 16rpx;
		background: #fff;
	}

	.pic-box-color-active {
		border: 2rpx solid var(--view-theme);
	}

	.pic-number-pic {
		font-size: 40rpx;
		color: #333;
	}

	.pic-number {
		margin-top: 10rpx;
		font-size: 24rpx;
		color: #666;
	}

	.tips-box {
		width: 100%;
		margin-top: 20rpx;
		background: #fff;
		border-radius: 16rpx;
		padding: 20rpx 24rpx;
	}

	.tips {
		font-size: 24rpx;
		color: #333;
	}

	.tips-samll {
		margin-top: 10rpx;
		font-size: 22rpx;
		color: #666;
		line-height: 34rpx;
	}

	.but {
		margin-top: 26rpx;
		width: 100%;
		height: 86rpx;
		line-height: 86rpx;
		border-radius: 16rpx;
		color: #fff;
	}
</style>
