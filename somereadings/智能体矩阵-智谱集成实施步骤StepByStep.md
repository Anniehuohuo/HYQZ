# 智能体矩阵（Agent Matrix）× 智谱应用集成实施步骤（Step by Step）

> 目标：在现有 HYQZ 项目中，实现「从智谱应用到小程序前端」的完整智能体矩阵能力，包括：
>
> - 后台可视化配置多个智能体（组合数据）
> - 前端展示智能体矩阵列表
> - 用户选择某智能体后，发起真实对话（调用智谱 `/v3/application/invoke`）

本文档面向「执行这个任务的 AI 开发助手」，要求严格按步骤实施，必要时可根据项目实际微调，但不改变整体架构思路。

---

## 一、整体架构与约定

1. 智谱侧：

   - 使用「对话型应用 / 文本型应用」能力，在智谱控制台创建一个或多个应用（智能体）。
   - 每个应用对应一个唯一 `app_id`。
   - 对话调用统一使用接口：`POST https://open.bigmodel.cn/api/llm-application/open/v3/application/invoke`。
2. 本项目侧（HYQZ，CRMEB v5）：

   - 使用系统配置（`SystemConfig`）保存：
     - `zhupu_api_key`：智谱 API Key（仅后端可见）。
     - 可选：`zhupu_base_url`：智谱 API 基础地址（默认上述地址）。
   - 使用组合数据（`SystemGroup` + `SystemGroupData`）保存智能体列表，配置名约定为：`agent_matrix`。
   - 新增服务层 `AgentChatServices` 封装：
     - 智能体列表读取（从组合数据）。
     - 与智谱接口的 HTTP 调用与结果解析。
   - 新增前台 API 控制器 `AgentController`（命名空间：`app\api\controller\v1\ai`）提供：
     - `GET /api/v1/ai/agents`：获取智能体列表。
     - `POST /api/v1/ai/chat`：与具体智能体对话。
   - 前端 uni-app：
     - `pages/ai/agents.vue`：从 `/ai/agents` 拉取列表，渲染矩阵。
     - `pages/ai/chat.vue`：通过 `/ai/chat` 与智谱智能体真实对话。
3. 基本风格：

   - 后端统一输出 JSON，使用 CRMEB 内置 `app('json')->success()` 与 `app('json')->fail()`。
   - 前端只关心统一的接口结构，不直接接触智谱字段。

---

## 二、前置检查与环境准备

### 2.1 项目结构确认

1. 项目根目录：`f:\项目\HYQZ\`
2. 关键子目录：
   - 后端 API：`crmeb\app\api\`
   - uni-app 前端：`template\uni-app\`
   - 智能体相关前端页面：
     - 智能体矩阵：`template\uni-app\pages\ai\agents.vue`
     - 智能体聊天：`template\uni-app\pages\ai\chat.vue`

### 2.2 智谱控制台准备

执行顺序：

1. 登录智谱 AI 控制台，进入「应用 / 智能体」模块。
2. 创建一个对话型应用（或文本型应用），配置基础：
   - 选择合适模型（如 `glm-4`、`glm-4-air` 等，根据业务与成本）。
   - 配置系统提示词（角色设定）、知识库（可选）、工具（可选）。
3. 保存后，记录：
   - `app_id`（智能体应用 ID）。
4. 打开智谱接口文档，用 Postman 或 curl 测一下：
   - `POST https://open.bigmodel.cn/api/llm-application/open/v3/application/invoke`
   - Header：
     - `Authorization: <你的APIkey>`
     - `Content-Type: application/json`
   - Body（对话类最小示例）：
     ```json
     {
       "app_id": 1855923672330727424,
       "stream": false,
       "send_log_event": false,
       "messages": [
         {
           "role": "user",
           "content": [
             {
               "value": "你好",
               "type": "input"
             }
           ]
         }
       ]
     }
     ```
5. 确认能够正常返回 ：
   - 存在 `conversation_id`。
   - `choices[0].messages.content.type = "text"` 且有合理的 `msg` 文本。

---

## 三、后台配置层设计与实施

### 3.1 系统配置：保存智谱 API Key

