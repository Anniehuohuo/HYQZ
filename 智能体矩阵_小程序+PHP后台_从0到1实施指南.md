# 智能体矩阵（小程序 + PHP 后台 + 数据库）从 0 到 1 实施指南（小白可照做）

> 目标：你有一个微信小程序前端 + PHP 后台，现在要加“智能体矩阵”能力：  
> - 后台能配置多个智能体（名称、封面、botId、能力开关、默认模型等）  
> - 前端自动渲染智能体列表  
> - 用户点开某个智能体后能顺滑对话（最好流式输出）  
> - 数据库能保存：用户、智能体配置、会话、消息、用量/计费（可选）  
>
> 你可以把“智能体矩阵”理解成：同一个聊天界面，换不同的 botId，就像换不同的客服/专家。

---

## 目录

1. 你要做出来的最终效果
2. 总体架构（推荐做法）
3. 数据库设计（最小可用）
4. 后台管理系统（PHP）要做的功能
5. API 设计（前端/后台/AI 网关）
6. AI 网关（关键）：如何让对话流畅（流式输出 + 会话）
7. 小程序前端改造步骤
8. 安全与合规（必须看）
9. 上线部署与运维清单
10. 小白“让 AI 帮你一步步完成”的提示词模板

---

## 1. 你要做出来的最终效果

### 1.1 用户侧（小程序）

- 首页/智能体页：展示一堆智能体卡片（从接口拿数据，不写死）
- 点某个智能体：进入聊天页，标题变成该智能体名称
- 发送消息：AI 能回答，最好是“边想边输出”（流式）
- 断网/切后台再回来：能自动重连，不丢消息
- 换另一个智能体：会话互不影响（每个智能体一份聊天记录）

### 1.2 管理员侧（后台）

- 能新增/编辑/启用/禁用智能体
- 能配置智能体的 botId（来自你在“扣子”平台创建的智能体）
- 能查看对话会话、消息（可选：导出、审查）
- 能查看用量/计费（可选）

---

## 2. 总体架构（推荐做法）

为了“流畅 + 安全 + 好维护”，推荐拆成三层：

1) 小程序前端  
2) PHP 业务后台（含管理后台 + DB）  
3) AI 网关服务（建议独立；也可以先在 PHP 里做简化版）

### 2.1 为什么需要 AI 网关（不要前端直连扣子）

- 密钥不能放前端：小程序源码容易被反编译/抓包，密钥会泄露
- 体验要流式：前端更适合跟“你自己的网关”做统一协议（delta/completed/done）
- 统一限流/风控：同一用户同时发 10 条会把扣子打挂，网关要做限流
- 统一日志与监控：出现问题能定位是前端、你后端、还是扣子

### 2.2 最小可行落地路线

- 第 1 期（先跑通）：HTTP 非流式（一次性返回答案）
- 第 2 期（体验升级）：改成 SSE 或 WebSocket 流式输出
- 第 3 期（运营成熟）：加用量计费、敏感词审核、知识库、人设等

---

## 3. 数据库设计（最小可用）

> 下面给的是“最小可用 + 可扩展”的表。你可以用 MySQL。

### 3.1 users（用户）

- `id` bigint PK
- `wx_openid` varchar(64) unique
- `nickname` varchar(64)
- `avatar` varchar(255)
- `created_at` datetime
- `updated_at` datetime

### 3.2 admin_users（后台管理员）

- `id` bigint PK
- `username` varchar(64) unique
- `password_hash` varchar(255)
- `role` varchar(32)（super_admin / operator / finance ...）
- `created_at` datetime
- `updated_at` datetime

### 3.3 bots（智能体配置：矩阵的“核心”）

- `id` bigint PK（你自己的自增 id）
- `bot_key` varchar(64) unique（你系统内部唯一标识，比如 `copywriting_ip_qa`）
- `bot_name` varchar(64)（展示名）
- `bot_avatar` varchar(255)（封面）
- `coze_bot_id` varchar(64)（扣子平台的 botId）
- `enabled` tinyint(1)（是否启用）
- `sort` int（排序）
- `default_model` varchar(64)（可选）
- `default_welcome` text（可选：欢迎语）
- `created_at` datetime
- `updated_at` datetime

### 3.4 conversations（会话：每个用户在某个 bot 下的一段聊天）

- `id` bigint PK
- `user_id` bigint index
- `bot_id` bigint index（对应 bots.id）
- `provider` varchar(32)（比如 `coze`）
- `provider_conversation_id` varchar(128)（扣子侧会话 id，如果有）
- `title` varchar(128)（可选）
- `created_at` datetime
- `updated_at` datetime

