<template>
	<view class="page">
		<scroll-view scroll-y="true" class="scroll">
			<view class="hero">
				<view class="heroTitle">家长课堂</view>
				<view class="heroSub">给家长的一份“可直接照做”的养育方法清单</view>
			</view>

			<view class="section" v-for="(s, i) in sections" :key="i">
				<view class="sectionHd">
					<view class="sectionTitle">{{ s.title }}</view>
					<view class="sectionTag" v-if="s.tag">{{ s.tag }}</view>
				</view>
				<view class="card">
					<view class="p" v-if="s.desc">{{ s.desc }}</view>
					<view class="li" v-for="(it, j) in s.items" :key="j">
						<view class="dot">•</view>
						<view class="liText">{{ it }}</view>
					</view>
					<view class="btnRow" v-if="s.cta">
						<view class="btn" @click="openTip(s.cta)">{{ s.ctaText || '查看示例' }}</view>
					</view>
				</view>
			</view>

			<view class="footer">
				<view class="footerTitle">使用方法</view>
				<view class="footerText">遇到具体场景时，把孩子的年龄、发生了什么、你的目标写清楚，再来问 AI，会更容易得到贴合你家的建议。</view>
			</view>

			<view class="safePad"></view>
		</scroll-view>

		<view class="popupMask" v-if="popup.show" @click="closeTip"></view>
		<view class="popup" v-if="popup.show">
			<view class="popupHd">
				<view class="popupTitle">{{ popup.title }}</view>
				<view class="popupClose" @click="closeTip">×</view>
			</view>
			<scroll-view scroll-y class="popupBd">
				<view class="popupP" v-for="(p, i) in popup.lines" :key="i">{{ p }}</view>
			</scroll-view>
			<view class="popupFt">
				<view class="popupBtn" @click="copyText(popup.copyText)">复制话术</view>
				<view class="popupBtn ghost" @click="closeTip">我知道了</view>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	data() {
		return {
			sections: [
				{
					title: '情绪先行：先接住情绪，再谈道理',
					tag: '高频场景',
					desc: '孩子情绪上来时，大脑很难听懂“道理”。先让他感觉被理解，才能进入协商。',
					items: ['先描述事实：我看到你很生气/很委屈。', '再命名情绪：因为…所以你难受，对吗？', '最后给选择：你想先抱抱，还是先喝口水？'],
					cta: 'emotion',
					ctaText: '话术示例'
				},
				{
					title: '规则清晰：少说“应该”，多说“我们怎么做”',
					tag: '立规矩',
					desc: '规则要具体、可执行、可重复，最好用“如果…那么…”表达。',
					items: ['把规则写成一句话：晚饭后先收拾桌面，再玩。', '提前提醒：还有5分钟就要收了。', '一致执行：每次都按规则走，避免讨价还价。'],
					cta: 'rule',
					ctaText: '示例规则'
				},
				{
					title: '正向反馈：表扬努力与方法，而不是表扬“聪明”',
					tag: '提升自信',
					desc: '表扬过程能让孩子更愿意继续尝试，也更能抗挫折。',
					items: ['表扬行为：你刚才主动把书放回去了。', '表扬策略：你用分步骤的方法做题，很聪明。', '表扬坚持：你失败后还愿意再试一次。'],
					cta: 'praise',
					ctaText: '表扬模板'
				},
				{
					title: '亲子沟通：用“我信息”减少对抗',
					tag: '减少吵架',
					desc: '把指责换成感受+需求+请求，更容易被听见。',
					items: ['感受：我有点着急/担心。', '需求：我需要现在安静一点。', '请求：你能把声音放小一点吗？'],
					cta: 'iMessage',
					ctaText: '说法示例'
				}
			],
			popup: {
				show: false,
				title: '',
				lines: [],
				copyText: ''
			}
		};
	},
	methods: {
		openTip(key) {
			const map = {
				emotion: {
					title: '情绪接住话术',
					lines: ['我看到你现在特别难受。', '这件事对你来说很重要，所以你会这么生气。', '我们先做3次深呼吸，等你舒服一点，再一起想办法。'],
					copyText: '我看到你现在特别难受。\n这件事对你来说很重要，所以你会这么生气。\n我们先做3次深呼吸，等你舒服一点，再一起想办法。'
				},
				rule: {
					title: '规则表达模板',
					lines: ['如果你想看动画，那么先把作业的第一题做完。', '如果你想继续玩，那么先把玩具收进盒子里。', '我们可以商量顺序，但规则不会消失。'],
					copyText: '如果你想看动画，那么先把作业的第一题做完。\n如果你想继续玩，那么先把玩具收进盒子里。\n我们可以商量顺序，但规则不会消失。'
				},
				praise: {
					title: '正向表扬模板',
					lines: ['你刚才用“先读题再圈关键字”的方法，很有效。', '你愿意再试一次，这就是进步。', '你今天比昨天更能控制情绪了。'],
					copyText: '你刚才用“先读题再圈关键字”的方法，很有效。\n你愿意再试一次，这就是进步。\n你今天比昨天更能控制情绪了。'
				},
				iMessage: {
					title: '我信息表达示例',
					lines: ['我有点担心你太晚睡会不舒服。', '我需要我们现在先把洗漱完成。', '你可以选先洗脸还是先刷牙？'],
					copyText: '我有点担心你太晚睡会不舒服。\n我需要我们现在先把洗漱完成。\n你可以选先洗脸还是先刷牙？'
				}
			};
			const tip = map[key];
			if (!tip) return;
			this.popup = { show: true, title: tip.title, lines: tip.lines, copyText: tip.copyText };
		},
		closeTip() {
			this.popup = { show: false, title: '', lines: [], copyText: '' };
		},
		copyText(text) {
			if (!text) return;
			uni.setClipboardData({ data: text });
		}
	}
};
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f6f7fb;
}
.scroll {
	height: 100vh;
}
.hero {
	padding: 26rpx 24rpx 10rpx;
}
.heroTitle {
	font-size: 44rpx;
	font-weight: 700;
	color: #111827;
}
.heroSub {
	margin-top: 8rpx;
	font-size: 26rpx;
	color: #6b7280;
}
.section {
	padding: 14rpx 24rpx 0;
}
.sectionHd {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12rpx;
}
.sectionTitle {
	font-size: 30rpx;
	font-weight: 700;
	color: #111827;
}
.sectionTag {
	font-size: 22rpx;
	color: #ef7a2f;
	background: rgba(239, 122, 47, 0.12);
	padding: 6rpx 12rpx;
	border-radius: 999rpx;
}
.card {
	background: #ffffff;
	border-radius: 16rpx;
	padding: 18rpx 18rpx;
	box-shadow: 0 8rpx 26rpx rgba(17, 24, 39, 0.06);
}
.p {
	font-size: 26rpx;
	color: #374151;
	line-height: 40rpx;
	margin-bottom: 10rpx;
}
.li {
	display: flex;
	align-items: flex-start;
	margin-top: 10rpx;
}
.dot {
	width: 26rpx;
	color: #ef7a2f;
	font-size: 30rpx;
	line-height: 34rpx;
}
.liText {
	flex: 1;
	font-size: 26rpx;
	color: #374151;
	line-height: 40rpx;
}
.btnRow {
	margin-top: 16rpx;
	display: flex;
	justify-content: flex-end;
}
.btn {
	background: #ef7a2f;
	color: #fff;
	font-size: 26rpx;
	padding: 12rpx 18rpx;
	border-radius: 12rpx;
}
.footer {
	margin: 18rpx 24rpx 0;
	padding: 18rpx;
	border-radius: 16rpx;
	background: #ffffff;
	box-shadow: 0 8rpx 26rpx rgba(17, 24, 39, 0.06);
}
.footerTitle {
	font-size: 28rpx;
	font-weight: 700;
	color: #111827;
}
.footerText {
	margin-top: 10rpx;
	font-size: 26rpx;
	color: #6b7280;
	line-height: 40rpx;
}
.safePad {
	height: 40rpx;
}

