import request from '@/utils/request.js';

export function getAiPowerQuota() {
	return request.get('ai/power/quota');
}

export function getAiPowerRechargeConfig() {
	return request.get('ai/power/recharge_config');
}

export function aiPowerRecharge(data) {
	return request.post('ai/power/recharge', data);
}

