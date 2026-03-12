# AI 对话稳定性：验证标准与回归用例

## 目标
- 用户每次请求获得：准确、完整、结构一致、可渲染。
- 系统异常时：不扣费/不浪费算力（至少不触发本系统 commit 扣费），并给出可理解的失败提示。
- 输出不包含：工具说明、系统提示词、内部调试指令等越权内容。

## 验收标准（必须同时满足）
### A. 正确性与完整度
- 对同一问题，清言平台与小程序端输出在结构化模块上保持一致：
  - 必须包含结尾“场景练习/下一个场景”模块（若平台侧包含）。
- 不允许出现“半段截断/只输出标题不输出内容”等明显不完整。

### B. 安全与越权输出防护
- 不允许出现以下关键特征（任一即 FAIL）：
  - “可用工具”“simple_browser”“msearch(…)”“mclick(…)”“open_url(…)”
  - 函数/工具列表、调试提示、系统规则全文回显

### C. 会话一致性
- session_id 必须绑定 agent_id，禁止跨智能体复用上下文。
- 若前端携带了不匹配的 session_id，服务端必须自动新建会话并继续对话。

### D. 富文本渲染一致性（小程序端）
- 支持 Markdown→HTML 子集：
  - h1~h6、ol/ul、inline code、fenced code、blockquote、hr
- 渲染内容需经过转义，禁止 XSS。

## 自动化测试用例（可复用）
### 1) Markdown 渲染单测
- 命令：`npm run test:markdown:render`
- 产物：`template/uni-app/scripts_out/**/markdown_render_diff_report.md`

### 2) Markdown 渲染性能基准
- 命令：`npm run bench:markdown:render`
- 产物：`template/uni-app/scripts_out/**/markdown_render_benchmark.md`

### 3) 清言 vs 本系统对齐评测（30 组）
- 命令：`npm run test:qingyan:align -- --q "<问题>"`
- 产物：`pairs.json / metrics.json / report.md`
- 通过条件：平均 BLEU ≥ 0.85，平均 ROUGE-L ≥ 0.85，完整度差异 < 5%

### 4) “场景练习模块”差异专项报告
- 命令：`npm run diff:qingyan:scene -- --q "<问题>"`
- 产物：`scene_practice_diff_report.md`（含关键词命中与首个差异片段）

### 5) AI Chat 契约测试（防越权输出）
- 命令：`npm run test:chat:contract -- --q "<问题>"`
- 通过条件：
  - stream 与非 stream 均不命中“工具/系统提示词”泄露特征
  - SSE 至少拿到 1 个 content chunk

