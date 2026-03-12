<template>
	<view class="new-users copy-data bg-mesh" :style="[colorStyle, { height: pageHeight }]">
		<view class="top">
			<!-- #ifdef MP || APP-PLUS -->
			<view class="sys-head">
				<view class="sys-bar" :style="{ height: sysHeight }"></view>
				<!-- #ifdef MP -->
				<view class="sys-title" :style="member_style == 3 ? 'color:#333' : ''">{{ $t('个人中心') }}</view>
				<!-- #endif -->
				<view class="bg" :style="member_style == 3 ? 'background:#f5f5f5' : ''"></view>
			</view>
			<!-- #endif -->
		</view>
		<view class="mid" style="flex: 1; overflow: hidden">
			<scroll-view scroll-y="true" style="height: 100%">
				<view class="ucHeader">
					<view class="ucHeaderLeft" @click="goEdit()">
						<view class="ucAvatar">
							<image class="ucAvatarImg" :src="userInfo.avatar" v-if="userInfo.avatar"></image>
							<image v-else class="ucAvatarImg" src="/static/images/f.png"></image>
						</view>
						<view class="ucHeaderInfo">
							<view class="ucNameRow" v-if="isLogin">
								<view class="ucName">{{ userInfo.nickname || '用户' }}</view>
								<view class="ucLevelTag">Lv.1</view>
							</view>
							<view class="ucNameRow" v-else>
								<view class="ucName">{{ $t('请点击登录') }}</view>
							</view>
							<view class="ucPhone" v-if="isLogin">{{ userInfo.phone || '' }}</view>
							<view class="ucPhone" v-else>{{ $t('未登录') }}</view>
						</view>
					</view>
					<view class="ucHeaderRight">
						<view class="ucIconBtn" @click="goMenuPage('/pages/users/user_info/index')">
							<text class="iconfont icon-shezhi"></text>
						</view>
						<view class="ucIconBtn" @click="goMenuPage('/pages/users/message_center/index')">
							<text class="iconfont icon-s-kefu"></text>
							<view class="ucDot" v-if="userInfo.service_num"></view>
						</view>
					</view>
				</view>

				<view class="ucPowerWrap" v-if="isLogin">
					<view class="ucPowerCard">
						<view class="ucPowerGlow"></view>
						<view class="ucPowerTop">
							<view class="ucPowerTitle">我的算力</view>
							<view class="ucPowerBadge" v-if="aiPower.enabled">今日免费剩余 {{ aiPower.free_remaining || 0 }}/{{ aiPower.free_limit || 0 }}</view>
						</view>
						<view class="ucPowerBottom">
							<view class="ucPowerAmount">
								<view class="ucPowerRow">
									<text class="ucPowerNum">{{ aiPower.balance || 0 }}</text>
									<text class="ucPowerUnit2">点</text>
								</view>
								<view class="ucPowerTip">超出后每次消耗 {{ aiPower.cost_per_chat || 1 }} 点</view>
							</view>
							<view class="ucPowerBtn" @click="goMenuPage('/pages/users/ai_power_payment/index')">充值算力</view>
						</view>
					</view>
				</view>

				<view class="ucTabs2">
					<view class="ucTab2" v-for="tab in tabs" :key="tab" :class="{ on: activeTab === tab }" @click="activeTab = tab">
						<view class="ucTab2Text">{{ tab }}</view>
						<view class="ucTab2Line" v-if="activeTab === tab"></view>
					</view>
				</view>

				<view class="ucBody">
					<view class="ucGroups">
						<view class="ucGroup" v-for="(group, gIdx) in currentGroups" :key="gIdx">
							<view class="ucGroupTitle">{{ group.title }}</view>
							<view class="ucGroupCard">
								<block v-for="(item, iIdx) in group.items" :key="iIdx">
									<view class="ucItem" :class="{ last: iIdx === group.items.length - 1 }" @click="onUcItem(item)">
										<view class="ucItemLeft">
											<view class="ucItemIcon" :style="{ background: item.bg || 'rgba(241, 165, 92, 0.12)', color: 'var(--view-theme)' }">
												<text class="iconfont" :class="item.icon"></text>
											</view>
											<view class="ucItemName">{{ item.name }}</view>
										</view>
										<view class="ucItemRight">
											<view class="ucItemExtra" v-if="item.extra">{{ item.extra }}</view>
											<text class="iconfont icon-jiantou ucItemArrow"></text>
										</view>
									</view>
								</block>
								<!-- #ifdef MP -->
								<button class="ucItem ucBtnItem" open-type="contact" v-if="group.contact">
									<view class="ucItemLeft">
										<view class="ucItemIcon" :style="{ background: 'rgba(241, 165, 92, 0.12)', color: 'var(--view-theme)' }">
											<text class="iconfont icon-s-kefu"></text>
										</view>
										<view class="ucItemName">{{ $t('联系客服') }}</view>
									</view>
									<view class="ucItemRight">
										<text class="iconfont icon-jiantou ucItemArrow"></text>
									</view>
								</button>
								<!-- #endif -->
							</view>
						</view>
					</view>
				</view>
				<view class="uni-p-b-98"></view>
			</scroll-view>
			<editUserModal :isShow="editModal" @closeEdit="closeEdit" @editSuccess="editSuccess"></editUserModal>
		</view>
		<pageFooter :style="colorStyle"></pageFooter>
	</view>
