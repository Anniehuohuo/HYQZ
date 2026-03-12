# 清言对话内容完整传输与前端渲染升级方案（v2）

本文基于《清言接口文档.md》与当前项目实现，给出结论、排查点与可落地的接口/渲染升级协议。

## 1. 结论：清言接口本身具备“完整传输”的能力

在《清言接口文档.md》中：

- 未发现对 `prompt` / `message.content` 的字段长度上限、截断规则或模板化限制说明。
- `POST /stream` 明确为 SSE 流式输出，且 Result 中说明 `message` 会“每次输出一个 message 对象，前一个结束后输出下一个”，适配长内容与多段输出。
- `conversation_id` 支持续聊；文档明确“对话轮数和上下文长度无限制（上下文过大会增加耗时）”。

因此，“小程序端回复短/固定/缺少多样性”更可能来自你们服务端与前端的解析与渲染策略，而非清言接口裁剪。

## 2. 当前实现中最可能导致内容“变短/变简陋”的点（需重点排查）

### 2.1 服务端对清言 `Content` 的解析丢失了非文本类型

清言 `message.content` 可能是：

- text/markdown（纯文本/Markdown）
- image（图片）
- code/execution_output（代码块与执行结果）
- tool_calls/browser_result/quote_result（工具调用与引用）
- rag_slices（知识库片段）

当前实现只提取 `type=text/markdown` 的 `text/content` 字段，其他类型会被忽略，导致：

- 原本在清言平台展示为“卡片/引用/代码块/图片/知识库片段”的内容，在小程序端直接缺失；
- 结构显著变短，观感“简陋且固定”。

### 2.2 前端渲染策略把“结构化内容”压扁成单段 Markdown/纯文本

如果服务端只下发一串文本，前端只能按 Markdown 渲染；但清言平台展示的“模块化区块/卡片/引用”并不等价于 Markdown 文本，导致样式与结构不一致。

### 2.3 流式拼接与降级路径可能影响完整性

当网络/流式异常时，服务端可能走 `/stream_sync` 降级；如果降级 prompt 或解析策略不同，会产生“结构更短”的现象。

## 3. 升级目标

- **完整性**：清言返回的所有 `Content` 类型都能传到前端（至少以“可渲染的区块形式”保留）。
- **一致性**：同一智能体在清言平台与小程序端展示结构尽量一致（差异主要来自平台自身额外 UI，而不是内容丢失）。
- **可扩展**：支持多级标题、富文本段落、列表、代码块、卡片引用、表情符号、颜色标记等。
- **性能**：首屏渲染 < 300ms（以“首屏 1~2 个区块 + 骨架/逐步加载”为策略，避免一次性巨量 rich-text 解析）。

## 4. 升级版响应协议（建议：Ai Chat Render v2）

### 4.1 非流式响应（HTTP JSON）

```json
{
  "status": 200,
  "msg": "ok",
  "data": {
    "session_id": 123,
    "conversation_id": "66cd9eea71105488bddd5d5f",
    "format": "blocks",
    "blocks": [
      { "type": "h2", "text": "三角模型分析" },
      { "type": "p", "inlines": [
        { "type": "text", "text": "假设赛道现状：" },
        { "type": "text", "text": "美妆赛道竞争激烈", "color": "#FF3B30", "bold": true }
      ]},
      { "type": "list", "ordered": false, "items": [
        { "inlines": [{ "type": "text", "text": "个人优势：具备专业美妆知识" }] },
        { "inlines": [{ "type": "text", "text": "甜蜜区：以个性化解决方案为核心" }] }
      ]},
      { "type": "code", "lang": "python", "text": "print('hello')" },
      { "type": "quote", "title": "注意", "blocks": [
        { "type": "p", "inlines": [{ "type": "text", "text": "内容由AI生成，仅供参考" }] }
      ]},
      { "type": "image", "urls": ["https://sfile.chatglm.cn/..."], "aspectRatio": 1.6 }
    ],
    "raw": {
      "provider": "qingyan",
      "provider_message": {}
    }
  }
}
```

### 4.2 流式响应（SSE / chunk）

以“区块增量”承载长内容，避免单次 payload 过大：

- `event: meta`：会话信息
- `event: block`：新增一个完整区块（推荐）
- `event: delta`：对最后一个 text/p 区块追加文本（可选，用于更丝滑的打字机效果）
- `event: done`：结束

示例：

```
event: meta
data: {"session_id":123,"conversation_id":"xxx","format":"blocks"}

event: block
data: {"type":"h2","text":"三角模型分析"}

event: block
data: {"type":"p","inlines":[{"type":"text","text":"假设赛道现状：..." }]}

event: done
data: {}
```

