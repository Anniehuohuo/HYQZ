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
        <el-form-item label="接口地址">
          <el-input v-model="form.chatUrl" placeholder="例如：https://open.bigmodel.cn/api/paas/v4/chat/completions"></el-input>
        </el-form-item>
        <el-form-item label="API Key">
          <el-input
            v-model="form.apiKey"
            type="password"
            show-password
            :placeholder="form.hasApiKey ? '已设置（不展示），如需修改请重新填写' : '请输入 API Key'"
          ></el-input>
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
        chatUrl: '',
        apiKey: '',
        hasApiKey: false,
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
          this.form.chatUrl = d.chatUrl || '';
          this.form.hasApiKey = !!d.hasApiKey;
          this.form.apiKey = '';
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
        chatUrl: this.form.chatUrl,
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
      if ((this.form.apiKey || '').trim()) {
        payload.apiKey = this.form.apiKey;
      }
      saveHomeAgentConfigApi(payload).then(() => {
        this.$message.success('保存成功');
        this.form.apiKey = '';
        this.getInfo();
      });
    },
  },
};
</script>