</template>
<script>
let sysHeight = uni.getSystemInfoSync().statusBarHeight + 'px';
import { getMenuList, getUserInfo, setVisit, mpBindingPhone } from '@/api/user.js';
import { wechatAuthV2, silenceAuth } from '@/api/public.js';
import { getAiPowerQuota } from '@/api/ai_power.js';
import { toLogin } from '@/libs/login.js';
import { mapState, mapGetters } from 'vuex';
// #ifdef H5
import Auth from '@/libs/wechat';
// #endif
const app = getApp();
import dayjs from '@/plugin/dayjs/dayjs.min.js';
import Routine from '@/libs/routine';
import colors from '@/mixins/color';
import pageFooter from '@/components/pageFooter/index.vue';
import { getCustomer } from '@/utils/index.js';
import editUserModal from '@/components/eidtUserModal/index.vue';
export default {
	components: {
		pageFooter,
		editUserModal
	},
	// computed: mapGetters(['isLogin','cartNum']),
	computed: {
		...mapGetters({
			cartNum: 'cartNum',
			isLogin: 'isLogin'
		}),
		currentGroups() {
			if (this.activeTab === '我的管理') return this.buildManageGroups();
			if (this.activeTab === '我的共创') return this.buildCocreateGroups();
			return this.buildCourseGroups();
		}
	},
	filters: {
		coundTime(val) {
			var setTime = val * 1000;
			var nowTime = new Date();
			var rest = setTime - nowTime.getTime();
			var day = parseInt(rest / (60 * 60 * 24 * 1000));
			// var hour = parseInt(rest/(60*60*1000)%24) //小时
			return day + this.$t('day');
		},
		dateFormat: function (value) {
			return dayjs(value * 1000).format('YYYY-MM-DD');
		}
	},
	mixins: [colors],
	data() {
		return {
			editModal: false, // 编辑头像信息
			storeMenu: [], // 商家管理
			orderMenu: [
				{
					img: 'icon-daifukuan',
					title: '待付款',
					url: '/pages/goods/order_list/index?status=0'
				},
				{
					img: 'icon-daifahuo',
					title: '待发货',
					url: '/pages/goods/order_list/index?status=1'
				},
				{
					img: 'icon-daishouhuo',
					title: '待收货',
					url: '/pages/goods/order_list/index?status=2'
				},
				{
					img: 'icon-daipingjia',
					title: '待评价',
					url: '/pages/goods/order_list/index?status=3'
				},
				{
					img: 'icon-a-shouhoutuikuan',
					title: '售后/退款',
					url: '/pages/users/user_return_list/index'
				}
			],
			imgUrls: [],
			autoplay: true,
			circular: true,
			interval: 3000,
			duration: 500,
			isAuto: false, //没有授权的不会自动授权
			isShowAuth: false, //是否隐藏授权
			orderStatusNum: {},
			userInfo: {},
			aiPower: {
				enabled: 0,
				free_limit: 3,
				free_used: 0,
				free_remaining: 3,
				cost_per_chat: 1,
				balance: 0
			},
			MyMenus: [],
			sysHeight: sysHeight,
			mpHeight: 0,
			showStatus: 1,
			activeRouter: '',
			// #ifdef H5 || MP
			pageHeight: '100%',
			routineContact: 0,
			// #endif
			// #ifdef APP-PLUS
			pageHeight: app.globalData.windowHeight,
			// #endif
			// #ifdef H5
			isWeixin: Auth.isWeixin(),
			//#endif
			footerSee: false,
			my_menus_status: 0,
			business_status: 0,
			member_style: 0,
			my_banner_status: 0,
			is_diy: uni.getStorageSync('is_diy'),
			copyRightPic: '/static/images/support.png',
			tabs: ['我的智能体', '我的共创', '我的管理'],
			activeTab: '我的管理'
		};
	},
	onLoad(option) {
		uni.hideTabBar();
		let that = this;
		// #ifdef MP
		// 小程序静默授权
		if (!this.$store.getters.isLogin) {
			// Routine.getCode()
			// 	.then(code => {
			// 		Routine.silenceAuth(code).then(res => {
			// 			this.onLoadFun();
			// 		})
			// 	})
			// 	.catch(res => {
			// 		uni.hideLoading();
			// 	});
		}
		// #endif

		// #ifdef H5 || APP-PLUS
		// if (that.isLogin == false) {
		// 	toLogin();
		// }
		//获取用户信息回来后授权
		let cacheCode = this.$Cache.get('snsapi_userinfo_code');
		let res1 = cacheCode ? option.code != cacheCode : true;
		if (this.isWeixin && option.code && res1 && option.scope === 'snsapi_userinfo') {
			this.$Cache.set('snsapi_userinfo_code', option.code);
			Auth.auth(option.code)
				.then((res) => {
					this.getUserInfo();
				})
				.catch((err) => {});
		}
		// #endif
		// #ifdef APP-PLUS
		that.$set(that, 'pageHeight', app.globalData.windowHeight);
		// #endif

		let routes = getCurrentPages(); // 获取当前打开过的页面路由数组
		let curRoute = routes[routes.length - 1].route; //获取当前页面路由
		this.activeRouter = '/' + curRoute;
		this.getCopyRight();
	},
	onReady() {
		let self = this;
		// #ifdef MP
		let info = uni.createSelectorQuery().select('.sys-head');
		info
			.boundingClientRect(function (data) {
				//data - 各种参数
				self.mpHeight = data.height;
			})
			.exec();
		// #endif
	},
	onShow: function () {
		let that = this;
		// #ifdef APP-PLUS
		uni.getSystemInfo({
			success: function (res) {
				that.pageHeight = res.windowHeight + 'px';
			}
		});
		// #endif
		if (that.isLogin) {
			this.getUserInfo();
			this.setVisit();
		}
		this.getMyMenus();
		this.getCopyRight();
	},
	onPullDownRefresh() {
		this.onLoadFun();
	},
	methods: {
		getWechatuserinfo() {
			//#ifdef H5
			Auth.isWeixin() && Auth.toAuth('snsapi_userinfo', '/pages/user/index');
			//#endif
		},
		editSuccess() {
			this.editModal = false;
			this.getUserInfo();
		},
		closeEdit() {
			this.editModal = false;
		},
		// 记录会员访问
		setVisit() {
			setVisit({
				url: '/pages/user/index'
			}).then((res) => {});
		},
		// 打开授权
		openAuto() {
			toLogin();
		},
		// 授权回调
		onLoadFun() {
			this.getUserInfo();
			this.getMyMenus();
			this.setVisit();
		},
		Setting: function () {
			uni.openSetting({
				success: function (res) {}
			});
		},
		// 授权关闭
		authColse: function (e) {
			this.isShowAuth = e;
		},
		// 绑定手机
		bindPhone() {
			uni.navigateTo({
				url: '/pages/users/user_phone/index'
			});
		},
		getphonenumber(e) {
			if (e.detail.errMsg == 'getPhoneNumber:ok') {
				Routine.getCode()
					.then((code) => {
						let data = {
							code,
							iv: e.detail.iv,
							encryptedData: e.detail.encryptedData
						};
						mpBindingPhone(data)
							.then((res) => {
								this.getUserInfo();
								this.$util.Tips({
									title: res.msg,
									icon: 'success'
								});
							})
							.catch((err) => {
								return this.$util.Tips({
									title: err
								});
							});
					})
					.catch((error) => {
						uni.hideLoading();
					});
			}
		},
		/**
		 * 获取个人用户信息
		 */
		getUserInfo: function () {
			let that = this;
			getUserInfo().then((res) => {
				that.userInfo = res.data;
				that.$store.commit('SETUID', res.data.uid);
				that.orderMenu.forEach((item, index) => {
					switch (item.title) {
						case '待付款':
							item.num = res.data.orderStatusNum.unpaid_count;
							break;
						case '待发货':
							item.num = res.data.orderStatusNum.unshipped_count;
							break;
						case '待收货':
							item.num = res.data.orderStatusNum.received_count;
							break;
						case '待评价':
							item.num = res.data.orderStatusNum.evaluated_count;
							break;
						case '售后/退款':
							item.num = res.data.orderStatusNum.refunding_count;
							break;
					}
				});
				getAiPowerQuota().then((r) => {
					that.aiPower = r.data || that.aiPower
				}).catch(() => {})
				uni.stopPullDownRefresh();
			});
		},
		//小程序授权api替换 getUserInfo
		getUserProfile() {
			toLogin();
		},
		onUcItem(item) {
			if (!item) return;
			if (item.url && typeof item.url === 'string' && (/^\/?pages\/forum\//.test(item.url.trim()))) {
				return this.goMenuPage('/pages/parents_classroom/index', '家长课堂');
			}
			if (item.url && typeof item.url === 'string' && item.url.trim() === '/pages/annex/vip_paid/index') {
				return uni.showToast({ title: '该功能暂未开放', icon: 'none' });
			}
			if (item.url) return this.goMenuPage(item.url, item.name || '');
			if (item.action === 'aiPower') return this.goMenuPage('/pages/users/ai_power_payment/index');
			if (item.action === 'course') return this.goMenuPage('/pages/ai/agents?onlyUnlocked=1&title=我的智能体');
		},
		promoItems() {
			const money = this.userInfo && (this.userInfo.commissionCount || this.userInfo.commission_count) ? (this.userInfo.commissionCount || this.userInfo.commission_count) : '0.00';
			return [
				{
					name: '我的推广',
					icon: 'icon-ic_gift1',
					url: '/pages/users/user_spread_user/index'
				},
				{
					name: '推广收益',
					icon: 'icon-ic_fire',
					extra: '¥ ' + money,
					url: '/pages/users/user_spread_money/index?type=2'
				},
				{
					name: '推广规则',
					icon: 'icon-a-ic_Imageandtextsorting',
					url: '/pages/users/user_distribution_level/index'
				}
			];
		},
		buildCourseGroups() {
			return [
				{
					title: '智能体',
					items: [
						{ name: '已解锁智能体', icon: 'icon-ic_fire', url: '/pages/ai/agents?onlyUnlocked=1&title=我的智能体' }
					]
				}
			];
		},
		buildCocreateGroups() {
			return [
				{
					title: '推广中心',
					items: this.promoItems()
				}
			];
		},
		buildManageGroups() {
			const groups = [
				{
					title: '智能体',
					items: [
						{ name: '我的智能体', icon: 'icon-ic_fire', url: '/pages/ai/agents?onlyUnlocked=1&title=我的智能体' }
					]
				},
				{
					title: '推广中心',
					items: this.promoItems()
				},
				{
					title: '常用管理',
					items: [
						{ name: '个人资料', icon: 'icon-ic-complete1', url: '/pages/users/user_info/index' },
						{ name: '消息中心', icon: 'icon-ic_increase-2', url: '/pages/users/message_center/index' },
						{ name: '隐私协议', icon: 'icon-ic_close1', url: '/pages/users/privacy/index?type=3' }
					]
				}
			];

			const moreItems = [
				{ name: '我的算力', icon: 'icon-ic_fire', action: 'aiPower' },
				{ name: '账号管理', icon: 'icon-ic_close1', url: '/pages/users/user_info/index' }
			];

			if (this.MyMenus && this.MyMenus.length) {
				const dynamic = this.MyMenus
					.filter(
						it =>
							it &&
							it.url &&
							it.url !== '#' &&
							it.url !== '/pages/service/index' &&
							it.url !== '/pages/goods/order_list/index' &&
							it.url !== '/pages/users/user_return_list/index' &&
							it.url !== '/pages/annex/vip_paid/index' &&
							String(it.name || '').indexOf('付费会员') === -1 &&
							String(it.name || '').indexOf('会员权益') === -1
					)
					.map(it => ({
						name: it.name,
						icon: 'icon-ic_gift1',
						url: it.url
					}));
				const forumItems = dynamic.filter(it => it && (it.name === '我的论坛' || it.url === '/pages/forum/me'));
				const otherItems = dynamic.filter(it => !forumItems.includes(it));

				if (forumItems.length) {
					const contentGroup = groups.find(g => g && g.title === '内容管理');
					if (contentGroup && Array.isArray(contentGroup.items)) {
						contentGroup.items.push(...forumItems);
					} else {
						moreItems.push(...forumItems);
					}
				}

				moreItems.push(...otherItems);
			}

			groups.push({
				title: '更多服务',
				items: moreItems,
				contact: this.routineContact == 1
			});

			if (this.storeMenu && this.storeMenu.length) {
				groups.push({
					title: '商家管理',
					items: this.storeMenu
						.filter(it => it && it.url && it.url !== '#' && it.url !== '/pages/service/index')
						.map(it => ({
							name: it.name,
							icon: 'icon-ic_ShoppingCart1',
							url: it.url
						}))
				});
			}
			return groups;
		},
		/**
		 *
		 * 获取个人中心图标
		 */
		switchTab(order) {
			this.orderMenu.forEach((item, index) => {
				switch (item.title) {
					case '待付款':
						item.img = order.dfk;
						break;
					case '待发货':
						item.img = order.dfh;
						break;
					case '待收货':
						item.img = order.dsh;
						break;
					case '待评价':
						item.img = order.dpj;
						break;
					case '售后/退款':
						item.img = order.sh;
						break;
				}
			});
		},
		getMyMenus: function () {
			let that = this;
			// if (this.MyMenus.length) return;
			getMenuList().then((res) => {
				this.member_style = Number(res.data.diy_data.value);
				this.my_banner_status = res.data.diy_data.my_banner_status;
				this.my_menus_status = res.data.diy_data.my_menus_status;
				this.business_status = res.data.diy_data.business_status;
				let storeMenu = [];
				let myMenu = [];
				res.data.routine_my_menus.forEach((el, index, arr) => {
					if (el.url == '/pages/admin/order/index' || el.url == '/pages/admin/order_cancellation/index' || el.name == '客服接待') {
						storeMenu.push(el);
					} else {
						myMenu.push(el);
					}
				});

				let order01 = {
					dfk: 'icon-daifukuan',
					dfh: 'icon-daifahuo',
					dsh: 'icon-daishouhuo',
					dpj: 'icon-daipingjia',
					sh: 'icon-a-shouhoutuikuan'
				};
				let order02 = {
					dfk: 'icon-daifukuan-lan',
					dfh: 'icon-daifahuo-lan',
					dsh: 'icon-daishouhuo-lan',
					dpj: 'icon-daipingjia-lan',
					sh: 'icon-shouhou-tuikuan-lan'
				};
				let order03 = {
					dfk: 'icon-daifukuan-ju',
					dfh: 'icon-daifahuo-ju',
					dsh: 'icon-daishouhuo-ju',
					dpj: 'icon-daipingjia-ju',
					sh: 'icon-shouhou-tuikuan-ju'
				};
				let order04 = {
					dfk: 'icon-daifukuan-fen',
					dfh: 'icon-daifahuo-fen',
					dsh: 'icon-daishouhuo-fen',
					dpj: 'icon-daipingjia-fen',
					sh: 'icon-a-shouhoutuikuan-fen'
				};
				let order05 = {
					dfk: 'icon-daifukuan-lv',
					dfh: 'icon-daifahuo-lv',
					dsh: 'icon-daishouhuo-lv',
					dpj: 'icon-daipingjia-lv',
					sh: 'icon-shouhou-tuikuan-lv'
				};
				switch (res.data.diy_data.order_status) {
					case 1:
						this.switchTab(order01);
						break;
					case 2:
						this.switchTab(order02);
						break;
					case 3:
						this.switchTab(order03);
						break;
					case 4:
						this.switchTab(order04);
						break;
					case 5:
						this.switchTab(order05);
						break;
				}
				that.$set(that, 'MyMenus', myMenu);
				that.$set(that, 'storeMenu', storeMenu);
				this.imgUrls = res.data.routine_my_banner;
				this.routineContact = Number(res.data.routine_contact_type);
			});
		},
		// 编辑页面
		goEdit() {
			if (this.isLogin == false) {
				toLogin();
			} else {
				// #ifdef MP
				if (this.userInfo.is_default_avatar) {
					this.editModal = true;
					return;
				}
				// #endif
				uni.navigateTo({
					url: '/pages/users/user_info/index'
				});
			}
		},
		// 签到
		goSignIn() {
			uni.navigateTo({
				url: '/pages/users/user_sgin/index'
			});
		},

		goPages(url) {
			this.$util.JumpPath(url);
		},

		// goMenuPage
		goMenuPage(url, name) {
			if (typeof url === 'string') {
				url = url.trim();
				if (url.indexOf('pages/') === 0) url = '/' + url;
			}
			if (typeof url === 'string' && /^\/?pages\/forum\//.test(url)) {
				url = '/pages/parents_classroom/index';
				if (!name) name = '家长课堂';
			}
			if (this.isLogin) {
				if (url.indexOf('http') === -1) {
					// #ifdef H5 || APP-PLUS
					if (name && name === '客服接待') {
						// return window.location.href = `${location.origin}${url}`
						return uni.navigateTo({
							url: `/pages/annex/web_view/index?url=${location.origin}${url}`
						});
					} else if (name && name === '联系客服') {
						return getCustomer(url);
					} else if (name === '订单核销') {
						return uni.navigateTo({
							url: url
						});
						// return window.location.href = `${location.origin}${url}`
					}
					// #endif

					// #ifdef MP
					if (name && name === '联系客服') {
						return getCustomer(url);
					}
					if (url != '#' && url == '/pages/users/user_info/index') {
						uni.openSetting({
							success: function (res) {}
						});
					}
					// #endif
					uni.navigateTo({
						url: url,
						fail(err) {
							uni.switchTab({
								url: url,
								fail() {
									uni.showToast({ title: '页面暂不可用', icon: 'none' });
								}
							});
						}
					});
				} else {
					uni.navigateTo({
						url: `/pages/annex/web_view/index?url=${url}`
					});
				}
			} else {
				// #ifdef MP
				this.openAuto();
				// #endif
				// #ifndef MP
				toLogin();
				// #endif
			}
		},
		goRouter(item) {
			var pages = getCurrentPages();
			var page = pages[pages.length - 1].$page.fullPath;
			if (item.link == page) return;
			uni.switchTab({
				url: item.link,
				fail(err) {
					uni.redirectTo({
						url: item.link
					});
				}
			});
		},
		getCopyRight() {
			const copyRight = uni.getStorageSync('copyRight');
			if (copyRight.copyrightImage) {
				this.copyRightPic = copyRight.copyrightImage;
			}
		}
	}
};
</script>

<style lang="scss">
page,
body {
	height: 100%;
}

.height {
	margin-top: -100rpx !important;
}

.ucHeader {
	background: #fff;
	padding: 40rpx 30rpx 26rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
	position: relative;
}

.ucHeaderLeft {
	display: flex;
	align-items: center;
	gap: 20rpx;
}

.ucAvatar {
	width: 120rpx;
	height: 120rpx;
	border-radius: 999rpx;
	border: 4rpx solid rgba(241, 165, 92, 0.18);
	padding: 4rpx;
	background: rgba(241, 165, 92, 0.08);
	overflow: hidden;
}

.ucAvatarImg {
	width: 100%;
	height: 100%;
	border-radius: 999rpx;
}

.ucHeaderInfo {
	display: flex;
	flex-direction: column;
	gap: 6rpx;
}

.ucNameRow {
	display: flex;
	align-items: center;
	gap: 12rpx;
}

.ucName {
	font-size: 36rpx;
	font-weight: 700;
	color: rgba(31, 35, 41, 0.95);
}

.ucLevelTag {
	font-size: 18rpx;
	padding: 4rpx 12rpx;
	border-radius: 999rpx;
	background: rgba(241, 165, 92, 0.18);
	color: var(--view-theme);
}

.ucPhone {
	font-size: 24rpx;
	color: rgba(31, 35, 41, 0.45);
}

.ucHeaderRight {
	display: flex;
	gap: 18rpx;
}

.ucIconBtn {
	width: 72rpx;
	height: 72rpx;
	border-radius: 999rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	color: rgba(31, 35, 41, 0.55);
	position: relative;
}

.ucDot {
	position: absolute;
	top: 16rpx;
	right: 18rpx;
	width: 14rpx;
	height: 14rpx;
	border-radius: 999rpx;
	background: #ff4d4f;
	border: 2rpx solid #fff;
}

.ucPowerWrap {
	padding: 0 20rpx;
	margin-top: -12rpx;
}

.ucPowerCard {
	position: relative;
	overflow: hidden;
	border-radius: 36rpx;
	padding: 32rpx 30rpx;
	background: linear-gradient(135deg, rgba(241, 165, 92, 1) 0%, rgba(241, 138, 72, 1) 55%, rgba(238, 92, 77, 1) 100%);
	box-shadow: 0rpx 18rpx 46rpx rgba(241, 165, 92, 0.28);
}

.ucPowerGlow {
	position: absolute;
	top: -40rpx;
	right: -60rpx;
	width: 320rpx;
	height: 320rpx;
	border-radius: 999rpx;
	background: rgba(255, 255, 255, 0.16);
	filter: blur(24rpx);
}

.ucPowerTop {
	position: relative;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 26rpx;
}

.ucPowerTitle {
	font-size: 26rpx;
	color: rgba(255, 255, 255, 0.92);
	font-weight: 600;
}

.ucPowerBadge {
	padding: 6rpx 14rpx;
	border-radius: 999rpx;
	background: rgba(255, 255, 255, 0.22);
	color: rgba(255, 255, 255, 0.9);
	font-size: 18rpx;
}

.ucPowerBottom {
	position: relative;
	display: flex;
	justify-content: space-between;
	align-items: flex-end;
	gap: 20rpx;
}

.ucPowerAmount {
	display: flex;
	flex-direction: column;
}

.ucPowerRow {
	display: flex;
	align-items: baseline;
}

.ucPowerNum {
	font-size: 88rpx;
	line-height: 88rpx;
	font-weight: 900;
	color: #fff;
}

.ucPowerUnit2 {
	margin-left: 12rpx;
	font-size: 24rpx;
	color: rgba(255, 255, 255, 0.8);
}

.ucPowerTip {
	margin-top: 14rpx;
	font-size: 20rpx;
	color: rgba(255, 255, 255, 0.65);
}

.ucPowerBtn {
	background: #fff;
	color: var(--view-theme);
	font-weight: 700;
	padding: 18rpx 26rpx;
	border-radius: 22rpx;
	box-shadow: 0rpx 14rpx 26rpx rgba(0, 0, 0, 0.14);
}

.ucTabs2 {
	margin-top: 26rpx;
	padding: 0 20rpx;
	display: flex;
	align-items: center;
	border-bottom: 2rpx solid rgba(0, 0, 0, 0.04);
	background: #fff;
}

.ucTab2 {
	flex: 1;
	padding: 26rpx 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	position: relative;
}

.ucTab2Text {
	font-size: 28rpx;
	font-weight: 700;
	color: rgba(31, 35, 41, 0.35);
}

.ucTab2.on .ucTab2Text {
	color: var(--view-theme);
}

.ucTab2Line {
	position: absolute;
	bottom: 0;
	left: 50%;
	transform: translateX(-50%);
	width: 64rpx;
	height: 8rpx;
	border-radius: 999rpx;
	background: var(--view-theme);
}

.ucBody {
	background: #f6f7f9;
	padding: 20rpx 20rpx 0;
}

.ucVipBar {
	background: #2d3139;
	border-radius: 22rpx;
	padding: 20rpx 20rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 18rpx;
}

.ucVipLeft {
	display: flex;
	align-items: center;
	gap: 14rpx;
}

.ucVipIcon {
	width: 56rpx;
	height: 56rpx;
	border-radius: 999rpx;
	background: rgba(241, 165, 92, 0.18);
	color: rgba(241, 165, 92, 1);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30rpx;
}

.ucVipText {
	color: rgba(255, 255, 255, 0.88);
	font-size: 26rpx;
}

.ucVipGo {
	color: rgba(255, 255, 255, 0.7);
	font-size: 22rpx;
	display: flex;
	align-items: center;
	gap: 6rpx;
}

.ucGroup {
	margin-top: 18rpx;
}

.ucGroupTitle {
	padding: 0 14rpx 10rpx;
	font-size: 20rpx;
	font-weight: 700;
	color: rgba(31, 35, 41, 0.35);
	letter-spacing: 2rpx;
}

.ucGroupCard {
	background: #fff;
	border-radius: 22rpx;
	overflow: hidden;
	border: 2rpx solid rgba(0, 0, 0, 0.03);
}

.ucItem {
	padding: 26rpx 22rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	border-bottom: 2rpx solid rgba(0, 0, 0, 0.04);
}

.ucItem.last {
	border-bottom: 0;
}

.ucBtnItem {
	width: 100%;
	background: transparent;
	border: 0;
	border-radius: 0;
}

.ucItemLeft {
	display: flex;
	align-items: center;
	gap: 18rpx;
}

.ucItemIcon {
	width: 44rpx;
	height: 44rpx;
	border-radius: 14rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28rpx;
}

.ucItemName {
	font-size: 28rpx;
	color: rgba(31, 35, 41, 0.82);
	font-weight: 500;
}

.ucItemRight {
	display: flex;
	align-items: center;
	gap: 10rpx;
}

.ucItemExtra {
	font-size: 22rpx;
	color: rgba(31, 35, 41, 0.5);
}

.ucItemArrow {
	font-size: 26rpx;
	color: rgba(31, 35, 41, 0.25);
}

.aiPowerBar {
	margin-top: 18rpx;
	width: 100%;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20rpx 22rpx;
	border-radius: 16rpx;
	background: rgba(245, 246, 248, 0.92);
}

.aiPowerLeft {
	display: flex;
	flex-direction: column;
	gap: 8rpx;
}

.aiPowerLabel {
	font-size: 26rpx;
	color: rgba(0, 0, 0, 0.9);
	font-weight: 600;
}

.aiPowerValue {
	font-size: 44rpx;
	color: rgba(0, 0, 0, 0.92);
	font-weight: 600;
}

.aiPowerUnit {
	margin-left: 6rpx;
	font-size: 26rpx;
	color: rgba(0, 0, 0, 0.72);
}

.aiPowerQuota {
	font-size: 22rpx;
	color: rgba(0, 0, 0, 0.72);
	line-height: 32rpx;
	max-width: 420rpx;
}

.aiPowerBtn {
	height: 72rpx;
	padding: 0 26rpx;
	border-radius: 999rpx;
	background: var(--view-theme);
	color: rgba(0, 0, 0, 0.88);
	font-size: 28rpx;
	font-weight: 600;
	display: flex;
	align-items: center;
	justify-content: center;
}

.ucTabs {
	margin-top: 18rpx;
	display: flex;
	background: #fff;
	border-radius: 16rpx;
	overflow: hidden;
}

.ucTab {
	flex: 1;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
}

.ucTabText {
	font-size: 28rpx;
	color: rgba(31, 35, 41, 0.7);
}

.ucTab.on .ucTabText {
	color: rgba(31, 35, 41, 0.95);
	font-weight: 600;
}

.ucTab.on::after {
	content: '';
	position: absolute;
	left: 50%;
	bottom: 10rpx;
	transform: translateX(-50%);
	width: 64rpx;
	height: 6rpx;
	border-radius: 999rpx;
	background: var(--view-theme);
}

.ucSection {
	margin-top: 20rpx;
}

.vipSection {
	margin-top: 20rpx;
}

.ucQuick {
	background: #fff;
	border-radius: 16rpx;
	padding: 10rpx 0;
	display: flex;
}

.ucQuickItem {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 18rpx 0;
}

.ucQuickIcon {
	width: 84rpx;
	height: 84rpx;
	border-radius: 999rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(241, 165, 92, 0.12);
	color: var(--view-theme);
	font-size: 44rpx;
}

.ucQuickText {
	margin-top: 12rpx;
	font-size: 24rpx;
	color: rgba(31, 35, 41, 0.86);
}

.ucCard {
	background: #fff;
	border-radius: 16rpx;
	padding: 26rpx 24rpx;
}

.ucCardTitle {
	font-size: 30rpx;
	color: rgba(31, 35, 41, 0.95);
	font-weight: 600;
}

.ucCardDesc {
	margin-top: 10rpx;
	font-size: 24rpx;
	color: rgba(31, 35, 41, 0.6);
}

.ucCardBtn {
	margin-top: 18rpx;
	height: 72rpx;
	border-radius: 14rpx;
	background: var(--view-theme);
	color: #fff;
	font-size: 26rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.manageGroup {
	background: #fff;
	border-radius: 16rpx;
	padding: 22rpx 22rpx 10rpx;
	margin-bottom: 20rpx;
}

.manageTitle {
	font-size: 28rpx;
	color: rgba(31, 35, 41, 0.95);
	font-weight: 600;
	margin-bottom: 12rpx;
}

.manageList {
	display: flex;
	flex-direction: column;
}

.manageItem {
	height: 92rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	border-top: 1rpx solid rgba(0, 0, 0, 0.05);
	background: transparent;
	padding: 0;
}

.manageItem:first-child {
	border-top: 0;
}

.manageLeft {
	display: flex;
	align-items: center;
	gap: 16rpx;
}

.manageIcon {
	width: 56rpx;
	height: 56rpx;
	border-radius: 14rpx;
	background: rgba(241, 165, 92, 0.12);
	color: var(--view-theme);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 34rpx;
}

.manageImg {
	width: 56rpx;
	height: 56rpx;
	border-radius: 14rpx;
}

.manageName {
	font-size: 26rpx;
	color: rgba(31, 35, 41, 0.9);
}

.manageArrow {
	font-size: 28rpx;
	color: rgba(31, 35, 41, 0.35);
}

.unBg {
	background-color: unset !important;

	.user-info {
		.info {
			.name {
				color: #333333 !important;
				font-weight: 600;
			}

			.num {
				color: #333 !important;

				.num-txt {
					height: 38rpx;
					background-color: rgba(51, 51, 51, 0.13);
					padding: 0 12rpx;
					border-radius: 16rpx;
				}
			}
		}
	}

	.num-wrapper {
		color: #333 !important;
		font-weight: 600;

		.num-item {
			.txt {
				color: rgba(51, 51, 51, 0.7) !important;
			}
		}
	}

	.message {
		.iconfont {
			color: #333 !important;
		}

		.num {
			color: #fff !important;
			background-color: var(--view-theme) !important;
		}
	}

	.setting {
		.iconfont {
			color: #333 !important;
		}
	}
}

.cardVipB {
	background-color: #343a48;
	width: 100%;
	height: 124rpx;
	border-radius: 16rpx 16rpx 0 0;
	padding: 22rpx 30rpx 0 30rpx;
	margin-top: 16px;

	.left-box {
		.small {
			color: #f8d5a8;
			font-size: 28rpx;
			margin-left: 18rpx;
		}

		.pictrue {
			width: 40rpx;
			height: 45rpx;

			image {
				width: 100%;
				height: 100%;
			}
		}
	}

	.btn {
		color: #bbbbbb;
		font-size: 26rpx;
	}

	.icon-jiantou {
		margin-top: 6rpx;
	}
}

.cardVipA {
	position: absolute;
	background: url('~@/static/images/member.png') no-repeat;
	background-size: 100% 100%;
	width: 750rpx;
	height: 84rpx;
	bottom: -2rpx;
	left: 0;
	padding: 0 56rpx 0 135rpx;

	.left-box {
		font-size: 26rpx;
		color: #905100;
		font-weight: 400;
	}

	.btn {
		color: #905100;
		font-weight: 400;
		font-size: 24rpx;
	}

	.iconfont {
		font-size: 20rpx;
		margin: 4rpx 0 0 4rpx;
	}
}

.new-users {
	display: flex;
	flex-direction: column;
	height: 100%;
	background-color: #eef2f3;
	--bg-mesh:
		radial-gradient(circle at 0% 0%, rgba(255, 154, 158, 0.2) 0%, rgba(255, 154, 158, 0) 45%),
		radial-gradient(circle at 100% 0%, rgba(118, 75, 162, 0.1) 0%, rgba(118, 75, 162, 0) 50%),
		radial-gradient(circle at 100% 100%, rgba(255, 236, 210, 0.5) 0%, rgba(255, 236, 210, 0) 55%);
	background-image: var(--bg-mesh);
	background-repeat: no-repeat;
	background-size: cover;

	.sys-head {
		position: relative;
		width: 100%;
		// background: linear-gradient(90deg, $bg-star1 0%, $bg-end1 100%);

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
			color: #ffffff;
		}
	}

	.head {
		// background: #fff;

		.user-card {
			position: relative;
			width: 100%;
			height: 380rpx;
			margin: 0 auto;
			padding: 35rpx 28rpx;
			background-image: url('~@/static/images/user01.png');
			background-size: 100% auto;
			background-color: var(--view-theme);

			.user-info {
				z-index: 20;
				position: relative;
				display: flex;

				.headwear {
					position: absolute;
					right: -4rpx;
					top: -14rpx;
					width: 44rpx;
					height: 44rpx;

					image {
						width: 100%;
						height: 100%;
					}
				}

				.live {
					width: 28rpx;
					height: 28rpx;
					margin-left: 20rpx;
				}

				.bntImg {
					width: 120rpx;
					height: 120rpx;
					border-radius: 50%;
					text-align: center;
					line-height: 120rpx;
					background-color: unset;
					position: relative;

					.avatarName {
						font-size: 16rpx;
						color: #fff;
						text-align: center;
						background-color: rgba(0, 0, 0, 0.6);
						height: 37rpx;
						line-height: 37rpx;
						position: absolute;
						bottom: 0;
						left: 0;
						width: 100%;
					}
				}

				.avatar-box {
					position: relative;
					display: flex;
					align-items: center;
					justify-content: center;
					width: 120rpx;
					height: 120rpx;
					border-radius: 50%;

					&.on {
						.avatar {
							border: 2px solid #ffac65;
							border-radius: 50%;
							box-sizing: border-box;
						}
					}
				}

				.avatar {
					position: relative;
					width: 120rpx;
					height: 120rpx;
					border-radius: 50%;
				}

				.info {
					flex: 1;
					display: flex;
					flex-direction: column;
					justify-content: space-between;
					margin-left: 20rpx;
					padding: 20rpx 0;

					.name {
						display: flex;
						align-items: center;
						color: #fff;
						font-size: 31rpx;

						.nickname {
							max-width: 8em;
						}

						.vip {
							margin-left: 10rpx;

							image {
								width: 78rpx;
								height: 30rpx;
								display: block;
							}
						}
					}

					.num {
						display: flex;
						align-items: center;
						font-size: 26rpx;
						color: rgba(255, 255, 255, 0.6);

						image {
							width: 22rpx;
							height: 23rpx;
							margin-left: 20rpx;
						}
					}
				}
			}

			.message {
				align-self: flex-start;
				position: relative;
				margin-top: 15rpx;
				margin-right: 20rpx;

				.num {
					position: absolute;
					top: -8rpx;
					left: 18rpx;
					padding: 0 6rpx;
					height: 28rpx;
					border-radius: 12rpx;
					background-color: #fff;
					font-size: 18rpx;
					line-height: 28rpx;
					text-align: center;
					color: var(--view-theme);
				}

				.iconfont {
					font-size: 40rpx;
					color: #fff;
				}
			}

			.num-wrapper {
				z-index: 30;
				position: relative;
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-top: 22rpx;
				// padding: 0 47rpx;
				color: #fff;

				.num-item {
					width: 33.33%;
					text-align: center;

					& ~ .num-item {
						position: relative;

						&:before {
							content: '';
							position: absolute;
							width: 1rpx;
							height: 28rpx;
							top: 50%;
							margin-top: -14rpx;
							background-color: rgba(255, 255, 255, 0.4);
							left: 0;
						}
					}

					.num {
						font-size: 42rpx;
						font-weight: bold;
					}

					.txt {
						margin-top: 8rpx;
						font-size: 26rpx;
						color: rgba(255, 255, 255, 0.6);
					}
				}
			}

			.sign {
				z-index: 200;
				position: absolute;
				right: -12rpx;
				top: 80rpx;
				display: flex;
				align-items: center;
				justify-content: center;
				width: 120rpx;
				height: 60rpx;
				background: linear-gradient(90deg, rgba(255, 225, 87, 1) 0%, rgba(238, 193, 15, 1) 100%);
				border-radius: 29rpx 4rpx 4rpx 29rpx;
				color: #282828;
				font-size: 28rpx;
				font-weight: bold;
			}
		}

		.order-wrapper {
			background: #fff;
			margin: 0 30rpx;
			border-radius: 16rpx;
			position: relative;
			margin-top: -10rpx;

			.order-hd {
				justify-content: space-between;
				padding: 30rpx 20rpx 10rpx 30rpx;
				margin-top: 25rpx;
				font-size: 30rpx;
				color: #282828;

				.left {
					font-weight: bold;
				}

				.right {
					display: flex;
					align-items: center;
					color: #666666;
					font-size: 26rpx;

					.icon-jiantou {
						margin-left: 5rpx;
						font-size: 26rpx;
					}
				}
			}

			.order-bd {
				display: flex;
				padding: 0 0;

				.order-item {
					display: flex;
					flex-direction: column;
					justify-content: center;
					align-items: center;
					width: 20%;
					height: 140rpx;

					.pic {
						position: relative;
						text-align: center;

						.iconfont {
							font-size: 48rpx;
							color: var(--view-theme);
						}

						image {
							width: 58rpx;
							height: 48rpx;
						}
					}

					.txt {
						margin-top: 6rpx;
						font-size: 26rpx;
						color: #333;
					}
				}
			}
		}
	}

	.slider-wrapper {
		margin: 20rpx 30rpx;
		height: 130rpx;

		swiper,
		swiper-item {
			height: 100%;
		}

		image {
			width: 100%;
			height: 130rpx;
			border-radius: 16rpx;
		}
	}

	.user-menus {
		background-color: #fff;
		margin: 0 30rpx;
		border-radius: 16rpx;
		.column-box {
			padding: 30rpx 20rpx 10rpx 30rpx;
			.item {
				display: flex;
				align-items: center;
				margin-bottom: 40rpx;
				font-size: 26rpx;
				.name {
					flex: 1;
					text-align: left;
				}
				image {
					width: 40rpx;
					height: 40rpx;
					margin-right: 20rpx;
				}
				.icon-jiantou {
					font-size: 26rpx;
					color: rgb(96, 98, 102);
				}
				&:last-child::before {
					display: none;
				}
				&:last-child {
					margin-bottom: 20rpx;
				}
			}
		}
		.menu-title {
			padding: 30rpx 30rpx 40rpx;
			font-size: 30rpx;
			color: #282828;
			font-weight: bold;
		}

		.list-box {
			display: flex;
			flex-wrap: wrap;
			.item {
				position: relative;
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-direction: column;
				width: 25%;
				margin-bottom: 40rpx;
				font-size: 26rpx;
				color: #333333;
				.name {
					flex: 1;
					text-align: left;
				}
				image {
					width: 52rpx;
					height: 52rpx;
					margin-bottom: 18rpx;
				}

				&:last-child::before {
					display: none;
				}
			}
		}

		button {
			font-size: 28rpx;
		}
	}

	.phone {
		color: #fff;
		background-color: #ffffff80;
		border-radius: 15px;
		width: max-content;
		font-size: 24rpx;
		padding: 2px 10px;
		margin-top: 8rpx;
	}

	.order-status-num {
		min-width: 12rpx;
		background-color: #fff;
		color: var(--view-theme);
		border-radius: 15px;
		position: absolute;
		right: -14rpx;
		top: -15rpx;
		font-size: 20rpx;
		padding: 0 8rpx;
		border: 1px solid var(--view-theme);
	}

	.support {
		width: 219rpx;
		height: 74rpx;
		margin: 54rpx auto;
		display: block;
	}
}

.card-vip {
	display: flex;
	align-items: center;
	justify-content: space-between;
	position: relative;
	width: 690rpx;
	height: 134rpx;
	margin: -72rpx auto 0;
	background: url('~@/static/images/user_vip.png');
	background-size: cover;
	padding-left: 118rpx;
	padding-right: 34rpx;

	.left-box {
		font-size: 24rpx;
		color: #ae5a2a;

		.big {
			font-size: 28rpx;
		}

		.small {
			opacity: 0.8;
			margin-top: 10rpx;
		}
	}

	.btn {
		height: 52rpx;
		line-height: 52rpx;
		padding: 0 10rpx;
		text-align: center;
		background: #fff;
		border-radius: 28rpx;
		font-size: 26rpx;
		color: #ae5a2a;
	}
}

.setting {
	margin-top: 15rpx;
	margin-left: 15rpx;
	color: #fff;

	.iconfont {
		font-size: 40rpx;
	}
}

.new-users {
	padding-bottom: 0;
	padding-bottom: constant(safe-area-inset-bottom);
	padding-bottom: env(safe-area-inset-bottom);
}
</style>