目的：在 CRMEB 后台集中管理敏感信息，不暴露给前端。

实施建议（可按项目实际配置方式微调）：

1. 打开 CRMEB 管理后台，进入「系统设置 → 系统配置」。
2. 找到合适的配置分组（如「接口配置」或新建「智谱配置」）。
3. 新增两个配置项：
   - `zhupu_api_key`：
     - 类型：输入框（单行文本）。
     - 说明：智谱 API Key，用于调用智谱智能体接口。
   - `zhupu_base_url`（可选）：
     - 类型：输入框。
     - 默认值：`https://open.bigmodel.cn/api/llm-application/open`
4. 在代码中规划读取方式（后续服务层会用到）：
   - 使用 `sys_config('zhupu_api_key')` 获取 API Key。
   - 使用 `sys_config('zhupu_base_url', 'https://open.bigmodel.cn/api/llm-application/open')` 获取基础 URL。

### 3.2 组合数据：定义智能体矩阵配置结构

目的：无需新建业务表，通过 CRMEB 组合数据在后台配置多个智能体。

实施步骤：

1. 在后端代码中，组合数据的服务类位于：

   - `crmeb\app\services\system\config\SystemGroupServices.php`
   - `crmeb\app\services\system\config\SystemGroupDataServices.php`
2. 在 CRMEB 管理后台：

   - 进入「设置 → 组合数据」模块（一般在系统设置下）。
3. 新建一组组合数据：

   - 配置名称（config_name）：`agent_matrix`（约定值，后端将使用该名字取数据）。
   - 标题（可见名称）：例如「智能体矩阵配置」。
   - 字段设计建议：
     - `key`：
       - 类型：输入框。
       - 作用：内部唯一标识，例如 `a_comm_1`。
     - `cate`：
       - 类型：输入框。
       - 作用：分类代码，例如 `comm`、`study`。
     - `cate_name`：
       - 类型：输入框。
       - 作用：分类名称，例如「亲子沟通」「学习规划」。
     - `abbr`：
       - 类型：输入框。
       - 作用：用于前端显示的一个大写汉字或字母，例如「沟」「学」。
     - `name`：
       - 类型：输入框。
       - 作用：智能体名称，例如「沟通教练」。
     - `desc`：
       - 类型：多行文本。
       - 作用：一句话描述智能体能力。
     - `tags`：
       - 类型：输入框。
       - 规则：多个标签用英文逗号分隔，例如 `共情,边界,修复`。
     - `app_id`：
       - 类型：输入框。
       - 作用：智谱应用 ID（纯数字或字符串，根据智谱返回为准）。
     - `sort`：
       - 类型：数字。
       - 作用：排序，数值越小越靠前。
     - `status`：
       - 类型：单选（radio），如「显示 / 隐藏」。
       - 作用：控制前端是否展示该智能体。
4. 在该组合数据下新增至少 1 条配置记录作为测试：

   - 填写上述字段，并确保 `status = 显示`。

### 3.3 后台菜单：增加“智能体管理”入口（可选但推荐）

目的：让运营人员从左侧菜单看到一个独立的「智能体管理」入口，实际指向组合数据页面。

实施步骤（略要）：

1. 在 CRMEB 后台「系统设置 → 菜单管理」中新增菜单项：
   - 名称：智能体管理。
   - 类型：菜单。
   - 路由地址：指向组合数据中 `agent_matrix` 对应的管理页面（参考现有组合数据菜单配置）。
2. 将该菜单绑定到合适的权限角色（如超级管理员）。

---

## 四、后端业务实现（API 与服务）

### 4.1 新增服务：AgentChatServices

目的：集中封装智能体列表获取与智谱调用逻辑，避免 Controller 里逻辑过重。

预期位置：

- 文件路径（建议）：`crmeb\app\services\ai\AgentChatServices.php`
- 命名空间：`app\services\ai`

核心职责：

1. 从组合数据中读取智能体列表：

   - 依赖 `SystemGroupDataServices::getConfigNameValue('agent_matrix')`。
   - 过滤掉 `status != 1` 的数据。
   - 整理成前端友好的结构。
