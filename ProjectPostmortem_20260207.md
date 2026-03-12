# 项目问题复盘报告（HYQZ）

> 文件：ProjectPostmortem_20260207.md  
> 生成日期：2026-02-07  
> 范围：`crmeb/`（ThinkPHP6 后端）+ `template/admin`（管理端 Vue2）+ `template/uni-app`（小程序端 uni-app）  
> 说明：本报告基于代码静态审计 + 已发生问题链路（清言对话“模型未返回内容”）复盘整理。由于缺少可追溯的版本发布记录/变更历史/线上监控截图，本报告中“首次出现版本”如无法从仓库直接推断，统一标注为“未知（需补齐版本历史）”，并给出补齐方法。

## 目录

- [1. 版本历史](#1-版本历史)
- [2. 问题清单（按七维度分类）](#2-问题清单按七维度分类)
- [3. 根因分析（现象 → 直接原因 → 根本原因）](#3-根因分析现象--直接原因--根本原因)
- [4. 解决方案设计（多方案 + 推荐方案 + 改动点）](#4-解决方案设计多方案--推荐方案--改动点)
- [5. 验证结果（最小化可验证闭环）](#5-验证结果最小化可验证闭环)
- [6. 后续 Action 清单](#6-后续-action-清单)
- [7. 标签/追踪规范（ISSUE-XXX）](#7-标签追踪规范issue-xxx)

---

## 1. 版本历史

### 1.1 已知版本信息

- 管理端 `template/admin`：`package.json` 显示版本 `5.6.0`。[package.json](file:///F:/项目/HYQZ/template/admin/package.json#L1-L5)
- 后端 `crmeb`：存在 `.version` 文件与 `README.md`，但缺少可审计的发布标签与变更记录（无法从仓库静态推断每次上线包含哪些改动）。

### 1.2 长期问题：缺少可追溯版本/发布记录（ISSUE-010）

- 影响：无法回答“首次出现版本”、无法稳定回滚、问题复现难、跨人协作成本高。
- 推荐补齐：建立 `CHANGELOG` + Git tag（按版本）+ 每次上线记录（环境/配置变更）+ 必须关联 ISSUE 编号。

---

## 2. 问题清单（按七维度分类）

> 严重级别：P0（致命/安全/核心不可用）→ P3（低影响/体验/可延期）

### 2.1 技术缺陷

#### ISSUE-001：清言对话“模型未返回内容”（流式/同步解析不兼容导致丢内容）
- 首次出现版本：未知（需补齐版本历史）
- 影响范围：小程序端 `/api/ai/chat` 对话；清言 provider 智能体
- 严重级别：P0
- 复现路径：
  1) 后台创建 provider=qingyan 智能体并配置 `assistant_id`
  2) 小程序解锁后进入聊天页发起对话
  3) 返回 `data: {"error":"模型未返回内容"}`，界面显示“模型未返回内容”
- 已尝试但未成功的修复手段（历史）：仅依赖严格 SSE 行 `data: {...}` 与 `content={type:'text',text}` 单对象解析（对上游结构差异不兼容）
- 证据（可量化）：
  - 后端出现该文案的触发点：`AiChatServices::chatStream()` 清言分支“stream 空 → sync 空”兜底。[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L235-L295)
  - 小程序端把 `data.error` 原样展示： [chat.vue](file:///F:/项目/HYQZ/template/uni-app/pages/ai/chat.vue#L415-L456)
  - 需采集日志：`runtime/log/qingyan_debug.log` 中 `qingyan.chat.begin` / `qingyan.chat.stream_empty` / `qingyan.sync.empty_text`

#### ISSUE-002：异常被吞掉导致“看似成功、实则失败”（大量空 catch）
- 首次出现版本：未知
- 影响范围：全站（上传、AI、第三方调用、日志链路等）
- 严重级别：P1
- 复现路径：触发异常（网络断开/依赖异常/权限异常）时，流程继续走但缺少错误记录或返回值伪造
- 已尝试但未成功：在个别位置加日志，但整体没有统一策略
- 证据：
  - 上传逻辑吞异常并返回伪造 header 信息：[BaseUpload.php](file:///F:/项目/HYQZ/crmeb/crmeb/services/upload/BaseUpload.php#L274-L299)
  - 清言日志 channel 写入吞异常（导致关键链路日志可能缺失）：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L231-L310)

#### ISSUE-003：生产环境残留调试输出（var_dump/dump_sql/console.log）
- 首次出现版本：未知
- 影响范围：稳定性、日志污染、潜在信息泄露
- 严重级别：P1
- 复现路径：触发 Workerman/长连接/相关工具函数后，响应或日志出现调试打印
- 已尝试但未成功：仅靠人工检查，无法防止回归
- 证据：
  - Workerman 命令中多处 `var_dump`：[Workerman.php](file:///F:/项目/HYQZ/crmeb/crmeb/command/Workerman.php#L107-L131)
  - `dump_sql()` 会直接输出 SQL：[common.php](file:///F:/项目/HYQZ/crmeb/app/common.php#L1149-L1162)
  - 小程序页面存在 `console.log`（示例命中）：[index.vue](file:///F:/项目/HYQZ/template/uni-app/pages/ai/index.vue#L600-L695)

#### ISSUE-012：默认弱口令与不安全散列（md5('123456')）导致账户被撞库
- 首次出现版本：未知
- 影响范围：用户体系安全（注册/导入/第三方登录补全用户信息）
- 严重级别：P0
- 复现路径：
  1) 触发 `setUserInfo()` 创建用户（例如某些微信/手机号链路补齐用户信息）
  2) 若上游未传 `pwd`，系统会写入 `md5('123456')`
  3) 攻击者可通过常见弱口令/彩虹表撞库
- 已尝试但未成功：依赖“用户不会用密码登录”这一假设，缺少强制改密/随机密码/更安全哈希
- 证据：
  - 默认密码写入位置：[UserServices.php](file:///F:/项目/HYQZ/crmeb/app/services/user/UserServices.php#L126-L142)

#### ISSUE-013：全局禁用 HTTPS 证书校验（verify_peer=false）存在中间人攻击风险
- 首次出现版本：未知
- 影响范围：任何依赖 `get_headers()`/远程资源探测的逻辑（上传/远程图片等），以及后续可能扩展的远程调用
- 严重级别：P0
- 复现路径：
  1) 系统向 HTTPS 远程 URL 获取 headers
  2) 由于禁用证书校验，攻击者可劫持返回伪造 headers/content-type/length
  3) 造成内容欺骗、错误处理绕过或间接安全问题
- 已尝试但未成功：为“兼容证书问题/自签名证书”直接关闭校验，未做白名单与环境隔离
- 证据：
  - 明确关闭 SSL 校验：[BaseUpload.php](file:///F:/项目/HYQZ/crmeb/crmeb/services/upload/BaseUpload.php#L274-L299)

#### ISSUE-014：Workerman 长连接安全能力被硬编码禁用（wss_open=0 + verify_peer=false）
- 首次出现版本：未知
- 影响范围：长连接（admin/chat/channel）安全与可用性；未来扩展 WebSocket 依赖时风险更高
- 严重级别：P1（若线上依赖 wss 则为 P0）
- 复现路径：
  1) 配置层即使开启 wss，命令执行仍强制 `wss_open=0`
  2) 若未来改回开启，仍存在 `verify_peer=false` 不安全 TLS 配置
- 已尝试但未成功：临时绕过导致“配置与实际行为不一致”，排障困难
- 证据：
  - 强制禁用 wss：[Workerman.php](file:///F:/项目/HYQZ/crmeb/crmeb/command/Workerman.php#L82-L105)

#### ISSUE-015：第三方服务域名/端点配置分散且存在硬编码（环境切换与灰度风险）
- 首次出现版本：未知
- 影响范围：AI（智谱/清言）、上传等对外能力；预发/生产切换与灰度发布
- 严重级别：P2
- 复现路径：在不同环境需要切换 baseUrl 或代理时，必须改代码/难统一治理
- 已尝试但未成功：部分通过 `sys_config` 可配，但仍存在类内硬编码默认值
- 证据：
  - 清言 baseUrl 硬编码：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L9-L12)
  - 智谱应用 baseUrl 默认值硬编码：[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L349-L375)

#### ISSUE-016：日志分级/通道导致关键错误不可见，排障依赖“碰运气”
- 首次出现版本：未知
- 影响范围：线上问题定位效率（尤其 AI 对话/第三方接口）
- 严重级别：P1
- 复现路径：发生错误时，错误被写入 info/独立通道或被 apart_level 分流，运维只看 error.log 时看不到关键链路
- 已尝试但未成功：临时加日志，但缺少“统一 traceId + 关键事件必达”规范
- 证据：
  - 日志按级别分文件（apart_level）配置：[log.php](file:///F:/项目/HYQZ/crmeb/config/log.php#L16-L64)
  - 已新增清言专用通道 `qingyan_debug` 作为临时排障手段：[log.php](file:///F:/项目/HYQZ/crmeb/config/log.php#L47-L62)

#### ISSUE-017：权限/路由治理存在 TODO 与不一致点，存在误放行或误拦截风险
- 首次出现版本：未知
- 影响范围：后台权限与菜单可见性；越权风险/误操作风险
- 严重级别：P1
- 复现路径：路由权限策略变更或新增路由后，权限树与实际校验不一致，造成可见不可用/不可见可访问
- 已尝试但未成功：依赖人工同步路由与权限
- 证据：
  - 前端路由处存在 TODO（提示权限逻辑未完善）：[index.js](file:///F:/项目/HYQZ/template/admin/src/router/index.js#L116-L146)

### 2.2 性能瓶颈

#### ISSUE-004：流式 SSE 长连接缺少系统性超时/重试/背压策略
- 首次出现版本：未知
- 影响范围：AI 对话高并发时的吞吐、稳定性与成本
- 严重级别：P1
- 复现路径：并发聊天或网络抖动时，连接容易悬挂/超时/丢包，导致“无内容/卡住”
- 已尝试但未成功：仅通过设置 `timeout=60` 与 flush
- 证据：
  - Controller 层 SSE 输出依赖 PHP flush/关闭缓冲：[AiController.php](file:///F:/项目/HYQZ/crmeb/app/api/controller/v1/ai/AiController.php#L84-L135)
  - 上游流读取为逐行 `fgets`（容易遇到 chunk 边界不等于行边界）：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L203-L310)

### 2.3 依赖冲突

#### ISSUE-005：管理端依赖栈老旧且混用（Vue 2.5 + Element 2.15 + CLI3/5 混合）
- 首次出现版本：管理端 `5.6.0`（已知）
- 影响范围：构建稳定性、浏览器兼容、依赖升级困难
- 严重级别：P2
- 复现路径：升级任一依赖（例如 vue-router/element/webpack）易出现 peer 依赖冲突或构建失败
- 已尝试但未成功：局部升级（风险高、回归面大）
- 证据：
  - Vue 版本：`vue: ^2.5.10`；Element：`2.15.6`；CLI 版本混用：[package.json](file:///F:/项目/HYQZ/template/admin/package.json#L15-L110)

### 2.4 测试遗漏

#### ISSUE-006：缺少项目级单元测试/集成测试（只有依赖自带 tests）
- 首次出现版本：未知
- 影响范围：每次改动容易引入回归（AI/支付/上传/鉴权等）
- 严重级别：P1
- 复现路径：任意功能改动上线后出现“修了 A 坏了 B”，且无法快速定位
- 已尝试但未成功：缺少统一测试框架落地与 CI 约束
- 证据：
  - 管理端虽包含 jest/mocha 插件，但仓库中未发现自有测试目录（命中主要来自 `node_modules`）。

### 2.5 部署异常

#### ISSUE-007：安装脚本与测试脚本可能被误部署到生产
- 首次出现版本：未知
- 影响范围：安全、稳定性、信息探测
- 严重级别：P0
- 复现路径：
  - 访问 `public/install/` 或误上传测试脚本，可能暴露安装信息/行为异常
- 已尝试但未成功：依赖运维手工避免误部署
- 证据：
  - 安装脚本存在 `exit` 与默认凭据：[install/index.php](file:///F:/项目/HYQZ/crmeb/public/install/index.php#L20-L47)
  - 仓库根目录存在测试脚本 `test_zhipu_v2.php`（若被部署可被访问）

#### ISSUE-008：跨平台命令/进程探测逻辑不可靠（Linux-only exec/ps）
- 首次出现版本：未知
- 影响范围：Windows/容器环境、部分运维接口稳定性
- 严重级别：P2
- 复现路径：在非 Linux 环境调用相关接口时失败，或输出不可信
- 已尝试但未成功：未形成跨平台适配策略
- 证据：[PublicController.php](file:///F:/项目/HYQZ/crmeb/app/adminapi/controller/PublicController.php#L128-L169)

### 2.6 文档缺失

#### ISSUE-009：缺少“AI/SSE 接口契约与排障手册”
- 首次出现版本：未知
- 影响范围：排障效率、协作成本；同类问题反复发生
- 严重级别：P2
- 复现路径：出现“模型未返回内容/卡住”等问题时，无法快速按步骤定位是：解锁/算力/路由/上游/解析/前端
- 已尝试但未成功：临时在群里/口头传递排障经验
- 证据：现有 README 存在，但缺少面向“AI 对话链路”的统一 Runbook 与指标定义

#### ISSUE-010：缺少可追溯版本/发布记录（首次出现版本无法判定）
- 首次出现版本：未知
- 影响范围：所有模块（定位/回滚/复盘）
- 严重级别：P2
- 复现路径：任何线上问题无法回答“哪个版本引入/如何精准回滚/如何关联验证”
- 已尝试但未成功：依赖口头/临时文档记录
- 证据：仓库未包含规范化发布记录（Git tag/release notes/changelog），且当前工作区未包含 `.git` 元数据

#### ISSUE-018：缺少可量化监控/告警与性能基线（无法形成复盘闭环）
- 首次出现版本：未知
- 影响范围：性能、稳定性、成本控制（AI 对话/支付/上传等）
- 严重级别：P1
- 复现路径：线上“偶发”问题无法给出客观证据（错误率/延迟/空回复比例），只能靠主观描述
- 已尝试但未成功：临时加日志排查，缺少指标化与持续观测
- 证据：当前仅有日志分级配置，缺少标准化指标（例如 TTFB/空回复率/上游 HTTP code 分布）与告警策略

### 2.7 沟通协作

#### ISSUE-011：缺少统一 ISSUE 编号与发布关联机制（需求/代码/上线无法闭环）
- 首次出现版本：未知
- 影响范围：协作效率、复盘质量、责任边界
- 严重级别：P2
- 复现路径：出现线上问题后，不清楚“谁改了什么、为何改、怎么验证、如何回滚”
- 已尝试但未成功：依赖口头同步与临时记录
- 证据：仓库未见统一约束（如 PR 模板/issue 模板/变更记录规范）

---

## 3. 根因分析（现象 → 直接原因 → 根本原因）

> 方法：5 Whys（至少下沉到代码/配置/流程/人员）

### ISSUE-001 根因（清言“模型未返回内容”）
- 现象：小程序对话返回“模型未返回内容”
- 直接原因：后端从清言上游没有解析到任何文本增量（delta）且同步兜底也解析不到文本，最终返回 `error`
- 根本原因（5 Whys）：
  1) 为什么小程序没内容？因为后端 SSE 返回了 `{"error":...}` 而不是 `{"content":...}`
  2) 为什么后端返回 error？因为 `assistantContent` 为空
  3) 为什么 `assistantContent` 为空？因为上游返回结构/分片方式不符合“只认一种格式”的解析逻辑
  4) 为什么解析逻辑只认一种格式？因为缺少对清言 API 返回结构变体（content array / 非严格 data 行）的兼容设计与契约测试
  5) 为什么缺少契约测试？因为缺少系统性测试与排障手册（ISSUE-006/ISSUE-009/ISSUE-011），问题只能线上碰到再补丁式修复

### ISSUE-002 根因（吞异常）
- 现象：线上出现“功能异常但日志无关键信息/返回看似正常”
- 直接原因：catch 空块/忽略错误导致异常被隐藏
- 根本原因（5 Whys）：
  1) 为什么没有错误日志？因为异常被 catch 且不记录
  2) 为什么会写空 catch？为了“不中断流程/兼容不同环境/避免用户看到报错”
  3) 为什么需要靠吞异常兼容？因为缺少统一错误分级与上报策略（日志/告警/用户提示）
  4) 为什么没有统一策略？因为缺少工程规范与 review 约束
  5) 为什么没有 review 约束？缺少协作流程与发布治理（ISSUE-011）