.popupMask {
	position: fixed;
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.45);
	z-index: 998;
}
.popup {
	position: fixed;
	left: 24rpx;
	right: 24rpx;
	bottom: 24rpx;
	background: #fff;
	border-radius: 18rpx;
	z-index: 999;
	overflow: hidden;
	box-shadow: 0 10rpx 34rpx rgba(17, 24, 39, 0.18);
}
.popupHd {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 18rpx;
	background: #fff7f1;
}
.popupTitle {
	font-size: 28rpx;
	font-weight: 700;
	color: #111827;
}
.popupClose {
	width: 54rpx;
	height: 54rpx;
	text-align: center;
	line-height: 54rpx;
	font-size: 40rpx;
	color: #6b7280;
}
.popupBd {
	max-height: 520rpx;
	padding: 18rpx;
}
.popupP {
	font-size: 26rpx;
	color: #374151;
	line-height: 42rpx;
	margin-bottom: 10rpx;
}
.popupFt {
	display: flex;
	padding: 18rpx;
	gap: 12rpx;
}
.popupBtn {
	flex: 1;
	text-align: center;
	background: #ef7a2f;
	color: #fff;
	padding: 14rpx 0;
	border-radius: 12rpx;
	font-size: 26rpx;
}
.popupBtn.ghost {
	background: #f3f4f6;
	color: #111827;
}
</style>