2. 调用智谱接口进行对话：

   - 读取系统配置 `zhupu_api_key`、`zhupu_base_url`。
   - 组装 `POST /v3/application/invoke` 请求体。
   - 根据前端传入内容选择：
     - 仅文本输入（最小 MVP 实现）。
     - 未来可扩展文件上传、知识库文档指定等能力。
   - 解析返回结果，提取：
     - `conversation_id`。
     - 文本回复内容（`type = text` 时的 `msg`）。

实现要点规划：

1. 方法一：`public function getAgents(): array`

   - 调用 `SystemGroupDataServices->getConfigNameValue('agent_matrix')`。
   - 返回字段示例：
     ```json
     [
       {
         "key": "a_comm_1",
         "cate": "comm",
         "cate_name": "亲子沟通",
         "abbr": "沟",
         "name": "沟通教练",
         "desc": "把冲突变成合作...",
         "tags": ["共情", "边界", "修复"],
         "app_id": "1855923672330727424",
         "sort": 1
       }
     ]
     ```
   - 注意：`tags` 字段需要把英文逗号字符串拆为数组。
2. 方法二：`public function chat(int $uid, string $agentKey, string $content, ?string $conversationId = null): array`

   - 通过 `agentKey` 从智能体列表中查找对应配置，取得 `app_id`。
   - 通过 `sys_config('zhupu_api_key')` 获取 API Key；如为空，应抛出异常或返回错误。
   - 构造请求体（仅文本场景）：
     ```json
     {
       "app_id": "<app_id>",
       "conversation_id": "<可选>",
       "stream": false,
       "send_log_event": false,
       "messages": [
         {
           "role": "user",
           "content": [
             {
               "value": "<content>",
               "type": "input"
             }
           ]
         }
       ]
     }
     ```
   - 使用 `crmeb\services\HttpService::postRequest()` 发送请求：
     - URL：`<zhupu_base_url>/v3/application/invoke`。
     - Header：
       - `Authorization: <API Key>`。
       - `Content-Type: application/json`。
     - 注意：需要以 JSON 字符串形式传递 Body（而非表单）。
   - 解析返回 JSON：
     - 优先获取 `conversation_id`。
     - 在非流式情况下，从 `choices[0].messages.content` 中寻找 `type = "text"` 的 `msg`。
   - 返回统一结构，例如：
     ```php
     [
       'conversation_id' => 'xxx',
       'reply' => '模型回复文本',
       'message_type' => 'text',
     ]
     ```

### 4.2 新增前台 API 控制器：AgentController

目的：面向前端的 REST API 封装，隐藏智谱细节。

预期位置：

- 文件路径：`crmeb\app\api\controller\v1\ai\AgentController.php`
- 命名空间：`app\api\controller\v1\ai`

接口一：获取智能体列表

1. 路径：`GET /api/v1/ai/agents`
2. 逻辑：

   - 注入或通过 `app()->make(AgentChatServices::class)` 获取服务实例。
   - 调用 `getAgents()` 获取列表。
   - 使用 `app('json')->success($data)` 返回。
3. 返回示例：

   ```json
   [
     {
       "key": "a_comm_1",
       "cate": "comm",
       "cate_name": "亲子沟通",
       "abbr": "沟",
       "name": "沟通教练",
       "desc": "把冲突变成合作...",
       "tags": ["共情", "边界", "修复"]
     }
   ]
   ```

接口二：智能体对话

1. 路径：`POST /api/v1/ai/chat`
2. 请求体（JSON）约定：
   ```json
   {
     "agent_key": "a_comm_1",
     "content": "孩子总是顶嘴怎么办？",
     "conversation_id": "可选，上一轮会话id"
   }
   ```
3. 逻辑：
   - 从 `Request` 中获取上述三个字段，进行基础校验：
     - `agent_key` 必填。
     - `content` 必填且非空字符串。
   - 获取当前登录用户 ID（如有登录机制，可从 `$request->uid()` 或 `user()` 中获取；如未登录则可为 0 或游客）。
   - 调用 `AgentChatServices->chat($uid, $agentKey, $content, $conversationId)`。
   - 将结果包装并返回：
     ```json
     {
       "conversation_id": "...",
       "reply": "模型回复文本",
       "message_type": "text"
     }
     ```