### ISSUE-003 根因（调试残留）
- 现象：生产出现 var_dump/console.log/SQL 输出
- 直接原因：调试代码直接输出到 stdout 或写入控制台，未受环境开关约束
- 根本原因：
  - 代码层：缺少“统一 debug logger/禁止直接输出”的约束
  - 流程层：缺少 CI 静态扫描与 code review gate（ISSUE-006/ISSUE-011）

### ISSUE-004 根因（SSE 性能与稳定）
- 现象：长连接卡住/无内容/高并发时不稳定
- 直接原因：PHP 进程承担长连接流式输出，上游/下游缺少统一心跳、超时、重连、背压策略
- 根本原因：
  - 架构层：缺少专用流式网关/连接管理层
  - 流程层：缺少压测基线与指标（ISSUE-018）

### ISSUE-005 根因（依赖冲突）
- 现象：升级依赖容易冲突，构建不稳定
- 直接原因：核心框架版本老旧且混用，依赖锁定/升级策略不明确
- 根本原因：缺少依赖治理与版本演进路线（ISSUE-010/ISSUE-011）

### ISSUE-006 根因（测试遗漏）
- 现象：改动后回归频发，难以快速定位
- 直接原因：没有“必须通过”的项目级单测/集成测试/冒烟测试
- 根本原因：CI/CD 体系与质量门禁缺失（ISSUE-011），缺少可复用测试基线（ISSUE-018）

