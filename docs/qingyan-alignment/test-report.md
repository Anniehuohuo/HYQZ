# 自动化测试报告（生成式评测）

## 测试目标
- 对同一问题在两种环境分别采集 30 组回答：
  - 环境A：直连清言 Assistant API（代表“清言平台侧能力输出”）
  - 环境B：本系统 `/ai/chat`（代表“当前调用环境输出”）
- 计算 BLEU 与 ROUGE-L 相似度，并满足：
  - 平均 BLEU ≥ 0.85
  - 平均 ROUGE-L ≥ 0.85
  - 信息完整度差异 < 5%

## 测试脚本
- 脚本：[qingyan_alignment_eval.js](file:///F:/项目/HYQZ/template/uni-app/scripts/qingyan_alignment_eval.js)
- npm 命令：`npm run test:qingyan:align`

## 运行方式
在 `template/uni-app` 目录设置环境变量后运行：

```bash
set QINGYAN_API_KEY=<your_key>
set QINGYAN_API_SECRET=<your_secret>
set QINGYAN_ASSISTANT_ID=<assistant_id>

set LOCAL_BASE_URL=<https://your-domain/api>
set LOCAL_AGENT_ID=<agent_id_in_this_system>
set LOCAL_AUTH_HEADER_NAME=Authori-zation
set LOCAL_AUTH_HEADER_VALUE=<token>

npm run test:qingyan:align -- --q "请用要点总结：孩子写作业拖拉怎么办？"
```

## 输出产物
脚本会生成一个目录并写入：
- `report.md`：本次评测汇总（PASS/FAIL + 平均分）
- `metrics.json`：逐条 BLEU/ROUGE-L/完整度差异
- `pairs.json`：30 组原始回答对（用于人工复核“信息缺失/格式差异”）

## 解释口径（避免误判）
- BLEU/ROUGE-L 对“同义改写”不够友好：即使内容等价，换词/换顺序也会显著拉低分数；因此要达到 0.85 通常需要“生成参数趋于确定性 + 输出结构固定”。
- 信息完整度差异使用“字母/数字/文字字符数”做近似衡量；当平台输出更长的解释或包含引用/代码块时，这个指标会提示“信息量差异”。