4. 错误处理：
   - 若 `agent_key` 不存在对应配置：返回「智能体不存在」错误。
   - 若 `zhupu_api_key` 未配置：返回「智谱配置缺失」错误。
   - 若 HTTP 请求失败或返回结构异常：记录日志，返回「智能体服务异常，请稍后重试」。

### 4.3 在 v1 路由文件中注册新接口

文件位置：

- `crmeb\app\api\route\v1.php`

实施步骤：

1. 引入必要命名空间（如已有通用）即可。
2. 在合适的 Route::group 中新增路由，建议放到需登录的用户组下，或独立一组：
   - 示例（仅示意，实际需根据项目路由组织方式调整）：
     ```php
     Route::group(function () {
         Route::get('ai/agents', 'v1.ai.AgentController/agents')->option(['real_name' => '智能体列表']);
         Route::post('ai/chat', 'v1.ai.AgentController/chat')->option(['real_name' => '智能体对话']);
     })->middleware(\app\http\middleware\AllowOriginMiddleware::class)
       ->middleware(\app\api\middleware\StationOpenMiddleware::class)
       ->middleware(\app\api\middleware\AuthTokenMiddleware::class, true)
       ->option(['mark' => 'ai', 'mark_name' => '智能体相关接口']);
     ```
3. 确保接口与前端计划访问的路径一致（`/api/v1/ai/agents` 与 `/api/v1/ai/chat`）。

---

## 五、前端 uni-app 实现步骤

### 5.1 智能体矩阵页：agents.vue

文件位置：

- `template\uni-app\pages\ai\agents.vue`

当前状态（推测）：

- 页面中 `data()` 部分存在一个写死的智能体数组，用于展示矩阵。

实施目标：

1. 去掉写死的智能体列表。
2. 页面加载时，通过 `/ai/agents` 接口异步获取。
3. 点击某个卡片时，跳转到 `chat.vue`，并携带 `agent_key` 和展示所需的信息。

实施步骤：

1. 在 uni-app 项目中新增一个 API 封装（如果已有公共 api.js，可在其中补充）：
   - 位置：`template\uni-app\api\api.js` 或新建 `ai.js`。
   - 定义方法：
     - `getAgents()` → `GET /api/v1/ai/agents`。
2. 在 `agents.vue` 中：
   - 引入上述 API 方法。
   - 在 `onLoad` 或 `onShow` 生命周期中调用 `getAgents()`，将返回结果赋值给 `agents`。
   - 渲染逻辑保持不变，只是数据来源从本地改为接口。
3. 点击卡片时：
   - 使用 `uni.navigateTo` 跳转至 `/pages/ai/chat`。
   - 通过 `url` 携带参数，如：
     - `agent_key`、`name`、`desc`、`abbr` 等。

### 5.2 智能体聊天页：chat.vue

文件位置：

- `template\uni-app\pages\ai\chat.vue`

当前状态（推测）：

- 存在本地模拟回复逻辑（不调用后端）。

实施目标：

1. 支持从路由参数中读取当前智能体信息。
2. 管理本地 `conversation_id` 状态，用于和后端保持会话上下文。
3. 改造发送消息逻辑，调用 `/ai/chat` 后端接口获取真实回复。

实施步骤：

1. 数据结构调整：
   - 新增字段：
     - `agent_key`：当前会话对应的智能体标识。
     - `conversation_id`：会话 ID，初始为空字符串。
   - 保留消息数组 `messages`，结构区分「自己」和「智能体」。
2. 在 `onLoad` 中：
   - 从 `options` 中读取 `agent_key`、`name`、`desc` 等（由 `agents.vue` 传入）。
   - 将 `agent_key` 存到本地。