### ISSUE-007 根因（误部署）
- 现象：install/test 脚本可能进入生产
- 直接原因：发布物未做安全裁剪，Web 访问控制缺少 deny 兜底
- 根本原因：缺少标准化发布流程与产物清单（ISSUE-010/ISSUE-011）

### ISSUE-008 根因（跨平台不可靠）
- 现象：exec/ps 在 Windows/容器等环境不兼容
- 直接原因：实现依赖 Linux 工具链
- 根本原因：缺少平台适配层与环境矩阵测试（ISSUE-006）

### ISSUE-009 根因（文档缺失）
- 现象：排障依赖个人经验，问题反复发生
- 直接原因：缺少 Runbook/接口契约/指标定义
- 根本原因：知识沉淀机制与复盘闭环缺失（ISSUE-011/ISSUE-018）

### ISSUE-010 根因（版本历史缺失）
- 现象：无法回答“首次出现版本/如何精准回滚”
- 直接原因：缺少 changelog 与发布标签/发布说明
- 根本原因：发布治理缺失（ISSUE-011）

### ISSUE-011 根因（协作机制缺失）
- 现象：需求/代码/上线/验证无法闭环
- 直接原因：没有统一 ISSUE 编号制度与 PR/发布模板
- 根本原因：团队工程化流程未制度化（人员/流程层）