### 3.5 messages（消息：用户/AI 的每条对话）

- `id` bigint PK
- `conversation_id` bigint index
- `role` varchar(16)（`user`/`assistant`/`system`）
- `content` longtext
- `reasoning_content` longtext（可选：思考过程，如果你要展示）
- `status` varchar(16)（`partial`/`completed`/`failed`）
- `created_at` datetime

### 3.6 usage_logs（用量/计费，可选）

- `id` bigint PK
- `user_id` bigint index
- `bot_id` bigint index
- `conversation_id` bigint index
- `tokens_in` int（可选）
- `tokens_out` int（可选）
- `cost` decimal(10,4)（可选）
- `created_at` datetime

---

## 4. 后台管理系统（PHP）要做的功能

> 你的 PHP 后台 = “配置中心 + 数据中心”，AI 网关再读取这些配置去调用扣子。

### 4.1 必做页面（最小可用）

1) 智能体管理
- 列表：搜索/排序/启用禁用
- 新增/编辑：bot_name、bot_avatar、coze_bot_id、enabled、sort、default_welcome

2) 会话管理（可选但很推荐）
- 按用户/按智能体筛选会话
- 查看某个会话的消息列表

3) 用户管理（基础）
- 查看用户列表、最近活跃时间（可选）

### 4.2 强烈建议后续增加

- 敏感词库与审查
- 用量统计（按日/按 bot）
- 黑名单/封禁

---

## 5. API 设计（前端/后台/AI 网关）

你需要 3 类 API：

1) 小程序调用的业务 API（PHP）
2) 管理后台 API（PHP）
3) AI 对话 API（AI 网关）

### 5.1 小程序业务 API（PHP）

#### A. 获取智能体列表

- `GET /api/bots`
- 返回示例：
```json
{
  "code": 0,
  "data": [
    {
      "id": 1,
      "botKey": "copywriting_ip_qa",
      "name": "IP问答型文案智能体",
      "avatar": "https://...",
      "enabled": true,
      "welcome": "你好，我可以帮你做..."
    }
  ]
}
```

#### B. 获取会话历史

- `GET /api/conversations/{conversationId}/messages`
- 返回 messages（按时间排序）

### 5.2 管理后台 API（PHP）

- `POST /admin/bots` 新增
- `PUT /admin/bots/{id}` 编辑
- `PATCH /admin/bots/{id}/enabled` 启用禁用
- `GET /admin/conversations` 会话列表
- `GET /admin/conversations/{id}/messages` 消息列表

### 5.3 AI 对话 API（AI 网关）

> 为了“流畅”，强烈推荐流式协议。两种常见方式：
> - SSE（简单，浏览器/H5 更友好；小程序也能做但要看实现方式）
> - WebSocket（适合小程序，体验接近你看到的项目）

#### 方案 1：WebSocket（推荐给小程序）

- `wss://your-domain/ws/chat?token=xxx`
- 客户端发送：
```json
{
  "botId": 1,
  "conversationId": 123,
  "content": "我想写一个火锅店的开业口播文案",
  "clientMsgId": "msg-1700000000000"
}
```
- 服务端推送（流式）：
```json
{ "event": "delta", "clientMsgId": "msg-...", "content": "好的，" }
{ "event": "delta", "clientMsgId": "msg-...", "content": "我先问你" }
{ "event": "completed", "clientMsgId": "msg-...", "content": "完整答案..." }
{ "event": "done", "clientMsgId": "msg-..." }
```

#### 方案 2：HTTP + SSE（可选）

- `POST /api/chat/stream`（响应类型 `text/event-stream`）

---

## 6. AI 网关（关键）：如何让对话流畅

### 6.1 网关要做的 6 件事

1) 鉴权：校验用户 token（不要把扣子密钥给前端）
2) 读取 bot 配置：从 PHP/DB 拿 `coze_bot_id` 等配置（建议加 Redis 缓存）
3) 会话管理：创建/绑定 `conversationId`，并保存到 DB
4) 调用扣子：把用户消息发给扣子智能体（带 botId/会话 id）
5) 流式转发：把扣子返回的流式内容转成 `delta/completed/done` 推给前端
6) 落库：把用户消息和 AI 最终答案写入 `messages`

### 6.2 “顺滑体验”细节清单（照做就顺）

