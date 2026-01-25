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
            <el-table-column label="bot_id" min-width="160" prop="bot_id" />
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
      <el-form :model="agentForm" label-width="120px">
        <el-form-item label="agent_name">
          <el-input v-model="agentForm.agent_name" maxlength="64" show-word-limit />
        </el-form-item>
        <el-form-item label="avatar">
          <el-input v-model="agentForm.avatar" maxlength="255" show-word-limit placeholder="图标URL（可为空）" />
        </el-form-item>
        <el-form-item label="description">
          <el-input v-model="agentForm.description" maxlength="255" show-word-limit />
        </el-form-item>
        <el-form-item label="category_id">
          <el-select v-model="agentForm.category_id" placeholder="请选择分类" style="width: 100%">
            <el-option v-for="c in allCategories" :key="c.id" :label="c.cate_name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="bot_id">
          <el-input v-model="agentForm.bot_id" maxlength="128" show-word-limit placeholder="智谱智能体/应用ID" />
        </el-form-item>
        <el-form-item label="api_key">
          <el-input v-model="agentForm.api_key" type="password" show-password maxlength="255" show-word-limit placeholder="仅后台可见" />
        </el-form-item>
        <el-form-item label="tags">
          <el-input v-model="agentForm.tags" maxlength="255" show-word-limit placeholder="用逗号/空格分隔，例如：共情,边界,修复" />
        </el-form-item>
        <el-form-item label="sort">
          <el-input-number v-model="agentForm.sort" :min="0" :max="999999" :step="1" />
        </el-form-item>
        <el-form-item label="status">
          <el-switch v-model="agentForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="agentDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveAgent">保存</el-button>
      </span>
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
  setAiAgentCategoryStatusApi,
  setAiAgentStatusApi,
  updateAiAgentApi,
  updateAiAgentCategoryApi,
} from '@/api/ai';

export default {
  name: 'AgentMatrix',
  data() {
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
        category_id: '',
        bot_id: '',
        api_key: '',
        tags: '',
        sort: 0,
        status: 1,
      },

      saving: false,
    };
  },
  created() {
    this.loadAllCategories();
    this.loadAgents();
    this.loadCategories();
  },
  methods: {
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
          category_id: row.category_id,
          bot_id: row.bot_id,
          api_key: row.api_key,
          tags: row.tags,
          sort: Number(row.sort || 0),
          status: Number(row.status || 0),
        };
      } else {
        this.agentForm = {
          id: 0,
          agent_name: '',
          avatar: '',
          description: '',
          category_id: '',
          bot_id: '',
          api_key: '',
          tags: '',
          sort: 0,
          status: 1,
        };
      }
      this.agentDialogVisible = true;
    },
    saveAgent() {
      if (this.saving) return;
      this.saving = true;
      const payload = {
        agent_name: this.agentForm.agent_name,
        avatar: this.agentForm.avatar,
        description: this.agentForm.description,
        category_id: this.agentForm.category_id,
        bot_id: this.agentForm.bot_id,
        api_key: this.agentForm.api_key,
        tags: this.agentForm.tags,
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
        .finally(() => {
          this.saving = false;
        });
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