### ISSUE-012 根因（默认弱口令与 MD5）
- 现象：存在默认 `md5('123456')` 写入
- 直接原因：以弱口令 + 不安全散列作为默认值
- 根本原因：
  - 代码层：未使用 `password_hash` 等安全哈希
  - 流程层：缺少安全基线与安全 review（ISSUE-011）

### ISSUE-013 根因（禁用 SSL 校验）
- 现象：HTTPS 证书校验被全局关闭
- 直接原因：为“兼容证书问题”直接禁用校验
- 根本原因：缺少证书治理与环境区分（开发/预发/生产）策略（ISSUE-010/ISSUE-011）

### ISSUE-014 根因（Workerman 安全能力硬编码禁用）
- 现象：配置与实际行为不一致（wss_open 强制 0）
- 直接原因：上线时为方便/规避证书问题写死开关
- 根本原因：缺少证书自动化与配置一致性校验（ISSUE-006/ISSUE-018）

### ISSUE-015 根因（端点硬编码/配置分散）
- 现象：多环境切换困难
- 直接原因：默认值写在代码里，配置入口不统一
- 根本原因：缺少统一配置中心与环境变量规范（ISSUE-010）

### ISSUE-016 根因（日志不可观测）
- 现象：关键错误在运维常看的日志里找不到
- 直接原因：日志按级别分文件且缺少 traceId 贯穿，关键链路未定义“必达事件”
- 根本原因：可观测性规范缺失（ISSUE-018/ISSUE-011）

### ISSUE-017 根因（权限治理不一致）
- 现象：权限树与实际路由校验可能不一致
- 直接原因：缺少自动同步与强制校验，存在 TODO/人工流程
- 根本原因：权限/路由治理缺少工程化约束与测试覆盖（ISSUE-006/ISSUE-011）

### ISSUE-018 根因（缺少指标与告警）
- 现象：无法输出可量化证据（错误率/延迟/空回复率）
- 直接原因：只有日志，没有指标体系与告警
- 根本原因：缺少 SLO/SLI 定义与监控落地（流程/人员层）

---

## 4. 解决方案设计（多方案 + 推荐方案 + 改动点）

> 说明：每条至少两套方案；给出实施步骤、资源、工期、风险与回退。

### ISSUE-001：清言对话“模型未返回内容”

#### 方案 A（推荐）：增强解析兼容 + 兜底返回更可诊断错误
- 方案描述：兼容 `data:` 行与“纯 JSON 行”；兼容 `message.content` 为对象/数组/字符串；同步兜底强制 `Accept-Encoding: identity`，并把“无文本”识别为明确错误
- 实施步骤：
  1) 后端解析：抽象 `extractText()`，统一提取文本
  2) stream：允许 `data:` 或 `{...}` 两种行输入
  3) stream_sync：支持 content 数组合并
  4) 日志：通过 `qingyan_debug.log` 输出 begin/empty/ok
- 所需资源：后端 1 人；需要一套可访问清言的测试环境（含合法 key/secret/assistant_id）
- 预计工期：1–2 人日（不含灰度观察）
- 风险与回退：
  - 风险：兼容更多结构可能引入误解析（把非文本字段当文本）
  - 回退：保留旧解析分支作为开关（按配置/环境变量切换）
- 最终改动点：
  - [QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php)（新增 `extractText`、stream/sync 兼容）
  - [AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php)（sync 兜底持久化 conversation_id）

#### 方案 B：放弃上游流式，统一走 `stream_sync` 并在本系统伪流式输出
- 方案描述：不再 `fopen` 读取上游 SSE，统一 `HttpService::postRequest` 调用 `stream_sync` 得到完整文本，再按固定 chunk（如 20 字）yield 给小程序
- 实施步骤：改造 `chatStream` 清言分支为仅 sync；移除对 stream 的依赖
- 所需资源：后端 1 人；测试环境同上
- 预计工期：1 人日
- 风险与回退：
  - 风险：首字延迟变大（用户体验变差），大文本响应时间更长
  - 回退：保留“流式优先、sync 兜底”机制

---

### ISSUE-007：安装/测试脚本误部署风险（P0）

#### 方案 A（推荐）：部署层强制移除 + Web 层禁止访问
- 实施步骤：
  1) 发布包构建时排除 `public/install/` 与仓库根目录测试脚本
  2) Nginx/Apache 显式 deny：`location ^~ /install/ { return 404; }`