- 立即回显：用户发出消息时，前端立刻把这条消息插入列表（不用等后端）
- 先插入空的 assistant 消息：收到 delta 就追加（体感马上开始输出）
- 断线重连：前端断了就重连；后端支持按 `clientMsgId` 去重
- 只保存 completed：delta 不用存库，存最终答案即可（节省 DB）
- 限流：同一用户同一时间最多 1～2 个生成任务（Redis 计数器）
- 超时兜底：超过 N 秒无响应就结束并提示（避免一直转圈）

---

## 7. 小程序前端改造步骤

### 7.1 第一步：把智能体列表从“写死”改成“接口拉取”

- 页面加载时调用 `GET /api/bots`
- 用返回的数组渲染卡片
- 点击卡片跳转：`/pages/chat/chat?botId=1&name=xxx`

### 7.2 第二步：实现聊天页的“流式消息更新”

聊天页的核心状态：
- `botId`
- `conversationId`（本地存一份：`conversationId:{botId}`）
- `messages[]`（本地存一份：`history:{botId}`）
- `socketConnected`（断线重连）

流程：
1) onLoad 读取 botId
2) 建立 WebSocket
3) 发送消息时：
   - 先 push 一条 user 消息
   - 再 push 一条空的 assistant 消息（id=clientMsgId）
   - 发送 WS 消息给网关
4) 收到 `delta`：找到对应 clientMsgId 的 assistant 消息，追加 content
5) 收到 `completed`：替换成完整内容
6) 收到 `done`：保存历史到本地，并停止输入状态

---

## 8. 安全与合规（必须看）

- 扣子密钥永远不要出现在小程序端
- 后端对输入做基本过滤：长度限制、敏感词（可后续）
- 做频率限制：避免被刷爆导致扣子费用爆炸
- 记录审计日志：出了事能追踪

---

## 9. 上线部署与运维清单

- 数据库备份策略（每日备份）
- 监控：接口耗时、错误率、WS 在线数
- 日志：按 userId/botId/conversationId 关联
- 灰度：先让 10% 用户可见新智能体

---

## 10. 小白“让 AI 帮你一步步完成”的提示词模板

你可以把下面每一段复制给 AI（比如我），让 AI 按你的现有项目落地。

### 10.1 让 AI 帮你做数据库

> 提示词：
> 我有一个 PHP 项目，数据库是 MySQL。请为“bots、conversations、messages、users、admin_users、usage_logs”生成建表 SQL。  
> 要求：字段包含 botId、coze_bot_id、enabled、sort；messages 用 longtext；所有表有 created_at/updated_at。  
> 并给出索引建议。

### 10.2 让 AI 帮你做 PHP 接口（bots/list）

> 提示词：
> 我的后端是 PHP（告诉 AI 你用 Laravel 还是原生）。  
> 请实现 `GET /api/bots` 返回启用的智能体列表，按 sort 升序。  
> 返回字段：id、botKey、name、avatar、enabled、welcome。  
> 需要鉴权（token 在 header：Authorization: Bearer xxx）。

### 10.3 让 AI 帮你做后台管理页面

> 提示词：
> 我有一个 PHP 后台管理系统。请帮我设计并实现“智能体管理”模块：列表、新增、编辑、启用禁用。  
> 字段：bot_name、bot_avatar、coze_bot_id、enabled、sort、default_welcome。  
> 并给出表单校验规则。

### 10.4 让 AI 帮你做 AI 网关（第一期：HTTP 非流式）

> 提示词：
> 我想先做一个最简版本：`POST /api/chat/send`，输入 botId、conversationId、content，后端调用扣子智能体 API，拿到最终答案后一次性返回。  
> 同时把用户消息和 AI 回复写入 messages。  
> 请给出接口设计、伪代码、错误处理与超时处理。

### 10.5 让 AI 帮你升级成流式（第二期：WebSocket）

> 提示词：
> 现在我要把聊天升级为 WebSocket 流式：前端发送 content，后端调用扣子流式接口，把内容拆成 delta 推给前端，最后推 completed/done。  
> 要求：支持断线重连、按 clientMsgId 去重、Redis 限流。  
> 请给出：协议、服务端事件流设计、数据落库策略。

---

## 结语（你下一步立刻做什么）

如果你是小白，最稳的下一步顺序是：
1) 先把数据库表建出来（bots/conversations/messages/users）
2) 先把后台的“智能体管理”做出来（能录入 coze_bot_id）
3) 小程序先实现“智能体列表从接口拉取”
4) 先做 HTTP 非流式聊天跑通
5) 再升级 WebSocket 流式

