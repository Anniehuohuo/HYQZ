<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16">
      <div slot="header" class="clearfix">
        <span>智能体矩阵</span>
      </div>
      <el-tabs v-model="activeTab">
        <el-tab-pane label="智能体" name="agents">
          <el-form inline class="ivu-mb-16">
            <el-form-item label="关键词">
              <el-input v-model="agentQuery.keyword" placeholder="名称/描述/标签/bot_id" clearable @keyup.enter.native="loadAgents" style="width: 240px" />
            </el-form-item>
            <el-form-item label="分类">
              <el-select v-model="agentQuery.category_id" clearable placeholder="全部" style="width: 200px">
                <el-option v-for="c in allCategories" :key="c.id" :label="c.cate_name" :value="c.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="agentQuery.status" clearable placeholder="全部" style="width: 120px">
                <el-option label="启用" :value="1" />
                <el-option label="停用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="loadAgents">查询</el-button>
              <el-button @click="resetAgentQuery">重置</el-button>
            </el-form-item>
            <el-form-item style="float: right">
              <el-button type="primary" @click="openAgentDialog()">新增智能体</el-button>
            </el-form-item>
          </el-form>

          <el-table :data="agentList" v-loading="agentLoading" class="mt14">
            <el-table-column label="ID" width="80" prop="id" />
            <el-table-column label="名称" min-width="160" prop="agent_name" />
            <el-table-column label="分类" min-width="120">
              <template slot-scope="scope">
                <span>{{ scope.row.cate_name || '-' }}</span>
              </template>
            </el-table-column>
          <el-table-column label="来源" width="110">
            <template slot-scope="scope">
              <span>{{ scope.row.provider === 'qingyan' ? '清言' : (scope.row.provider === 'managed' ? '中台托管' : (scope.row.provider === 'coze' ? '扣子' : '本地')) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="外部ID" min-width="200">
            <template slot-scope="scope">
              <span v-if="scope.row.provider === 'qingyan'">{{ scope.row.provider_assistant_id || '-' }}</span>
              <span v-else-if="scope.row.provider === 'managed'">托管模式</span>
              <span v-else>{{ scope.row.bot_id || '-' }}</span>
            </template>
          </el-table-column>
            <el-table-column label="解锁价格" width="110">
              <template slot-scope="scope">
                <span>{{ scope.row.unlock_price || 0 }}</span>
              </template>
            </el-table-column>
            <el-table-column label="赠送算力" width="110">
              <template slot-scope="scope">
                <span>{{ scope.row.gift_power || 0 }}</span>
              </template>
            </el-table-column>
            <el-table-column label="排序" width="90" prop="sort" />
            <el-table-column label="状态" width="120">
              <template slot-scope="scope">
                <el-switch
                  v-model="scope.row.status"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="启用"
                  inactive-text="停用"
                  @change="(val) => onAgentStatusChange(scope.row, val)"
                />
              </template>
            </el-table-column>
            <el-table-column label="更新时间" min-width="160" prop="updated_at" />
            <el-table-column fixed="right" label="操作" width="140">
              <template slot-scope="scope">
                <a @click="openAgentDialog(scope.row)">编辑</a>
                <el-divider direction="vertical"></el-divider>
                <a @click="deleteAgent(scope.row)">删除</a>
              </template>
            </el-table-column>
          </el-table>
          <div class="acea-row row-right page">
            <pagination v-if="agentTotal" :total="agentTotal" :page.sync="agentQuery.page" :limit.sync="agentQuery.limit" @pagination="loadAgents" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="分类" name="categories">
          <el-form inline class="ivu-mb-16">
            <el-form-item label="关键词">
              <el-input v-model="cateQuery.keyword" placeholder="cate_key/cate_name" clearable @keyup.enter.native="loadCategories" style="width: 240px" />
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="cateQuery.status" clearable placeholder="全部" style="width: 120px">
                <el-option label="启用" :value="1" />
                <el-option label="停用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="loadCategories">查询</el-button>
              <el-button @click="resetCateQuery">重置</el-button>
            </el-form-item>
            <el-form-item style="float: right">
              <el-button type="primary" @click="openCateDialog()">新增分类</el-button>
            </el-form-item>
          </el-form>

          <el-table :data="cateList" v-loading="cateLoading" class="mt14">
            <el-table-column label="ID" width="80" prop="id" />
            <el-table-column label="cate_key" min-width="160" prop="cate_key" />
            <el-table-column label="cate_name" min-width="160" prop="cate_name" />
            <el-table-column label="排序" width="90" prop="sort" />
            <el-table-column label="状态" width="120">
              <template slot-scope="scope">
                <el-switch
                  v-model="scope.row.status"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="启用"
                  inactive-text="停用"
                  @change="(val) => onCateStatusChange(scope.row, val)"
                />
              </template>
            </el-table-column>
            <el-table-column label="更新时间" min-width="160" prop="updated_at" />
            <el-table-column fixed="right" label="操作" width="140">
              <template slot-scope="scope">
                <a @click="openCateDialog(scope.row)">编辑</a>
                <el-divider direction="vertical"></el-divider>
                <a @click="deleteCategory(scope.row)">删除</a>
              </template>
            </el-table-column>
          </el-table>
          <div class="acea-row row-right page">
            <pagination v-if="cateTotal" :total="cateTotal" :page.sync="cateQuery.page" :limit.sync="cateQuery.limit" @pagination="loadCategories" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="算力设置" name="power">
          <el-card shadow="never" class="ivu-mb-16">
            <el-form label-width="140px" v-loading="powerLoading">
              <el-form-item label="启用算力">
                <el-switch v-model="powerForm.enabled" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
              </el-form-item>
              <el-form-item label="每次对话扣费">
                <el-input-number v-model="powerForm.cost_per_chat" :min="0" :max="999999" :step="1" :precision="0" />
              </el-form-item>
              <el-form-item label="每日免费次数">
                <el-input-number v-model="powerForm.free_daily_limit" :min="0" :max="999999" :step="1" :precision="0" />
              </el-form-item>
              <el-form-item label="充值标题">
                <el-input v-model="powerForm.recharge_title" maxlength="64" show-word-limit />
              </el-form-item>
              <el-form-item label="充值说明">
                <el-input v-model="powerForm.recharge_attention" type="textarea" :rows="4" maxlength="500" show-word-limit placeholder="一行一条说明" />
              </el-form-item>
            </el-form>

            <div style="display: flex; align-items: center; justify-content: space-between; margin: 10px 0">
              <div style="font-weight: 600">充值套餐</div>
              <el-button type="primary" size="small" @click="addPowerPackage">新增套餐</el-button>
            </div>
            <el-table :data="powerForm.packages" size="small" border>
              <el-table-column label="ID" width="90">
                <template slot-scope="scope">
                  <el-input-number v-model="scope.row.id" :min="1" :max="999999" :step="1" :precision="0" />
                </template>
              </el-table-column>
              <el-table-column label="金额(元)" min-width="140">
                <template slot-scope="scope">
                  <el-input v-model="scope.row.price" placeholder="例如 9.90" />
                </template>
              </el-table-column>
              <el-table-column label="算力" min-width="140">
                <template slot-scope="scope">
                  <el-input-number v-model="scope.row.power" :min="1" :max="99999999" :step="1" :precision="0" />
                </template>
              </el-table-column>
              <el-table-column label="操作" width="100">
                <template slot-scope="scope">
                  <el-button type="text" @click="removePowerPackage(scope.row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div style="margin-top: 14px">
              <el-button type="primary" :loading="powerSaving" @click="savePowerConfig">保存配置</el-button>
            </div>
          </el-card>
        </el-tab-pane>

        <el-tab-pane label="清言配置" name="qingyan">
          <el-card shadow="never" class="ivu-mb-16">
            <el-form label-width="140px" v-loading="qingyanLoading">
              <el-form-item label="api_key">
                <el-input v-model="qingyanForm.qingyan_api_key" maxlength="255" show-word-limit />
              </el-form-item>
              <el-form-item label="api_secret">
                <el-input v-model="qingyanForm.qingyan_api_secret" maxlength="255" show-word-limit type="password" show-password placeholder="留空则不修改" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="qingyanSaving" @click="saveQingyanConfig">保存</el-button>
              </el-form-item>
              <el-divider></el-divider>
              <el-form-item label="验证 assistant_id">
                <div style="display: flex; gap: 10px; align-items: center">
                  <el-input v-model="qingyanVerifyAssistantId" maxlength="128" show-word-limit placeholder="填写清言智能体ID" />
                  <el-button type="primary" :loading="qingyanVerifying" @click="verifyQingyanAssistant">验证连接</el-button>
                </div>
                <div v-if="qingyanVerifyResult" style="margin-top: 8px; color: rgba(0,0,0,0.6)">
                  {{ qingyanVerifyResult }}
                </div>
              </el-form-item>
            </el-form>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog :title="cateForm.id ? '编辑分类' : '新增分类'" :visible.sync="cateDialogVisible" width="520px">
      <el-form :model="cateForm" label-width="120px">
        <el-form-item label="cate_key">
          <el-input v-model="cateForm.cate_key" maxlength="32" show-word-limit />
        </el-form-item>
        <el-form-item label="cate_name">
          <el-input v-model="cateForm.cate_name" maxlength="64" show-word-limit />
        </el-form-item>
        <el-form-item label="sort">
          <el-input-number v-model="cateForm.sort" :min="0" :max="999999" :step="1" />
        </el-form-item>
        <el-form-item label="status">
          <el-switch v-model="cateForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="cateDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveCategory">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog :title="agentForm.id ? '编辑智能体' : '新增智能体'" :visible.sync="agentDialogVisible" width="720px">
      <el-form ref="agentFormRef" :model="agentForm" :rules="agentRules" label-width="120px">
        <el-form-item label="agent_name" prop="agent_name">
          <el-input v-model="agentForm.agent_name" maxlength="64" show-word-limit />
        </el-form-item>
        <el-form-item label="avatar" prop="avatar">
          <div style="display: flex; align-items: center; gap: 10px">
            <div v-if="agentForm.avatar" v-viewer style="width: 60px; height: 60px; flex: 0 0 60px">
              <img :src="agentForm.avatar" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px" />
            </div>
            <el-input v-model="agentForm.avatar" maxlength="255" show-word-limit placeholder="请选择/上传图片" />
            <el-button type="primary" @click="openAvatarPicker">选择图片</el-button>
          </div>
        </el-form-item>
        <el-form-item label="description" prop="description">
          <el-input v-model="agentForm.description" maxlength="255" show-word-limit />
        </el-form-item>
        <el-form-item label="system_prompt" prop="system_prompt">
          <el-input v-model="agentForm.system_prompt" type="textarea" :rows="5" maxlength="4000" show-word-limit placeholder="统一上下文模式下作为系统提示词；为空则使用description" />
        </el-form-item>
        <el-form-item v-if="agentForm.provider === 'local' || agentForm.provider === 'managed'" label="temperature" prop="temperature">
          <el-input-number v-model="agentForm.temperature" :min="0" :max="2" :step="0.1" :precision="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="开场白" prop="welcome">
          <el-input v-model="agentForm.welcome" type="textarea" :rows="3" maxlength="600" show-word-limit placeholder="进入对话页展示的开场白" />
        </el-form-item>
        <el-form-item label="引导问题" prop="suggestions">
          <el-input v-model="agentForm.suggestions" type="textarea" :rows="4" maxlength="1000" show-word-limit placeholder="一行一个问题，用于对话页快捷发问" />
        </el-form-item>
        <el-form-item label="category_id" prop="category_id">
          <el-select v-model="agentForm.category_id" placeholder="请选择分类" style="width: 100%">
            <el-option v-for="c in allCategories" :key="c.id" :label="c.cate_name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="来源" prop="provider">
          <el-select v-model="agentForm.provider" style="width: 100%">
            <el-option label="本地/智谱" value="local" />
            <el-option label="清言" value="qingyan" />
            <el-option label="中台托管" value="managed" />
            <el-option label="扣子" value="coze" />
          </el-select>
        </el-form-item>

        <el-form-item v-if="agentForm.provider === 'qingyan'" label="assistant_id" prop="provider_assistant_id">
          <div style="display: flex; align-items: center; gap: 10px">
            <el-input v-model="agentForm.provider_assistant_id" maxlength="128" show-word-limit placeholder="清言智能体ID" />
            <el-button type="primary" :loading="agentVerifyLoading" @click="verifyAgentInDialog">验证连接</el-button>
          </div>
        </el-form-item>
        
        <el-form-item v-if="agentForm.provider === 'qingyan'" label="对话模式" prop="context_mode">
          <el-select v-model="agentForm.context_mode" style="width: 100%">
            <el-option label="跟随清言平台" value="platform" />
            <el-option label="服务端统一上下文" value="unified" />
          </el-select>
        </el-form-item>

        <el-form-item v-if="agentForm.provider === 'managed'" label="托管模型" prop="managed_model">
          <el-input v-model="agentForm.managed_model" maxlength="64" show-word-limit placeholder="留空使用系统默认模型（建议 glm-4-flash）" />
        </el-form-item>
        <el-form-item v-if="agentForm.provider === 'managed'" label="知识库内容" prop="managed_knowledge">
          <el-input
            v-model="agentForm.managed_knowledge"
            type="textarea"
            :rows="6"
            maxlength="20000"
            show-word-limit
            placeholder="先用文本方式托管知识库，后续可升级为文件库"
          />
        </el-form-item>
        <el-form-item v-if="agentForm.provider === 'managed'" label="文档知识库">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px">
            <el-upload
              :action="kbUploadUrl"
              :headers="kbUploadHeaders"
              :data="kbUploadData"
              :show-file-list="false"
              :before-upload="beforeKbUpload"
              :on-success="onKbUploadSuccess"
              :on-error="onKbUploadError"
            >
              <el-button :loading="kbUploading" :disabled="!agentForm.id">上传文档并导入</el-button>
            </el-upload>
            <el-input v-model="kbAttachmentId" maxlength="20" placeholder="可选：已有附件时填写附件ID再导入" style="max-width: 280px" />
            <el-button type="primary" :loading="kbImporting" :disabled="!agentForm.id" @click="importKbDoc">按附件ID导入</el-button>
            <el-button :loading="kbLoading" :disabled="!agentForm.id" @click="loadAgentKbDocs(agentForm.id)">刷新</el-button>
          </div>
          <div style="color: rgba(0,0,0,0.45); margin-bottom: 8px">推荐直接使用“上传文档并导入”（支持txt/md/csv/json/log/doc/docx）；附件ID仅用于导入历史已上传文件。</div>
          <div v-if="!agentForm.id" style="color: rgba(0,0,0,0.45); margin-bottom: 8px">请先保存智能体，再导入文档知识库。</div>
          <el-table :data="kbDocs" size="small" border v-loading="kbLoading">
            <el-table-column label="ID" width="90" prop="id" />
            <el-table-column label="文档名" min-width="180" prop="title" />
            <el-table-column label="附件ID" width="110" prop="attachment_id" />
            <el-table-column label="切片数" width="100" prop="chunk_count" />
            <el-table-column label="字数" width="100" prop="content_len" />
            <el-table-column label="操作" width="100">
              <template slot-scope="scope">
                <el-button type="text" @click="deleteKbDoc(scope.row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>

        <el-form-item v-if="agentForm.provider === 'local' || agentForm.provider === 'coze'" label="bot_id" prop="bot_id">
          <el-input v-model="agentForm.bot_id" maxlength="128" show-word-limit :placeholder="agentForm.provider === 'coze' ? '扣子Bot ID' : '智谱智能体/应用ID'" />
        </el-form-item>
        <el-form-item v-if="agentForm.provider === 'local' || agentForm.provider === 'coze'" label="api_key" prop="api_key">
          <el-input v-model="agentForm.api_key" type="password" show-password maxlength="255" show-word-limit :placeholder="agentForm.provider === 'coze' ? '扣子 PAT Token，仅后台可见' : '仅后台可见'" />
        </el-form-item>
        <el-form-item label="tags" prop="tags">
          <el-input v-model="agentForm.tags" maxlength="255" show-word-limit placeholder="用逗号/空格分隔，例如：共情,边界,修复" />
        </el-form-item>
        <el-form-item label="解锁价格" prop="unlock_price">
          <el-input-number v-model="agentForm.unlock_price" :min="0.01" :max="999999" :step="0.01" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="赠送算力" prop="gift_power">
          <el-input-number v-model="agentForm.gift_power" :min="0" :max="999999" :step="1" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="sort" prop="sort">
          <el-input-number v-model="agentForm.sort" :min="0" :max="999999" :step="1" />
        </el-form-item>
        <el-form-item label="status" prop="status">
          <el-switch v-model="agentForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="agentDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveAgent">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog :visible.sync="avatarPickerVisible" width="950px" title="选择图片" :close-on-click-modal="false">
      <uploadPictures :isChoice="avatarPickerChoice" @getPic="onPickAvatar" v-if="avatarPickerVisible"></uploadPictures>
    </el-dialog>
  </div>
</template>

<script>
import {
  createAiAgentApi,
  createAiAgentCategoryApi,
  deleteAiAgentApi,
  deleteAiAgentCategoryApi,
  getAiAgentCategoriesApi,
  getAiAgentsApi,
  getQingyanConfigApi,
  getAiPowerConfigApi,
  setAiAgentCategoryStatusApi,
  setAiAgentStatusApi,
  saveQingyanConfigApi,
  saveAiPowerConfigApi,
  updateAiAgentApi,
  updateAiAgentCategoryApi,
  verifyQingyanAssistantApi,
  getAiAgentKbDocsApi,
  importAiAgentKbDocApi,
  deleteAiAgentKbDocApi,
} from '@/api/ai';
import uploadPictures from '@/components/uploadPictures';
import Setting from '@/setting';
import { getCookies } from '@/libs/util';


export default {
  name: 'AgentMatrix',
  components: { uploadPictures },
  data() {
    const validateBotId = (rule, value, callback) => {
      if (this.agentForm.provider !== 'local' && this.agentForm.provider !== 'coze') return callback();
      if (!value) return callback(new Error('请填写 bot_id'));
      return callback();
    };
    const validateApiKey = (rule, value, callback) => {
      if (this.agentForm.provider !== 'local' && this.agentForm.provider !== 'coze') return callback();
      if (!value) return callback(new Error('请填写 api_key'));
      return callback();
    };
    const validateManagedKnowledge = (rule, value, callback) => {
      if (this.agentForm.provider !== 'managed') return callback();
      const s = String(value || '').trim();
      const hasDocs = Array.isArray(this.kbDocs) && this.kbDocs.length > 0;
      if (!s && !hasDocs) return callback(new Error('请填写知识库内容或导入知识文档'));
      return callback();
    };
    const validateAssistantId = (rule, value, callback) => {
      if (this.agentForm.provider !== 'qingyan') return callback();
      if (!value) return callback(new Error('请填写 assistant_id'));
      return callback();
    };
    return {
      activeTab: 'agents',

      cateLoading: false,
      cateQuery: {
        page: 1,
        limit: 15,
        keyword: '',
        status: '',
      },
      cateList: [],
      cateTotal: 0,

      agentLoading: false,
      agentQuery: {
        page: 1,
        limit: 15,
        keyword: '',
        category_id: '',
        status: '',
      },
      agentList: [],
      agentTotal: 0,

      allCategories: [],

      cateDialogVisible: false,
      cateForm: {
        id: 0,
        cate_key: '',
        cate_name: '',
        sort: 0,
        status: 1,
      },

      agentDialogVisible: false,
      agentForm: {
        id: 0,
        agent_name: '',
        avatar: '',
        description: '',
        system_prompt: '',
        temperature: 0.7,
        welcome: '',
        suggestions: '',
        category_id: '',
        provider: 'local',
        context_mode: 'platform',
        provider_assistant_id: '',
        managed_model: '',
        managed_knowledge: '',
        bot_id: '',
        api_key: '',
        tags: '',
        unlock_price: 0.01,
        gift_power: 99,
        sort: 0,
        status: 1,
      },

      agentRules: {
        agent_name: [{ required: true, message: '请填写 agent_name', trigger: 'blur' }],
        description: [{ required: true, message: '请填写 description', trigger: 'blur' }],
        system_prompt: [{ max: 4000, message: 'system_prompt最多4000字符', trigger: 'blur' }],
        welcome: [{ max: 600, message: '开场白最多600字符', trigger: 'blur' }],
        suggestions: [{ max: 1000, message: '引导问题最多1000字符', trigger: 'blur' }],
        context_mode: [{ required: false, message: '请选择对话模式', trigger: 'change' }],
        category_id: [{ required: true, message: '请选择 category_id', trigger: 'change' }],
        provider: [{ required: true, message: '请选择来源', trigger: 'change' }],
        bot_id: [{ validator: validateBotId, trigger: 'blur' }],
        api_key: [{ validator: validateApiKey, trigger: 'blur' }],
        provider_assistant_id: [{ validator: validateAssistantId, trigger: 'blur' }],
        managed_knowledge: [{ validator: validateManagedKnowledge, trigger: 'blur' }],
        unlock_price: [{ required: true, message: '请填写解锁价格', trigger: 'change' }],
      },

      avatarPickerVisible: false,
      avatarPickerChoice: '单选',

      saving: false,
      agentVerifyLoading: false,

      powerLoading: false,
      powerSaving: false,
      powerForm: {
        enabled: 1,
        cost_per_chat: 1,
        free_daily_limit: 3,
        recharge_title: '算力充值',
        recharge_attention: '',
        packages: [
          { id: 1, price: '9.90', power: 30 },
          { id: 2, price: '19.90', power: 80 },
          { id: 3, price: '49.90', power: 240 },
        ],
      },

      qingyanLoading: false,
      qingyanSaving: false,
      qingyanForm: {
        qingyan_api_key: '',
        qingyan_api_secret: '',
        has_secret: 0,
      },
      qingyanVerifyAssistantId: '',
      qingyanVerifying: false,
      qingyanVerifyResult: '',
      kbLoading: false,
      kbImporting: false,
      kbUploading: false,
      kbDocs: [],
      kbAttachmentId: '',
      kbUploadUrl: `${Setting.apiBaseURL}/file/upload`,
      kbUploadHeaders: {
        'Authori-zation': `Bearer ${getCookies('token') || ''}`,
      },
      kbUploadData: {
        pid: 0,
      },
    };
  },
  created() {
    this.loadAllCategories();
    this.loadAgents();
    this.loadCategories();
    this.loadPowerConfig();
    this.loadQingyanConfig();
  },
  methods: {
    loadQingyanConfig() {
      this.qingyanLoading = true;
      getQingyanConfigApi()
        .then((res) => {
          const d = (res && res.data) || {};
          this.qingyanForm = {
            qingyan_api_key: d.qingyan_api_key || '',
            qingyan_api_secret: '',
            has_secret: Number(d.has_secret || 0),
          };
        })
        .finally(() => {
          this.qingyanLoading = false;
        });
    },
    saveQingyanConfig() {
      if (this.qingyanSaving) return;
      this.qingyanSaving = true;
      const payload = {
        qingyan_api_key: this.qingyanForm.qingyan_api_key || '',
        qingyan_api_secret: this.qingyanForm.qingyan_api_secret || '',
      };
      saveQingyanConfigApi(payload)
        .then((res) => {
          this.$message.success((res && res.msg) || '保存成功');
          this.loadQingyanConfig();
        })
        .finally(() => {
          this.qingyanSaving = false;
        });
    },
    verifyQingyanAssistant() {
      const id = (this.qingyanVerifyAssistantId || '').trim();
      if (!id) {
        this.$message.warning('请填写 assistant_id');
        return;
      }
      if (this.qingyanVerifying) return;
      this.qingyanVerifying = true;
      this.qingyanVerifyResult = '';
      verifyQingyanAssistantApi({ assistant_id: id })
        .then((res) => {
          const d = (res && res.data) || {};
          const cid = d.conversation_id || '';
          this.qingyanVerifyResult = cid ? `验证成功，conversation_id：${cid}` : '验证成功';
          this.$message.success('验证成功');
        })
        .catch((err) => {
          const msg = (err && err.msg) || '验证失败';
          this.qingyanVerifyResult = msg;
          this.$message.error(msg);
        })
        .finally(() => {
          this.qingyanVerifying = false;
        });
    },
    loadAllCategories() {
      getAiAgentCategoriesApi({ page: 1, limit: 1000 })
        .then((res) => {
          const d = (res && res.data) || {};
          this.allCategories = Array.isArray(d.list) ? d.list : [];
        })
        .catch(() => {});
    },
    resetCateQuery() {
      this.cateQuery = {
        page: 1,
        limit: this.cateQuery.limit,
        keyword: '',
        status: '',
      };
      this.loadCategories();
    },
    resetAgentQuery() {
      this.agentQuery = {
        page: 1,
        limit: this.agentQuery.limit,
        keyword: '',
        category_id: '',
        status: '',
      };
      this.loadAgents();
    },
    loadCategories() {
      this.cateLoading = true;
      getAiAgentCategoriesApi(this.cateQuery)
        .then((res) => {
          const d = (res && res.data) || {};
          this.cateList = Array.isArray(d.list) ? d.list : [];
          this.cateTotal = Number(d.count || 0);
        })
        .finally(() => {
          this.cateLoading = false;
        });
    },
    loadAgents() {
      this.agentLoading = true;
      getAiAgentsApi(this.agentQuery)
        .then((res) => {
          const d = (res && res.data) || {};
          this.agentList = Array.isArray(d.list) ? d.list : [];
          this.agentTotal = Number(d.count || 0);
        })
        .finally(() => {
          this.agentLoading = false;
        });
    },
    loadPowerConfig() {
      this.powerLoading = true;
      getAiPowerConfigApi()
        .then((res) => {
          const d = (res && res.data) || {};
          this.powerForm = {
            enabled: Number(d.enabled || 0) ? 1 : 0,
            cost_per_chat: Number(d.cost_per_chat || 0),
            free_daily_limit: Number(d.free_daily_limit || 0),
            recharge_title: d.recharge_title || '',
            recharge_attention: d.recharge_attention || '',
            packages: Array.isArray(d.packages) ? d.packages : [],
          };
          if (!this.powerForm.packages.length) {
            this.powerForm.packages = [
              { id: 1, price: '9.90', power: 30 },
              { id: 2, price: '19.90', power: 80 },
              { id: 3, price: '49.90', power: 240 },
            ];
          }
        })
        .finally(() => {
          this.powerLoading = false;
        });
    },
    addPowerPackage() {
      const ids = new Set((this.powerForm.packages || []).map((p) => Number(p && p.id)));
      let nextId = 1;
      while (ids.has(nextId)) nextId++;
      this.powerForm.packages.push({ id: nextId, price: '9.90', power: 30 });
    },
    removePowerPackage(row) {
      const id = Number(row && row.id);
      this.powerForm.packages = (this.powerForm.packages || []).filter((p) => Number(p && p.id) !== id);
    },
    validatePowerPackages() {
      const rows = Array.isArray(this.powerForm.packages) ? this.powerForm.packages : [];
      if (!rows.length) return '请至少配置一个充值套餐';
      const seen = new Set();
      for (const r of rows) {
        const id = Number(r && r.id);
        const price = Number(r && r.price);
        const power = Number(r && r.power);
        if (!id || id <= 0) return '套餐ID必须大于0';
        if (seen.has(id)) return `套餐ID重复：${id}`;
        seen.add(id);
        if (!price || price <= 0) return `套餐(${id})金额必须大于0`;
        if (!power || power <= 0) return `套餐(${id})算力必须大于0`;
      }
      return '';
    },
    savePowerConfig() {
      if (this.powerSaving) return;
      const err = this.validatePowerPackages();
      if (err) {
        this.$alert(err, '配置不正确', { type: 'warning' });
        return;
      }
      this.powerSaving = true;
      const payload = {
        enabled: this.powerForm.enabled ? 1 : 0,
        cost_per_chat: Number(this.powerForm.cost_per_chat || 0),
        free_daily_limit: Number(this.powerForm.free_daily_limit || 0),
        recharge_title: this.powerForm.recharge_title || '',
        recharge_attention: this.powerForm.recharge_attention || '',
        packages: (this.powerForm.packages || []).map((p) => ({
          id: Number(p.id),
          price: String(p.price),
          power: Number(p.power),
        })),
      };
      saveAiPowerConfigApi(payload)
        .then(() => {
          this.$alert('保存成功', '提示', { type: 'success' });
          this.loadPowerConfig();
        })
        .finally(() => {
          this.powerSaving = false;
        });
    },
    openCateDialog(row) {
      if (row) {
        this.cateForm = {
          id: row.id,
          cate_key: row.cate_key,
          cate_name: row.cate_name,
          sort: Number(row.sort || 0),
          status: Number(row.status || 0),
        };
      } else {
        this.cateForm = {
          id: 0,
          cate_key: '',
          cate_name: '',
          sort: 0,
          status: 1,
        };
      }
      this.cateDialogVisible = true;
    },
    saveCategory() {
      if (this.saving) return;
      this.saving = true;
      const payload = {
        cate_key: this.cateForm.cate_key,
        cate_name: this.cateForm.cate_name,
        sort: this.cateForm.sort,
        status: this.cateForm.status,
      };
      const req = this.cateForm.id
        ? updateAiAgentCategoryApi(this.cateForm.id, payload)
        : createAiAgentCategoryApi(payload);
      req
        .then(() => {
          this.$message.success('保存成功');
          this.cateDialogVisible = false;
          this.loadCategories();
          this.loadAllCategories();
        })
        .finally(() => {
          this.saving = false;
        });
    },
    deleteCategory(row) {
      this.$confirm('确认删除该分类？删除前需确保分类下无智能体。', '提示', { type: 'warning' })
        .then(() => deleteAiAgentCategoryApi(row.id))
        .then(() => {
          this.$message.success('删除成功');
          this.loadCategories();
          this.loadAllCategories();
        })
        .catch(() => {});
    },
    onCateStatusChange(row, val) {
      setAiAgentCategoryStatusApi(row.id, val ? 1 : 0).catch(() => {
        row.status = val ? 0 : 1;
      });
    },
    openAgentDialog(row) {
      if (row) {
        this.agentForm = {
          id: row.id,
          agent_name: row.agent_name,
          avatar: row.avatar,
          description: row.description,
          system_prompt: row.system_prompt || '',
          temperature: row.temperature === '' || row.temperature === null || row.temperature === undefined ? 0.7 : Number(row.temperature),
          welcome: row.welcome || '',
          suggestions: Array.isArray(row.suggestions) ? row.suggestions.join('\n') : (row.suggestions || ''),
          category_id: row.category_id,
          provider: row.provider || 'local',
          context_mode: row.context_mode || 'platform',
          provider_assistant_id: row.provider_assistant_id || '',
          managed_model: row.managed_model || '',
          managed_knowledge: row.managed_knowledge || '',
          bot_id: row.bot_id,
          api_key: row.api_key,
          tags: row.tags,
          unlock_price: Number(row.unlock_price || 0) || 0.01,
          gift_power: Number(row.gift_power || 0) || 99,
          sort: Number(row.sort || 0),
          status: Number(row.status || 0),
        };
        this.kbAttachmentId = '';
        this.loadAgentKbDocs(row.id);
      } else {
        this.agentForm = {
          id: 0,
          agent_name: '',
          avatar: '',
          description: '',
          system_prompt: '',
          temperature: 0.7,
          welcome: '',
          suggestions: '',
          category_id: '',
          provider: 'local',
          context_mode: 'platform',
          provider_assistant_id: '',
          managed_model: '',
          managed_knowledge: '',
          bot_id: '',
          api_key: '',
          tags: '',
          unlock_price: 0.01,
          gift_power: 99,
          sort: 0,
          status: 1,
        };
        this.kbDocs = [];
        this.kbAttachmentId = '';
      }
      this.agentDialogVisible = true;
    },
    loadAgentKbDocs(agentId) {
      const id = Number(agentId || 0);
      if (!id) {
        this.kbDocs = [];
        return;
      }
      if (this.agentForm.provider !== 'managed') {
        this.kbDocs = [];
        return;
      }
      this.kbLoading = true;
      getAiAgentKbDocsApi(id)
        .then((res) => {
          const d = (res && res.data) || {};
          this.kbDocs = Array.isArray(d.list) ? d.list : [];
        })
        .finally(() => {
          this.kbLoading = false;
        });
    },
    importKbDoc() {
      const id = Number(this.agentForm.id || 0);
      if (!id) {
        this.$message.warning('请先保存智能体');
        return;
      }
      const attachmentId = Number(this.kbAttachmentId || 0);
      if (!attachmentId) {
        this.$message.warning('请填写附件ID');
        return;
      }
      if (this.kbImporting) return;
      this.kbImporting = true;
      importAiAgentKbDocApi(id, { attachment_id: attachmentId })
        .then((res) => {
          const d = (res && res.data) || {};
          const msg = `导入成功，切片${Number(d.chunk_count || 0)}条`;
          this.$message.success(msg);
          this.kbAttachmentId = '';
          this.loadAgentKbDocs(id);
        })
        .catch((err) => {
          this.$message.error((err && err.msg) || '导入失败');
        })
        .finally(() => {
          this.kbImporting = false;
        });
    },
    beforeKbUpload(file) {
      const name = (file && file.name) || '';
      const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
      const allow = ['txt', 'md', 'csv', 'json', 'log', 'docx', 'doc'];
      if (!allow.includes(ext)) {
        this.$message.warning('仅支持 txt/md/csv/json/log/doc/docx');
        return false;
      }
      this.kbUploading = true;
      return true;
    },
    onKbUploadSuccess(response) {
      let res = response;
      if (typeof response === 'string') {
        try {
          res = JSON.parse(response);
        } catch (e) {
          res = {};
        }
      }
      const src = (res && res.data && res.data.src) || '';
      if (!src) {
        this.kbUploading = false;
        this.$message.error('上传成功但未返回文件路径');
        return;
      }
      const id = Number(this.agentForm.id || 0);
      importAiAgentKbDocApi(id, { att_dir: src })
        .then((ret) => {
          const d = (ret && ret.data) || {};
          const msg = `导入成功，切片${Number(d.chunk_count || 0)}条`;
          this.$message.success(msg);
          this.loadAgentKbDocs(id);
        })
        .catch((err) => {
          this.$message.error((err && err.msg) || '上传后导入失败');
        })
        .finally(() => {
          this.kbUploading = false;
        });
    },
    onKbUploadError(err) {
      this.kbUploading = false;
      this.$message.error((err && err.message) || '上传失败');
    },
    deleteKbDoc(row) {
      const id = Number(this.agentForm.id || 0);
      const docId = Number((row && row.id) || 0);
      if (!id || !docId) return;
      this.$confirm('确认删除该知识文档？', '提示', { type: 'warning' })
        .then(() => deleteAiAgentKbDocApi(id, docId))
        .then(() => {
          this.$message.success('删除成功');
          this.loadAgentKbDocs(id);
        })
        .catch(() => {});
    },
    verifyAgentInDialog() {
      const provider = this.agentForm.provider || 'local';
      if (provider !== 'qingyan') return;
      const id = (this.agentForm.provider_assistant_id || '').trim();
      if (!id) {
        this.$message.warning('请填写 assistant_id');
        return;
      }
      if (this.agentVerifyLoading) return;
      this.agentVerifyLoading = true;
      verifyQingyanAssistantApi({ assistant_id: id })
        .then(() => {
          this.$message.success('验证成功');
        })
        .catch((err) => {
          this.$message.error((err && err.msg) || '验证失败');
        })
        .finally(() => {
          this.agentVerifyLoading = false;
        });
    },
    openAvatarPicker() {
      this.avatarPickerVisible = true;
    },
    onPickAvatar(item) {
      if (!item) return;
      const url = item.att_dir || item.satt_dir || '';
      if (url) this.agentForm.avatar = url;
      this.avatarPickerVisible = false;
    },
    saveAgent() {
      if (this.saving) return;
      const formRef = this.$refs.agentFormRef;
      if (formRef && typeof formRef.validate === 'function') {
        formRef.validate((valid, fields) => {
          if (!valid) {
            const keys = fields ? Object.keys(fields) : [];
            const first = keys.length ? (fields[keys[0]] && fields[keys[0]][0] && fields[keys[0]][0].message) : '';
            this.$alert(first || '请检查必填项', '未填写完整', { type: 'warning' });
            return;
          }
          this.saving = true;
          const payload = {
            agent_name: this.agentForm.agent_name,
            avatar: this.agentForm.avatar,
            description: this.agentForm.description,
            system_prompt: this.agentForm.system_prompt,
            temperature: this.agentForm.temperature,
            welcome: this.agentForm.welcome,
            suggestions: this.agentForm.suggestions,
            category_id: this.agentForm.category_id,
            provider: this.agentForm.provider || 'local',
            context_mode: this.agentForm.context_mode,
            provider_assistant_id: this.agentForm.provider_assistant_id || '',
            managed_model: this.agentForm.managed_model || '',
            managed_knowledge: this.agentForm.managed_knowledge || '',
            bot_id: this.agentForm.bot_id,
            api_key: this.agentForm.api_key,
            tags: this.agentForm.tags,
            unlock_price: this.agentForm.unlock_price,
            gift_power: this.agentForm.gift_power,
            sort: this.agentForm.sort,
            status: this.agentForm.status,
          };
          const req = this.agentForm.id ? updateAiAgentApi(this.agentForm.id, payload) : createAiAgentApi(payload);
          req
            .then(() => {
              this.$message.success('保存成功');
              this.agentDialogVisible = false;
              this.loadAgents();
            })
            .catch((err) => {
              this.$message.error((err && err.msg) || '保存失败');
            })
            .finally(() => {
              this.saving = false;
            });
        });
      }
    },
    deleteAgent(row) {
      this.$confirm('确认删除该智能体？', '提示', { type: 'warning' })
        .then(() => deleteAiAgentApi(row.id))
        .then(() => {
          this.$message.success('删除成功');
          this.loadAgents();
        })
        .catch(() => {});
    },
    onAgentStatusChange(row, val) {
      setAiAgentStatusApi(row.id, val ? 1 : 0).catch(() => {
        row.status = val ? 0 : 1;
      });
    },
  },
};
</script>