- 所需资源：运维 1 人 + 后端 0.5 人协作验证
- 预计工期：0.5–1 人日
- 风险与回退：若仍需安装流程，改为仅内网可访问或一次性 token
- 改动点：
  - Web Server 配置（非代码）
  - [install/index.php](file:///F:/项目/HYQZ/crmeb/public/install/index.php#L20-L47)（增加“已安装则禁止访问”校验，作为兜底）

#### 方案 B：保留 install，但增加强约束
- 实施步骤：增加安装锁文件、强制随机初始化密码、强制 HTTPS、限制来源 IP
- 所需资源：后端 1 人 + 运维 0.5 人
- 预计工期：1–3 人日
- 风险与回退：
  - 风险：策略复杂、仍可能被误配置暴露
  - 回退：若不再需要安装流程，直接下线 install 入口（回到方案 A）

---

### ISSUE-012：默认弱口令与不安全散列（P0）

#### 方案 A（推荐）：改用安全哈希 + 禁止默认弱口令 + 强制改密策略
- 方案描述：把密码存储改为 `password_hash()`（bcrypt/argon2），默认不再写弱口令；对“需要密码登录”的用户首次登录强制改密
- 实施步骤：
  1) 统一封装密码服务（hash/verify/rehash）
  2) `setUserInfo()` 不再默认写 `md5('123456')`，改为随机密码或空（取决于产品是否允许密码登录）
  3) 若已有历史 MD5 密码：采用“兼容验证 + 登录后迁移到安全哈希”的渐进方案
- 所需资源：后端 1 人 + DBA 0.5 人（数据迁移评估）
- 预计工期：2–4 人日（含兼容/回归）
- 风险与回退：
  - 风险：改动登录链路，可能影响历史用户登录
  - 回退：保留旧 MD5 校验分支，迁移开关可配置
- 最终改动点：
  - 默认弱口令写入位置：[UserServices.php](file:///F:/项目/HYQZ/crmeb/app/services/user/UserServices.php#L126-L142)
  - 需要联动：用户登录校验服务（建议在用户鉴权服务内统一落地）

#### 方案 B：维持现有密码字段，但改为随机初始化 + 仅短信/三方登录
- 方案描述：如果产品不提供“密码登录”，则不应存储可被撞库的默认密码；改为随机不可用密码并关闭密码登录入口
- 所需资源：后端 0.5–1 人
- 预计工期：1–2 人日
- 风险与回退：
  - 风险：后续若新增密码登录能力，需要再次梳理
  - 回退：保留密码字段但默认不写入，后续按方案 A 做兼容迁移

---

### ISSUE-013：禁用 SSL 校验（P0）

#### 方案 A（推荐）：恢复证书校验 + 失败可观测 + 白名单降级
- 方案描述：默认启用 `verify_peer/verify_peer_name`；对确需兼容的域名使用白名单与可配置降级（仅非生产或特定域名）
- 实施步骤：
  1) 移除全局 `stream_context_set_default` 禁用校验
  2) 若有自签名证书场景：通过配置注入 CA bundle 或限定域名白名单
  3) 对失败场景记录错误日志（含目标域名、错误原因，不记录敏感信息）
- 所需资源：后端 1 人 + 运维 0.5 人（证书/CA）
- 预计工期：1–2 人日
- 风险与回退：
  - 风险：历史“证书不正确”的远程地址会失败
  - 回退：提供配置开关仅对特定域名降级（严禁全局关闭）
- 最终改动点：
  - SSL 校验被关闭位置：[BaseUpload.php](file:///F:/项目/HYQZ/crmeb/crmeb/services/upload/BaseUpload.php#L274-L299)

#### 方案 B：替换实现为 cURL/Guzzle 并显式配置证书策略
- 方案描述：用 cURL/Guzzle 获取 headers，按环境配置 CA；错误输出更标准
- 所需资源：后端 1 人
- 预计工期：2–4 人日
- 风险与回退：
  - 风险：替换面更大，需回归测试
  - 回退：保留原实现为 fallback，仅对新链路启用

---

### ISSUE-014：Workerman 安全能力硬编码禁用（P1/P0）

#### 方案 A（推荐）：修复配置一致性（不再写死 wss_open）+ 安全 TLS 默认值
- 方案描述：恢复从配置读取 `wss_open`；TLS 默认启用 peer 校验；移除生产 var_dump
- 实施步骤：
  1) 去掉 `wss_open = 0` 写死，改回 `getSslFilePath()` 的结果
  2) `verify_peer` 按环境启用（生产必须 true）
  3) 删除/替换 `var_dump` 为日志
- 所需资源：后端 1 人 + 运维 0.5 人（证书/端口）
- 预计工期：1–2 人日
- 风险与回退：证书配置不完整会导致 wss 启动失败；回退到 ws 并保留告警
- 最终改动点：
  - wss_open 写死与 verify_peer=false：[Workerman.php](file:///F:/项目/HYQZ/crmeb/crmeb/command/Workerman.php#L82-L105)
  - var_dump 调试输出：[Workerman.php](file:///F:/项目/HYQZ/crmeb/crmeb/command/Workerman.php#L107-L131)

#### 方案 B：不自建长连接，改为网关/托管 WS（Nginx/云服务）+ 仅业务处理
- 所需资源：后端 1 人 + 运维 1 人
- 预计工期：5–10 人日
- 风险与回退：
  - 风险：改造成本高、需要新基础设施
  - 回退：仍可回到方案 A 的自建 Workerman（确保配置一致）

---

### ISSUE-003：生产调试残留（P1）

#### 方案 A（推荐）：构建/CI 阶段静态扫描拦截（禁止 var_dump/print_r/dump_sql/console.log）
- 实施步骤：
  1) 后端：在 CI 增加 grep 规则（或 PHPStan/自定义规则）拦截 `var_dump|print_r|dump_sql`
  2) 前端：生产构建开启 console 移除（babel 插件或 terser drop_console），并要求所有 log 走统一 logger
- 所需资源：后端 0.5 人 + 前端 0.5 人
- 预计工期：1–2 人日
- 风险与回退：误报会阻塞发布；回退为“仅生产分支强制”
- 改动点：
  - `dump_sql` 源：[common.php](file:///F:/项目/HYQZ/crmeb/app/common.php#L1149-L1162)
  - `var_dump` 源：[Workerman.php](file:///F:/项目/HYQZ/crmeb/crmeb/command/Workerman.php#L107-L131)

#### 方案 B：保留调试能力但必须显式开关（DEBUG=true）且记录到专用日志
- 所需资源：后端 0.5 人 + 前端 0.5 人
- 预计工期：1–2 人日
- 风险与回退：
  - 风险：容易被误开；需权限控制
  - 回退：完全移除调试输出（回到方案 A 的“禁止直接输出”）

---

### ISSUE-002：吞异常（P1）

#### 方案 A（推荐）：建立“错误分级 + 必达日志 + traceId”规范并逐步替换空 catch
- 实施步骤：
  1) 约定：禁止空 catch；必须 `Log::error` 并返回可追踪错误码
  2) 引入 traceId（例如 reqId）贯穿 controller → service → 上游请求
  3) 先改 AI/上传/支付等高风险链路
