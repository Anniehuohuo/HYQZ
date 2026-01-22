<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16" :body-style="{ padding: 0 }">
      <div class="padding-add">
        <el-form
          ref="formValidate"
          :model="formValidate"
          :label-width="labelWidth"
          :label-position="labelPosition"
          @submit.native.prevent
          inline
        >
          <el-form-item label="帖子ID：" label-for="post_id">
            <el-input v-model="formValidate.post_id" placeholder="请输入" class="form_content_width" clearable />
          </el-form-item>
          <el-form-item label="用户UID：" label-for="uid">
            <el-input v-model="formValidate.uid" placeholder="请输入" class="form_content_width" clearable />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" v-db-click @click="userSearchs">查询</el-button>
            <el-button v-db-click @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
    <el-card :bordered="false" shadow="never" class="ivu-mt">
      <el-table
        :data="tableList"
        v-loading="loading"
        highlight-current-row
        no-userFrom-text="暂无数据"
        no-filtered-userFrom-text="暂无筛选结果"
      >
        <el-table-column label="ID" width="80">
          <template slot-scope="scope">
            <span>{{ scope.row.id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="帖子ID" min-width="90">
          <template slot-scope="scope">
            <span>{{ scope.row.post_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户UID" min-width="90">
          <template slot-scope="scope">
            <span>{{ scope.row.uid }}</span>
          </template>
        </el-table-column>
        <el-table-column label="时间" min-width="140">
          <template slot-scope="scope">
            <span>{{ scope.row.add_time | formatDate }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" fixed="right" width="120">
          <template slot-scope="scope">
            <a v-db-click @click="del(scope.row, '删除点赞', scope.$index)">删除</a>
          </template>
        </el-table-column>
      </el-table>
      <div class="acea-row row-right page">
        <pagination
          v-if="total"
          :total="total"
          :page.sync="formValidate.page"
          :limit.sync="formValidate.limit"
          @pagination="getList"
        />
      </div>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { forumLikeListApi } from '@/api/forum';
import { formatDate } from '@/utils/validate';
export default {
  name: 'forum_like',
  data() {
    return {
      loading: false,
      formValidate: {
        page: 1,
        limit: 20,
        post_id: '',
        uid: '',
      },
      tableList: [],
      total: 0,
    };
  },
  computed: {
    ...mapState('media', ['isMobile']),
    labelWidth() {
      return this.isMobile ? undefined : '80px';
    },
    labelPosition() {
      return this.isMobile ? 'top' : 'right';
    },
  },
  filters: {
    formatDate(time) {
      if (time !== 0 && time !== undefined && time !== null) {
        let date = new Date(time * 1000);
        return formatDate(date, 'yyyy-MM-dd hh:mm');
      }
      return '';
    },
  },
  created() {
    this.getList();
  },
  methods: {
    getList() {
      this.loading = true;
      forumLikeListApi(this.formValidate)
        .then(async (res) => {
          let data = res.data;
          this.tableList = data.list;
          this.total = data.count;
          this.loading = false;
        })
        .catch((res) => {
          this.loading = false;
          this.$message.error(res.msg);
        });
    },
    del(row, title, num) {
      let delfromData = {
        title: title,
        num: num,
        url: `forum/like/${row.id}`,
        method: 'DELETE',
        ids: '',
      };
      this.$modalSure(delfromData)
        .then((res) => {
          this.$message.success(res.msg);
          this.getList();
        })
        .catch((res) => {
          this.$message.error(res.msg);
        });
    },
    userSearchs() {
      this.formValidate.page = 1;
      this.getList();
    },
    handleReset() {
      this.formValidate = {
        page: 1,
        limit: 20,
        post_id: '',
        uid: '',
      };
      this.getList();
    },
  },
};
</script>
