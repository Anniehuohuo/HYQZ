# AI智能体矩阵（管理员配置 + 用户端读取）演示说明

## 技术栈
- 后端：ThinkPHP（CRMEb 项目结构）
- 管理端前端：Vue2 + Element UI（template/admin）
- 用户端：uni-app（template/uni-app）

## 1. 建表
在 MySQL 执行建表脚本：
- [ai-agent-matrix.sql](file:///d:/HYQZ3/docs/ai-agent-matrix.sql)

说明：本项目数据库默认表前缀为 `eb_`（见 `crmeb/config/database.php`），所以实际表名为 `eb_ai_categories / eb_ai_agents`。

## 2. 管理员后台配置（分类/智能体）
1. 登录后台管理端（现有登录流程不变）。
2. 进入菜单：智能体中心 → 智能体矩阵。
3. 在“分类”页签：
   - 新增分类：填写 cate_key / cate_name / sort / status
   - 编辑分类：修改上述字段
   - 删除分类：需确保该分类下没有任何智能体（后端会阻止删除）
4. 在“智能体”页签：
   - 新增智能体：填写 agent_name / description / category_id / bot_id / api_key / tags / sort / status
   - bot_id 在表中唯一（后端校验 + 数据库唯一索引）
   - api_key 仅供服务端调用智谱，不会下发到用户端矩阵接口

管理端页面代码（Vue2+Element）：
- [agentMatrix/index.vue](file:///d:/HYQZ3/template/admin/src/pages/ai/agentMatrix/index.vue)

管理端请求封装：
- [ai.js](file:///d:/HYQZ3/template/admin/src/api/ai.js)

## 3. 让用户端矩阵读取数据库配置
用户端“智能体矩阵”页面会调用接口 `/api/ai/agent_matrix`，返回启用的分类与启用的智能体（并附带 tags 数组与 abbr）。

用户端页面代码：
- [agents.vue](file:///d:/HYQZ3/template/uni-app/pages/ai/agents.vue)

用户端接口封装：
- [ai.js](file:///d:/HYQZ3/template/uni-app/api/ai.js)

## 4. 从矩阵智能体发起对话（连接智谱）
用户端点击某个智能体卡片进入对话页，会携带 `agentId=<数值ID>`。

后端 `/api/ai/chat` 会根据 agent_id 的规则选择配置：
- agent_id 为“数值ID”：从 ai_agents 读取 bot_id/api_key，并校验智能体与所属分类均为启用状态
- agent_id 非数字或为空：走原有“首页引流助手”默认配置（保持不冲突）

对话接口接入点：
- [AiController.php](file:///d:/HYQZ3/crmeb/app/api/controller/v1/ai/AiController.php)
- [AiChatServices.php](file:///d:/HYQZ3/crmeb/app/services/ai/AiChatServices.php)

## 5. curl 操作示例

### 5.1 管理端：获取分类列表
```
GET /adminapi/ai/agent_categories?page=1&limit=20
Authorization: Bearer <admin_token>
```

### 5.2 管理端：新增分类
```json
POST /adminapi/ai/agent_categories
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "cate_key": "comm",
  "cate_name": "亲子沟通",
  "sort": 100,
  "status": 1
}
```

### 5.3 管理端：新增智能体
```json
POST /adminapi/ai/agents
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "agent_name": "沟通教练",
  "avatar": "",
  "description": "把冲突变成合作：用结构化对话化解顶嘴、争执与冷战。",
  "category_id": 1,
  "bot_id": "1791378613740900352",
  "api_key": "你的智谱密钥",
  "tags": "共情,边界,修复",
  "sort": 100,
  "status": 1
}
```

### 5.4 用户端：拉取矩阵
```
GET /api/ai/agent_matrix
```

### 5.5 用户端：使用矩阵智能体对话
```json
POST /api/ai/chat
Content-Type: application/json

{
  "message": "孩子顶嘴很严重怎么办？",
  "conversation_id": "",
  "agent_id": "1"
}
```

## 6. 接口文档（OpenAPI）
- [ai-agent-matrix.openapi.yaml](file:///d:/HYQZ3/docs/ai-agent-matrix.openapi.yaml)
