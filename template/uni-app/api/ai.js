import request from "@/utils/request.js";

export function aiChat(data) {
	return request.post("ai/chat", data, {
		noAuth: true
	});
}

