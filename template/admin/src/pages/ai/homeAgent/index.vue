<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16">
      <div slot="header" class="clearfix">
        <span>首页引流助手</span>
      </div>
      <el-form ref="form" :model="form" label-width="120px" @submit.native.prevent style="max-width: 980px">
        <el-form-item label="AI开关">
          <el-switch v-model="form.enabled" active-text="启用" inactive-text="停用"></el-switch>
        </el-form-item>
        <el-form-item label="服务地址">
          <el-input v-model="form.baseUrl" placeholder="https://open.bigmodel.cn/api/llm-application/open"></el-input>
        </el-form-item>
        <el-form-item label="App ID">
          <el-input v-model="form.appId" placeholder="例如：1791378613740900352"></el-input>
        </el-form-item>
        <el-form-item label="API Key">
          <el-input v-model="form.apiKey" type="password" show-password placeholder="例如：xxxx"></el-input>
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="例如：首页引流助手"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" active-text="启用" inactive-text="停用"></el-switch>
        </el-form-item>
        <el-form-item label="模型标识">
          <el-input v-model="form.model" placeholder="例如：glm-4 / 你选定的大模型名称"></el-input>
        </el-form-item>
        <el-form-item label="温度">
          <el-slider v-model="form.temperature" :min="0" :max="1" :step="0.1" show-input></el-slider>
        </el-form-item>
        <el-form-item label="系统规则">
          <el-input v-model="form.systemRules" type="textarea" :rows="5"></el-input>
        </el-form-item>
        <el-form-item label="人设与语气">
          <el-input v-model="form.persona" type="textarea" :rows="4"></el-input>
        </el-form-item>
        <el-form-item label="输出结构">
          <el-input v-model="form.outputFormat" type="textarea" :rows="5"></el-input>
        </el-form-item>
        <el-form-item label="引流策略">
          <el-input v-model="form.growthPolicy" type="textarea" :rows="4"></el-input>
        </el-form-item>
        <el-form-item label="降级话术">
          <el-input v-model="form.fallbackText" type="textarea" :rows="4"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="save">保存</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import { getHomeAgentConfigApi, saveHomeAgentConfigApi } from '@/api/ai';

export default {
  name: 'HomeAgent',
  data() {
    return {
      form: {
        enabled: false,
        baseUrl: 'https://open.bigmodel.cn/api/llm-application/open',
        appId: '',
        apiKey: '',
        name: '首页引流助手',
        status: true,
        model: '',
        temperature: 0.7,
        systemRules: '',
        persona: '',
        outputFormat: '',
        growthPolicy: '',
        fallbackText: '',
      },
    };
  },
  created() {
    this.getInfo();
  },
  methods: {
    getInfo() {
      getHomeAgentConfigApi()
        .then((res) => {
          const d = res.data || {};
          this.form.enabled = !!d.enabled;
          this.form.baseUrl = d.baseUrl || this.form.baseUrl;
          this.form.appId = d.appId || '';
          this.form.apiKey = d.apiKey || '';
          this.form.name = d.name || this.form.name;
          this.form.status = !!d.status;
          this.form.model = d.model || '';
          this.form.temperature = typeof d.temperature === 'number' ? d.temperature : this.form.temperature;
          this.form.systemRules = d.systemRules || '';
          this.form.persona = d.persona || '';
          this.form.outputFormat = d.outputFormat || '';
          this.form.growthPolicy = d.growthPolicy || '';
          this.form.fallbackText = d.fallbackText || '';
        })
        .catch(() => {});
    },
    save() {
      const payload = {
        enabled: this.form.enabled ? 1 : 0,
        baseUrl: this.form.baseUrl,
        appId: this.form.appId,
        apiKey: this.form.apiKey,
        name: this.form.name,
        status: this.form.status ? 1 : 0,
        model: this.form.model,
        temperature: this.form.temperature,
        systemRules: this.form.systemRules,
        persona: this.form.persona,
        outputFormat: this.form.outputFormat,
        growthPolicy: this.form.growthPolicy,
        fallbackText: this.form.fallbackText,
      };
      saveHomeAgentConfigApi(payload).then(() => {
        this.$message.success('保存成功');
      });
    },
  },
};
</script>
