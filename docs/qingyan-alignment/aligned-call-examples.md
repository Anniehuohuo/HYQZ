# 对齐后的调用示例

## 1) 直连清言（用于与平台对齐对照）

### 1.1 获取 token
```bash
curl -sS -X POST "https://chatglm.cn/chatglm/assistant-api/v1/get_token" \
  -H "Content-Type: application/json" \
  -d '{"api_key":"<QINGYAN_API_KEY>","api_secret":"<QINGYAN_API_SECRET>"}'
```

### 1.2 单轮对话（stream_sync，便于评测采样）
```bash
curl -sS -X POST "https://chatglm.cn/chatglm/assistant-api/v1/stream_sync" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Accept-Encoding: identity" \
  -d '{"assistant_id":"<ASSISTANT_ID>","prompt":"<QUESTION>"}'
```

### 1.3 流式对话（stream，线上体验）
```bash
curl -N -sS -X POST "https://chatglm.cn/chatglm/assistant-api/v1/stream" \
  -H "Content-Type: application/json" \
  -H "Accept: text/event-stream" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Accept-Encoding: identity" \
  -d '{"assistant_id":"<ASSISTANT_ID>","prompt":"<QUESTION>"}'
```

## 2) 调用本系统（用于验证“当前调用环境”）

### 2.1 单轮（关闭 stream，拿 JSON reply 便于脚本评测）
```bash
curl -sS -X POST "<LOCAL_BASE_URL>/ai/chat" \
  -H "Content-Type: application/json" \
  -H "Authori-zation: <LOCAL_AUTH_TOKEN>" \
  -d '{"agent_id":"<LOCAL_AGENT_ID>","message":"<QUESTION>","stream":0}'
```

### 2.2 多轮（建议复用 session_id）
第 1 次调用返回 `session_id` 后，后续请求携带相同 `session_id`，后端会复用清言 `conversation_id` 来保持上下文一致。