### 4.3 Block 类型定义（核心）

- `h1/h2/h3/h4`：标题（`text`）
- `p`：段落（`inlines[]`）
- `list`：列表（`ordered:boolean`，`items[]`，每项可为 `inlines[]` 或 `blocks[]`）
- `code`：代码块（`lang`，`text`）
- `quote`：卡片引用（`title`，`blocks[]`）
- `image`：图片（`urls[]`，`aspectRatio`）
- `divider`：分隔线
- `space`：空白（`size`）
- `badge`：徽标（`text`，`color`，`bgColor`）
- `table`：（可选）表格（`header[]`，`rows[][]`）

Inline 类型：

- `text`：纯文本（支持 `bold/italic/underline/strike/color/bgColor`）
- `emoji`：表情（`name` 或直接 `text`）
- `link`：链接（`text`，`url`）
- `tag`：标签（`text`，`color`，`bgColor`）

## 5. 清言 Content → blocks 的映射建议

基于文档中的 Content 类型：

- `type=text/markdown` → `p` 或 “Markdown to blocks” 解析（优先用 blocks，兜底用 markdown 渲染）
- `type=code` → `code`
- `type=execution_output` → `quote(title="运行结果") + p`
- `type=image` → `image`
- `type=browser_result/quote_result` → `quote(title="引用")`
- `type=tool_calls` → `quote(title="工具调用")`（可配置是否展示）
- `type=rag_slices` → `quote(title="知识库片段")` + list
- 未识别类型 → `quote(title="未识别内容")` + p(原始 JSON 字符串)

## 6. 小程序 WXML 模板（blocks 渲染）

> 说明：这是原生小程序 WXML/WXSS 版本。uni-app 可按同样结构在 Vue template 中实现。

### 6.1 WXML（render-blocks.wxml）

```xml
<view class="ai">
  <block wx:for="{{blocks}}" wx:key="index">
    <view wx:if="{{item.type === 'h1'}}" class="h1">{{item.text}}</view>
    <view wx:elif="{{item.type === 'h2'}}" class="h2">{{item.text}}</view>
    <view wx:elif="{{item.type === 'h3'}}" class="h3">{{item.text}}</view>

    <view wx:elif="{{item.type === 'p'}}" class="p">
      <block wx:for="{{item.inlines}}" wx:key="idx">
        <text
          wx:if="{{it.type === 'text'}}"
          class="t"
          style="
            {{it.color ? 'color:' + it.color + ';' : ''}}
            {{it.bgColor ? 'background:' + it.bgColor + ';' : ''}}
            {{it.bold ? 'font-weight:700;' : ''}}
            {{it.italic ? 'font-style:italic;' : ''}}
            {{it.underline ? 'text-decoration:underline;' : ''}}
            {{it.strike ? 'text-decoration:line-through;' : ''}}
          "
        >{{it.text}}</text>
        <text wx:elif="{{it.type === 'emoji'}}" class="emoji">{{it.text}}</text>
        <text
          wx:elif="{{it.type === 'link'}}"
          class="link"
          data-url="{{it.url}}"
          bindtap="onOpenLink"
        >{{it.text}}</text>
        <text wx:elif="{{it.type === 'tag'}}" class="tag" style="
          {{it.color ? 'color:' + it.color + ';' : ''}}
          {{it.bgColor ? 'background:' + it.bgColor + ';' : ''}}
        ">{{it.text}}</text>
      </block>
    </view>

    <view wx:elif="{{item.type === 'list'}}" class="list">
      <block wx:for="{{item.items}}" wx:key="i2">
        <view class="li">
          <view class="li-bullet">{{item.ordered ? (index2 + 1) + '.' : '•'}}</view>
          <view class="li-body">
            <view class="p">
              <block wx:for="{{item2.inlines}}" wx:key="i3">
                <text wx:if="{{it3.type === 'text'}}" class="t">{{it3.text}}</text>
              </block>
            </view>
          </view>
        </view>
      </block>
    </view>

    <view wx:elif="{{item.type === 'code'}}" class="code">
      <view class="code-hd">
        <text class="code-lang">{{item.lang || 'code'}}</text>
      </view>
      <scroll-view scroll-x class="code-bd">
        <text class="code-txt">{{item.text}}</text>
      </scroll-view>
    </view>

    <view wx:elif="{{item.type === 'quote'}}" class="quote">
      <view wx:if="{{item.title}}" class="quote-title">{{item.title}}</view>
      <view class="quote-body">
        <block wx:for="{{item.blocks}}" wx:key="qb">
          <view wx:if="{{itb.type === 'p'}}" class="p">
            <block wx:for="{{itb.inlines}}" wx:key="qbi">
              <text wx:if="{{qit.type === 'text'}}" class="t">{{qit.text}}</text>
            </block>
          </view>
        </block>
      </view>
    </view>

    <view wx:elif="{{item.type === 'image'}}" class="img-wrap">
      <image class="img" src="{{item.urls[0]}}" mode="widthFix" />
    </view>

    <view wx:elif="{{item.type === 'divider'}}" class="divider"></view>
    <view wx:elif="{{item.type === 'space'}}" style="height: {{item.size || 12}}px"></view>
  </block>
</view>
```

