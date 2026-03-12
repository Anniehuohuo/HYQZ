import request from "@/utils/request.js";

export function aiChat(data) {
	return request.post("ai/chat", data, {
		noAuth: false
	});
}

export function getAgentMatrix() {
	return request.get("ai/agent_matrix", {}, {
		noAuth: false
	});
}

export function getChatHistory(data) {
	return request.get("ai/chat/history", data, {
		noAuth: false
	});
}

export function getRecentSession(data) {
	return request.get("ai/session/recent", data, {
		noAuth: false
	});
}

export function clearChatHistory(data) {
	return request.post("ai/chat/clear", data, {
		noAuth: false
	});
}

export function getAgentSaleInfo(data) {
	return request.get("ai/agent/sale_info", data, {
		noAuth: false
	});
}

export function getAgentAccess(data) {
	return request.get("ai/agent/access", data, {
		noAuth: false
	});
}