3. 发送消息流程：
   - 用户输入文本后：
     1. 若输入为空，直接 return。
     2. 立即将用户消息 push 到 `messages` 中显示（role=me）。
     3. 调用统一封装的 API：
        - 路径：`POST /api/v1/ai/chat`。
        - Body：
          ```json
          {
            "agent_key": "<agent_key>",
            "content": "<用户输入>",
            "conversation_id": "<当前conversation_id，可为空>"
          }
          ```
     4. 接口返回后：
        - 更新本地 `conversation_id = res.conversation_id`。
        - 将 `res.reply` 以「智能体消息」形式 push 到 `messages` 中显示。
     5. 若接口报错：
        - 使用 `uni.showToast({ title: '智能体服务异常，请稍后重试', icon: 'none' })` 提示用户。
4. 暂不实现流式（SSE）效果：
   - 保持 `stream: false`。
   - 一次性获取完整回复文本。
   - 未来如需打字机效果，再单独设计前后端流式实现。

---

## 六、端到端联调与验证

### 6.1 基础功能验证流程

按如下顺序进行联调：

1. 在智谱控制台确认某应用可正常通过 `/v3/application/invoke` 接口返回结果。
2. 在 CRMEB 后台：
   - 配置 `zhupu_api_key`。
   - 在组合数据 `agent_matrix` 中配置至少一个智能体（填写正确 `app_id`）。
3. 使用 Postman / 接口调试工具测试后端新接口：
   - `GET /api/v1/ai/agents`：
     - 预期：返回组合数据中配置的智能体列表。
   - `POST /api/v1/ai/chat`：
     - Body：
       ```json
       {
         "agent_key": "组合数据中配置的key",
         "content": "测试问题",
         "conversation_id": ""
       }
       ```
     - 预期：
       - HTTP 200。
       - 返回 JSON 中存在 `conversation_id`。
       - `reply` 为一段合理中文文本。
4. 启动 uni-app 前端：
   - 打开智能体矩阵页：
     - 预期：列表从接口加载，展示配置的智能体。
   - 点击某智能体进入聊天页：
     - 发送一条消息：
       - 预期：智能体返回合理的回复文本。
       - 第二次发送时，继续使用同一个 `conversation_id`，智谱端应保留历史上下文。

### 6.2 基本异常场景验证

1. 智谱 API Key 未配置或配置错误：
   - 预期：`/ai/chat` 接口返回明确错误信息（可统一为业务错误码与提示）。
2. 智能体 `agent_key` 不存在或被禁用：
   - 预期：`/ai/chat` 返回「智能体不存在」或「智能体已下线」提示。
3. 智谱接口超时或网络异常：
   - 预期：
     - 后端记录日志。
     - 前端展示友好提示（如「网络异常，请稍后重试」）。

---

## 七、后续扩展建议（非当前必做）

在上述最小可行版本上线并稳定后，可按优先级规划以下扩展：

1. 智能体配置增强：
   - 在组合数据中增加：
     - `avatar`（头像图片）。
     - `welcome_message`（进入聊天页时的欢迎语）。
     - `max_tokens`、`temperature` 等参数（如果智谱应用支持外部覆盖）。
2. 文件上传与知识问答：
   - 基于文档的「3.2 文件上传」「3.3 文件解析状态」「3.6 invoke」等接口：
     - 后端增加文件上传转发接口。
     - 前端允许用户上传 PDF / Word 文件给智能体处理。
3. 流式输出（SSE）：
   - 将 `stream` 设置为 `true`，后端转发 SSE 流。
   - 前端通过 uni-app 的适配方式实现流式展示。
4. 会话管理：
   - 在本系统内维护会话表与消息表：
     - 支持「历史会话列表」「继续上次会话」「删除会话」等功能。

---

## 八、执行优先级建议

对于 AI 执行者，建议按以下优先级顺序实施：

1. 智谱侧应用创建与接口自测（确保可通）。
2. CRMEB 后台系统配置（`zhupu_api_key`）与组合数据 `agent_matrix` 创建及填充。
3. 后端服务层 `AgentChatServices` 与 API 控制器 `AgentController` 实现及本地接口自测。
4. uni-app 前端 `agents.vue`、`chat.vue` 改造与联调。
5. 基础异常场景处理与文档更新。

确保每一步通过接口或页面可见结果验证后，再进入下一步。