### 6.2 WXSS（render-blocks.wxss）

```css
.ai { color: #1f2329; font-size: 14px; line-height: 22px; }
.h1 { font-size: 22px; font-weight: 800; margin: 14px 0 10px; }
.h2 { font-size: 18px; font-weight: 800; margin: 14px 0 8px; }
.h3 { font-size: 16px; font-weight: 800; margin: 12px 0 6px; }
.p { margin: 6px 0; word-break: break-all; }
.emoji { margin: 0 2px; }
.link { color: #2f6feb; text-decoration: underline; }
.tag { padding: 1px 6px; border-radius: 10px; margin: 0 4px; font-size: 12px; background: rgba(47,111,235,0.10); }
.list { margin: 8px 0; }
.li { display: flex; margin: 4px 0; }
.li-bullet { width: 18px; flex: none; color: rgba(31,35,41,0.55); }
.li-body { flex: 1; }
.code { margin: 10px 0; border-radius: 10px; overflow: hidden; background: #0b1020; }
.code-hd { padding: 8px 10px; display: flex; justify-content: space-between; }
.code-lang { color: rgba(255,255,255,0.65); font-size: 12px; }
.code-bd { padding: 10px; }
.code-txt { color: #fff; font-family: Menlo, Consolas, monospace; font-size: 12px; line-height: 18px; white-space: pre; }
.quote { margin: 10px 0; padding: 10px 12px; border-radius: 10px; background: rgba(241,165,92,0.10); border: 1px solid rgba(241,165,92,0.18); }
.quote-title { font-weight: 700; margin-bottom: 6px; }
.divider { height: 1px; background: rgba(0,0,0,0.08); margin: 10px 0; }
.img-wrap { margin: 10px 0; border-radius: 10px; overflow: hidden; }
.img { width: 100%; }
```

## 7. 端到端测试用例（建议用 fixtures + 自动截图对比）

### 7.1 用例集合（JSON）

- `case_short_text`：短文本
- `case_long_text`：超长文本（> 12k 字符）
- `case_multi_paragraph`：多段落 + 多级标题 + 列表
- `case_rich_mix`：标题 + 富文本 + 代码块 + 引用卡片 + emoji + 颜色
- `case_empty`：空内容（返回 blocks=[] 或 raw 为空）
- `case_unknown_type`：未知块类型

每个用例都包含：
- `blocks`：渲染输入
- `expect`：期望（块数量、首块类型、是否包含某段关键文本）

### 7.2 验收与性能

- 完整性：长文本用例中，关键尾部字段必须能在前端可检索到（例如最后 200 字的签名段）。
- 样式还原：采用“截图像素差异阈值”评估（建议基于微信开发者工具 + 自动化截图）。
- 首屏 < 300ms：策略为“先渲染前 N 个 blocks + 分帧追加剩余 blocks（requestAnimationFrame/定时器）”。

## 8. 降级策略

- `format=blocks` 渲染失败 → 降级为 `raw_markdown`（若提供）或纯文本。
- 流式失败 → 降级 `/stream_sync`，并保留 `blocks` 结构（由服务端一次性生成）。
- 未识别 Content → 以 `quote` 展示原始 JSON，避免信息丢失。

## 9. 小程序端集成步骤与验收标准

### 9.1 集成步骤

1. 小程序请求参数增加 `format=blocks`（已在对话页默认开启）
2. SSE 流式中持续处理 `content`（打字机效果），当收到 `blocks` 字段后切换为 blocks 渲染（并分批追加渲染降低首屏开销）
3. 旧版本/降级路径继续使用 markdown 渲染，不影响存量

### 9.2 验收标准

- 完整性：清言返回的 `image/code/execution_output/rag_slices` 等类型在小程序端均可见（以 blocks 形式呈现）
- 还原度：模块结构（标题/分段/列表/引用卡片）与清言平台展示一致或更接近，主观评估 ≥ 95%
- 性能：首屏渲染不阻塞（先渲染前 24 个 blocks，其余分帧追加）
- 兼容：未下发 blocks 时仍按 markdown 正常展示