- 所需资源：后端 1–2 人
- 预计工期：3–7 人日（分阶段）
- 风险与回退：日志量上升；回退为仅对关键链路启用
- 改动点：
  - 上传吞异常：[BaseUpload.php](file:///F:/项目/HYQZ/crmeb/crmeb/services/upload/BaseUpload.php#L274-L299)
  - AI 清言链路吞异常（日志写入处）：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L203-L310)

#### 方案 B：接入统一异常上报（Sentry/自建）+ 保留必要用户提示
- 所需资源：后端 1 人 + 运维 1 人
- 预计工期：3–7 人日
- 风险与回退：
  - 风险：需要额外基础设施与隐私合规评估
  - 回退：仅做日志必达与 traceId（回到方案 A）

---

### ISSUE-004：SSE 性能与稳定（P1）

#### 方案 A（推荐）：SSE 输出协议标准化 + 心跳 + 上游/下游超时治理
- 实施步骤：
  1) 后端 SSE 固定输出格式：仅输出 `data: {content|error|session_id}` + `[DONE]`
  2) 加心跳：定期 `data: {"ping":1}` 或 `:keep-alive`
  3) 上游流读取增加“缓冲与半包处理”，并记录每次对话的首字延迟与空回复比例（对接 ISSUE-018）
- 所需资源：后端 1 人
- 预计工期：2–5 人日
- 风险与回退：心跳可能影响前端解析；回退为灰度开启
- 改动点：
  - SSE 输出入口：[AiController.php](file:///F:/项目/HYQZ/crmeb/app/api/controller/v1/ai/AiController.php#L84-L135)
  - 上游解析入口（清言）：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L203-L310)

#### 方案 B：AI 对话改为 WebSocket（Workerman）承载
- 所需资源：后端 1–2 人 + 运维 1 人 + 前端 0.5 人
- 预计工期：5–15 人日
- 风险与回退：
  - 风险：需先解决 ISSUE-014（wss/证书/安全），回归面大
  - 回退：保留 SSE 作为 fallback（灰度切换）

---

### ISSUE-005：依赖冲突（P2）

#### 方案 A（推荐）：依赖冻结 + 只修高危漏洞（最小变动策略）
- 实施步骤：输出依赖清单与锁定策略；只针对 CVE 级别升级并回归
- 资源：前端 1 人
- 预计工期：2–4 人日
- 风险与回退：锁死版本会延缓新功能；回退为“按模块逐步升级”
- 改动点：[package.json](file:///F:/项目/HYQZ/template/admin/package.json#L15-L110)

#### 方案 B：管理端升级路线（Vue2.6 LTS → Vue3）
- 所需资源：前端 1–2 人
- 预计工期：10–30 人日
- 风险与回退：
  - 风险：工期大、回归面广；需独立里程碑
  - 回退：先执行方案 A 的“冻结 + CVE 修复”

---

### ISSUE-006：测试遗漏（P1）

#### 方案 A（推荐）：先落地“冒烟/契约测试”覆盖核心链路（AI/登录/支付/上传）
- 实施步骤：
  1) 定义最小冒烟用例（10–20 条）
  2) AI：对 `/api/ai/chat` 与清言解析做契约测试（用录制响应或 mock）
  3) 接入 CI：每次合并必须通过
- 资源：后端 1 人 + 前端 0.5 人
- 预计工期：3–6 人日
- 风险与回退：CI 初期容易因不稳定测试阻塞；回退为先在 nightly 跑

#### 方案 B：分层单测覆盖（Service/DAO/Controller），逐步提高覆盖率
- 所需资源：后端 1 人（长期）
- 预计工期：持续投入（按迭代推进）
- 风险与回退：
  - 风险：需要长期投入与持续维护
  - 回退：优先保住冒烟/契约测试（方案 A）

---

### ISSUE-008：跨平台命令不可靠（P2）

