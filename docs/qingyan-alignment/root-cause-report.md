# 清言平台 vs 当前调用环境：输出差异根因报告

## 结论摘要
- 当前系统对“清言智能体”的上游调用只传 `assistant_id + prompt (+conversation_id)`，不传 `temperature/max_tokens/top_p/system prompt` 等生成参数，也不传“工具/插件/知识库开关与版本信息”；因此实际输出强依赖清言平台侧该智能体的发布版本与默认参数，且无法在本系统内做精确复现与对齐。
- 当前链路存在两处“格式丰富度被削弱”的位置：
  - 上游内容抽取：仅抽取 `type=text/markdown`（此前仅 text）并忽略其他富类型（如 tool/image/code 等），会丢失平台侧多模态/结构化输出。
  - 小程序渲染：聊天页把 Markdown 做了强“降级”为段落/列表（此前还会剥离标题、代码等），导致同一内容在清言平台更“像平台”、在小程序更“像纯文本”。
- 还存在“接口版本/endpoint 不一致”的潜在根因：本系统使用 `assistant-api/v1` 的 `stream/stream_sync`，而清言平台 Web 端可能使用更高版本或不同 endpoint/渲染链路；只要版本/渲染不同，即使参数一致也会出现格式差异。

## 1) 两种场景的参数/上下文/知识库/插件差异诊断

### 1.1 当前调用环境（本系统）实际请求参数（可直接在代码确认）
- 清言上游请求体（固定字段）：
  - `assistant_id`
  - `prompt`
  - `conversation_id`（可选）
  - 证据：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L97-L120)、[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L165-L177)、[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L233-L255)
- 本系统不会向清言传递以下常见生成参数（因此无法“参数对齐”到平台）：
  - `temperature / top_p / max_tokens / presence_penalty / frequency_penalty / stop / seed`
  - `system prompt`（系统提示词）、`tools`（插件/工具）与知识库版本信息
  - 证据：上述调用处 payload 未包含这些字段
- 本系统上下文处理：
  - 本地会话会保存最近 10 条消息用于构建 `$messages`，但在 `provider=qingyan` 分支并未把 `$messages`发送给清言，只把“本轮用户输入”作为 `prompt` 发送。
  - 证据：[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L112-L132)（构建 history/messages）、[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L135-L205)（qingyan 实际调用只传 `$message`）

### 1.2 清言平台（Console/Web）侧需要对齐的“隐含配置项”（必须采集）
清言平台对同一个 `assistant_id` 的输出差异，最常见来自“智能体发布版本/草稿版本”和“调试开关”。以下项目需要从清言平台侧导出或人工记录：
- 生成参数：`temperature / top_p / max_tokens`（以及平台是否有 `seed/penalty/stop`）
- system prompt：系统人设、输出格式要求、引用格式（Markdown/HTML）等
- 上下文长度：最大上下文/历史轮数、是否启用长期记忆
- 知识库：绑定的知识库列表、版本号/更新时间、是否重新索引；检索参数（top_k、相似度阈值等）
- 插件/工具：是否开启联网、函数工具、文件/图片能力；以及工具返回是否会被模型复述/引用

### 1.3 结论：为什么“参数对齐”在当前实现下做不到
- 本系统调用清言接口的 payload 不包含上述生成参数与开关项，因此“本系统侧”无法通过代码把参数调到与平台一致；只能通过“清言平台侧的智能体配置”来对齐。

## 2) 接口版本、模型 endpoint、后处理与渲染导致的差异

### 2.1 endpoint/版本差异（高概率）
- 本系统使用：`assistant-api/v1` 的 `POST /stream` 与 `POST /stream_sync`
  - 证据：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L9-L12)、[docs/qingyan-agent-integration.md](file:///F:/项目/HYQZ/docs/qingyan-agent-integration.md#L24-L36)
- 清言平台 Web 端可能使用：
  - 不同版本（例如 v2/v3）或不同业务网关，导致默认参数、返回结构、富文本字段不同。
- 对齐建议：将清言 baseUrl 做成可配置并按“与平台一致”的 endpoint 切换（本次已增加 `qingyan_base_url` 配置项，见 [AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L2635-L2656) 与 [QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L9-L24)）。

### 2.2 后处理逻辑差异（富类型被丢弃）
- 当前只抽取 `type=text/markdown`，其他类型直接忽略，会导致平台侧“更丰富”的内容在本系统被降级成更短/更少结构。
  - 证据：[QingyanServices::extractText](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L13-L35)

### 2.3 前端富文本渲染差异（Markdown 被降级）
- 小程序聊天页使用 `rich-text` 渲染，但将 Markdown 转成了有限的 HTML 子集；平台侧若渲染完整 Markdown（标题、代码块、引用、表格等），则本系统会显著“看起来更朴素”。
  - 证据：[chat.vue](file:///F:/项目/HYQZ/template/uni-app/pages/ai/chat.vue#L8-L177)

## 3) 可量化对齐方案（参数同步/提示词重写/知识库刷新/回退）

### 3.1 参数同步（量化目标：降低随机性）
- 平台侧将该智能体的生成参数调整为“可复现”：
  - `temperature=0~0.2`，`top_p=1`（或与平台稳定配置一致），`max_tokens`固定
  - 关闭/固定随机种子（若平台支持 `seed` 则固定为同一值）
- 本系统侧对齐动作：
  - 统一通过同一 `assistant_id` 调用，不额外在 prompt 里注入“风格强约束”文本（除非平台本身有）
  - 通过 `qingyan_base_url` 指向与平台一致的 endpoint（若平台与 v1 不同）

### 3.2 提示词对齐（量化目标：格式一致）
- 让清言平台侧 system prompt 明确输出格式：
  - 指定 Markdown 结构（标题层级、列表、代码块、引用规则）
  - 指定“引用/来源”格式（若使用知识库）
- 本系统侧对齐动作：
  - 小程序端保留 Markdown 关键结构（标题/列表/代码块），避免把平台输出“剥成纯段落”
  - 本次已增强小程序的 Markdown→HTML 子集渲染（见 [chat.vue](file:///F:/项目/HYQZ/template/uni-app/pages/ai/chat.vue#L125-L177)）

### 3.3 知识库刷新（量化目标：信息一致）
- 平台侧：
  - 记录知识库版本与更新时间
  - 每次更新后触发重新索引并发布新版本
- 本系统侧：
  - 在评测期固定知识库版本（避免 30 次采样期间知识库变化导致差异）

### 3.4 回退策略（量化目标：失败可控）
- 触发条件（任一满足）：
  - 上游接口报错/超时/空回复
  - 评测不达标（BLEU/ROUGE-L < 0.85 或完整度差异≥5%）
- 回退动作：
  - 走本系统的“同步兜底”（stream_sync）并按固定 chunk 输出（已存在，见 [AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L235-L296)）
  - 或切换到平台一致的 endpoint（通过 `qingyan_base_url`）

## 4) 自动化评测（BLEU/ROUGE-L/完整度差异）
- 评测脚本位置：[qingyan_alignment_eval.js](file:///F:/项目/HYQZ/template/uni-app/scripts/qingyan_alignment_eval.js)
- 说明：
  - 对同一问题采集 N 组（默认 30）：
    - A：直连清言 `stream_sync`（assistant_id）
    - B：调用本系统 `POST /ai/chat`（agent_id 指向 provider=qingyan 的智能体，stream=0）
  - 计算：
    - BLEU（字符级 1~4gram + 平滑）
    - ROUGE-L（LCS F1）
    - 信息完整度差异：按“字母/数字/文字字符数”衡量，差异 < 5%