#### 方案 A（推荐）：去除 exec 依赖，改为内部状态/健康检查接口
- 实施步骤：对 Workerman/进程状态改用 pid 文件/端口探活/内部注册表
- 资源：后端 1 人
- 预计工期：1–2 人日
- 风险与回退：探活策略不当可能误判；回退为保留旧实现但加平台判断
- 改动点：[PublicController.php](file:///F:/项目/HYQZ/crmeb/app/adminapi/controller/PublicController.php#L128-L169)

#### 方案 B：封装平台适配层（Windows/Linux）并严格 escapeshellarg
- 所需资源：后端 1 人
- 预计工期：2–4 人日
- 风险与回退：
  - 风险：仍有维护成本与安全面
  - 回退：禁用该接口或只在 Linux 环境启用（方案 A）

---

### ISSUE-009：AI/SSE 排障文档缺失（P2）

#### 方案 A（推荐）：输出 Runbook（按“数据流 + 日志关键词 + 判定树”）
- 实施步骤：固化“后台验证 → agent 配置 → 小程序请求 → 后端分发 → 上游响应 → SSE 输出”时序图与检查清单
- 资源：后端 0.5 人 + 产品/运维 0.5 人
- 预计工期：1–2 人日
- 风险与回退：文档过时；回退为每次改动必须同步更新

#### 方案 B：在管理端增加一键诊断页（探活/权限/算力/上游连通性）
- 所需资源：前端 1 人 + 后端 1 人
- 预计工期：5–10 人日
- 风险与回退：
  - 风险：开发量更大，但对运营更友好
  - 回退：仅提供文档/命令行排障（方案 A）

---

### ISSUE-010：版本/发布记录缺失（P2）

#### 方案 A（推荐）：建立版本 tag + Release Notes 模板 + 变更关联 ISSUE
- 实施步骤：定义语义化版本；每次上线打 tag；release notes 必须包含“验证/回退”
- 资源：负责人 0.5 人推动 + 全员执行
- 预计工期：1–2 人日（制度落地）+ 持续执行

#### 方案 B：CI 自动生成 changelog（基于 Conventional Commits）
- 所需资源：负责人 0.5 人推动 + 全员执行
- 预计工期：2–5 人日（CI/规范落地）
- 风险与回退：
  - 风险：需要统一提交规范
  - 回退：先使用人工 release notes 模板（方案 A）

---

### ISSUE-011：协作机制缺失（P2）

#### 方案 A（推荐）：ISSUE-XXX 制度 + PR 模板 + Code Review 必选项
- 实施步骤：每个改动必须绑定 ISSUE；PR 必写“复现/验证/回退”；上线必须留记录
- 资源：团队共识
- 预计工期：1–2 人日（制度落地）+ 持续执行

#### 方案 B：引入看板（迭代节奏/Owner/验收标准）并季度复盘
- 所需资源：团队共识 + 负责人推动
- 预计工期：1–3 人日（制度落地）+ 持续执行
- 风险与回退：
  - 风险：流程成本上升；需要简化模板
  - 回退：先试点一个模块（AI）再扩展

---

### ISSUE-015：端点硬编码/配置分散（P2）

#### 方案 A（推荐）：统一配置入口（env + sys_config）并禁止类内硬编码
- 实施步骤：建立 `ai.php`/`services.php` 配置集中管理；类内仅读取配置
- 资源：后端 1 人
- 预计工期：1–3 人日
- 风险与回退：配置迁移错误导致不可用；回退为保留旧默认值但仅开发环境启用
- 改动点：
  - 清言 baseUrl：[QingyanServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/QingyanServices.php#L9-L12)
  - 智谱 baseUrl 默认值：[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L349-L375)

#### 方案 B：引入配置中心/服务发现（更适合多环境多租户）
- 所需资源：后端 1 人 + 运维 1 人
- 预计工期：5–15 人日
- 风险与回退：
  - 风险：基础设施与运维成本增加
  - 回退：先做“配置集中化 + 禁止硬编码”（方案 A）

---

### ISSUE-016：日志不可观测（P1）

#### 方案 A（推荐）：统一 traceId + 关键事件必达 + 日志查询手册
- 实施步骤：
  1) controller 生成 reqId 并传入 service，所有关键日志带 reqId
  2) 约定关键事件：`api.ai.chat.request`、`ai.matrix_chat.request/ok/remote_error/empty_reply` 必须 `error` 或专用通道双写
  3) 给运维一个固定的“按 reqId 检索路径”
- 资源：后端 1 人
- 预计工期：2–4 人日
- 风险与回退：日志量增加；回退为只对 AI 链路启用
- 改动点：
  - 日志分流配置：[log.php](file:///F:/项目/HYQZ/crmeb/config/log.php#L16-L64)
  - AI chat 入口已有 `api.ai.chat.request`：[AiController.php](file:///F:/项目/HYQZ/crmeb/app/api/controller/v1/ai/AiController.php#L73-L82)

#### 方案 B：接入集中日志（ELK/Loki）并固化查询面板
- 所需资源：运维 1–2 人 + 后端 0.5 人
- 预计工期：5–15 人日
- 风险与回退：
  - 风险：需要额外基础设施
  - 回退：先用“专用通道 + traceId + 查询手册”（方案 A）

---

### ISSUE-017：权限治理不一致（P1）

#### 方案 A（推荐）：路由权限自动同步 + 前后端一致性校验
- 实施步骤：每次新增路由自动进入权限表；前端 meta.auth 与后端权限标识对齐；CI 检查未注册权限的路由
- 资源：后端 1 人 + 前端 0.5 人
- 预计工期：3–6 人日
- 风险与回退：历史路由需要补齐；回退为仅对新增路由强制
- 改动点：前端权限 TODO 位置：[index.js](file:///F:/项目/HYQZ/template/admin/src/router/index.js#L116-L150)

#### 方案 B：简化权限模型（角色-菜单为主），弱化路由级细粒度
- 所需资源：前端 1 人 + 后端 1 人
- 预计工期：5–12 人日
- 风险与回退：
  - 风险：安全边界可能变粗
  - 回退：先做自动同步与一致性校验（方案 A）

---

### ISSUE-018：缺少监控/告警与性能基线（P1）

#### 方案 A（推荐）：先为 AI 链路建立最小指标集（错误率/空回复率/延迟）并告警
- 实施步骤：
  1) 指标定义：TTFB、完整耗时、HTTP code 分布、空回复比例
  2) 数据采集：Nginx access log + `qingyan_debug.log` 统计（先日志统计，后指标化）
  3) 告警：空回复率/错误率阈值触发
- 资源：后端 0.5 人 + 运维 1 人
- 预计工期：2–5 人日
- 风险与回退：告警噪音；回退为仅工作时间告警或提高阈值

#### 方案 B：接入指标系统（Prometheus + Grafana / 云监控）并建立 SLO
- 所需资源：运维 1 人 + 后端 0.5–1 人
- 预计工期：5–15 人日
- 风险与回退：
  - 风险：需要持续运维与成本
  - 回退：先做日志统计与阈值告警（方案 A）

### ISSUE-002 / ISSUE-003 / ISSUE-004 / ISSUE-005 / ISSUE-006 / ISSUE-008 / ISSUE-009 / ISSUE-010 / ISSUE-011（汇总推荐方案）

> 为避免报告冗长，本次先给出“可落地、可复用”的推荐方案骨架与关键改动点；详细到每条的逐行改造建议建议按 Action 清单推进时逐条补齐。

- ISSUE-002（吞异常）：
  - 方案 A（推荐）：建立统一 `try/catch + Log + error code` 规范；禁止空 catch（CI 扫描拦截）
  - 方案 B：全局异常上报（Sentry/自建）+ 关键链路必须带 traceId
  - 改动点：优先从上传/AI/第三方调用链路开始（见 BaseUpload/QingyanServices）
- ISSUE-003（调试残留）：
  - 方案 A（推荐）：增加“生产环境禁止 var_dump/dump_sql/console.log”的静态扫描与构建失败
  - 方案 B：封装 debug logger（按配置开关）替代直接输出
- ISSUE-004（SSE 性能/稳定）：
  - 方案 A（推荐）：SSE 输出统一中间层（心跳、超时、断线处理、backpressure）
  - 方案 B：改 WebSocket（Workerman）承载对话流（需先解决 wss/证书/权限）
- ISSUE-005（依赖冲突）：
  - 方案 A（推荐）：冻结依赖 + 修复高危漏洞（最小变动）
  - 方案 B：管理端升级至 Vue2.6/LTS 或迁移 Vue3（大工程）
- ISSUE-006（测试遗漏）：
  - 方案 A（推荐）：先补“契约测试/冒烟测试”（AI chat、登录、支付回调、上传）
  - 方案 B：全量单测覆盖（长期）
- ISSUE-008（Linux-only exec）：
  - 方案 A（推荐）：移除 exec 依赖，用 Workerman 自身状态/守护进程管理
  - 方案 B：封装平台适配层（Windows/Linux）
- ISSUE-009（文档缺失）：
  - 方案 A（推荐）：新增“AI 排障 Runbook + SSE 协议说明 + 日志关键词表”
  - 方案 B：把关键链路做成可视化时序图（PlantUML/Mermaid）
- ISSUE-010（缺少版本历史）：
  - 方案 A（推荐）：引入 Git tag + changelog + release notes 模板
  - 方案 B：引入语义化版本与自动生成发布说明（CI）
- ISSUE-011（协作机制缺失）：
  - 方案 A（推荐）：所有改动必须关联 ISSUE-XXX；PR 模板强制“验证步骤/回退策略”
  - 方案 B：引入看板与季度复盘机制（流程治理）

---

## 5. 验证结果（最小化可验证闭环）

### 5.1 已完成的最小验证（本地静态）
- PHP 语法级诊断：IDE 诊断通过（未发现语法错误）。
- 路由/参数一致性核对：
  - 小程序聊天请求参数：`agent_id/session_id/message/stream` 与后端 `AiController::chat` 一致。[chat.vue](file:///F:/项目/HYQZ/template/uni-app/pages/ai/chat.vue#L506-L595) / [AiController.php](file:///F:/项目/HYQZ/crmeb/app/api/controller/v1/ai/AiController.php#L32-L38)
  - “模型未返回内容”仅可能由后端返回 `error` 触发，小程序只是展示。[AiChatServices.php](file:///F:/项目/HYQZ/crmeb/app/services/ai/AiChatServices.php#L235-L295)

### 5.2 待完成的验证（需要在可运行环境执行）
- 单元测试：
  - 目标：为 `QingyanServices::extractText/stream/syncChat` 增加输入样例测试（覆盖 content 对象/数组/字符串、data 行/JSON 行）
- 集成测试：
  - 目标：`POST /api/ai/chat`（清言智能体）在真实 key/secret 下能稳定返回 SSE content
- 灰度回归：
  - 目标：小流量用户开启清言智能体；观察 `qingyan_debug.log` 的 empty 比例与平均首字延迟
- 性能对比数据：
  - 指标：首字延迟（TTFB）、完整回复耗时、空回复比例、token 获取失败率
  - 数据源：Nginx access log + `qingyan_debug.log` 统计

---

## 6. 后续 Action 清单

> 目标：每条 ISSUE 在进入开发前必须补齐“复现用例 + 验证标准 + 回退策略”。

1) ISSUE-001：在生产/预发复现一次并采集 `qingyan_debug.log`（begin/empty/ok），确认空回复比例是否下降  
2) ISSUE-007：上线包剔除 install/test 文件，并在 Nginx 层 deny `/install/`  
3) ISSUE-002：制定并落地“禁止空 catch”的扫描规则（CI 或 pre-commit）  
4) ISSUE-006：先落地 AI chat 的契约测试（最小覆盖）  
5) ISSUE-012：完成密码策略改造方案选型（A/B）并输出迁移与回退步骤  
6) ISSUE-013：恢复 SSL 校验并对异常域名走白名单降级（禁止全局关闭）  
7) ISSUE-014：Workerman 配置一致性修复（不再写死 wss_open）并清理 var_dump  
8) ISSUE-016：为 AI 链路引入 reqId 查询路径与“关键事件必达”日志规范  
9) ISSUE-018：建立 AI 链路最小指标集与告警阈值（空回复率/错误率/延迟）  
10) ISSUE-010/ISSUE-011：建立发布规范与 ISSUE 编号制度（从 AI 模块开始试点）

---

## 7. 标签/追踪规范（ISSUE-XXX）

### 7.1 ISSUE 编号与标签建议

- ISSUE-001：`ai`、`qingyan`、`sse`、`p0`
- ISSUE-002：`observability`、`error-handling`、`p1`
- ISSUE-003：`security`、`debug`、`p1`
- ISSUE-004：`performance`、`sse`、`p1`
- ISSUE-005：`deps`、`frontend-admin`、`p2`
- ISSUE-006：`testing`、`ci`、`p1`
- ISSUE-007：`deploy`、`security`、`p0`
- ISSUE-008：`deploy`、`compat`、`p2`
- ISSUE-009：`docs`、`runbook`、`p2`
- ISSUE-010：`release`、`process`、`p2`
- ISSUE-011：`collaboration`、`process`、`p2`
- ISSUE-012：`security`、`auth`、`p0`
- ISSUE-013：`security`、`tls`、`p0`
- ISSUE-014：`security`、`workerman`、`p1`
- ISSUE-015：`config`、`env`、`p2`
- ISSUE-016：`observability`、`logging`、`p1`
- ISSUE-017：`security`、`rbac`、`p1`
- ISSUE-018：`observability`、`metrics`、`p1`

### 7.2 “在代码仓库创建对应标签”的落地方式（两种可选）

#### 方式 A（推荐）：在 Git 平台创建 Issue Labels（GitHub/Gitee）
- 优点：标签语义清晰、可筛选、可统计；不会污染 Git tag（Git tag 更适合版本）
- 操作：在平台后台创建 labels；每条问题建 Issue 并打标签（ISSUE-XXX + 分类标签）

#### 方式 B：本地 Git tag（不推荐用于问题追踪，只作为兼容手段）
- 可建轻量 tag：`issue/ISSUE-001` 指向“引入修复的提交”
- 风险：tag 数量膨胀、与版本 tag 混淆
