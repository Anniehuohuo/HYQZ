// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2024 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

import request from '@/libs/request';

export function getHomeAgentConfigApi() {
  return request({
    url: 'ai/home_agent',
    method: 'get',
  });
}

export function saveHomeAgentConfigApi(data) {
  return request({
    url: 'ai/home_agent',
    method: 'post',
    data,
  });
}

export function getAiAgentCategoriesApi(params) {
  return request({
    url: 'ai/agent_categories',
    method: 'get',
    params,
  });
}

export function createAiAgentCategoryApi(data) {
  return request({
    url: 'ai/agent_categories',
    method: 'post',
    data,
  });
}

export function updateAiAgentCategoryApi(id, data) {
  return request({
    url: `ai/agent_categories/${id}`,
    method: 'put',
    data,
  });
}

export function deleteAiAgentCategoryApi(id) {
  return request({
    url: `ai/agent_categories/${id}`,
    method: 'delete',
  });
}

export function setAiAgentCategoryStatusApi(id, status) {
  return request({
    url: `ai/agent_categories/set_status/${id}/${status}`,
    method: 'put',
  });
}

export function getAiAgentsApi(params) {
  return request({
    url: 'ai/agents',
    method: 'get',
    params,
  });
}

export function createAiAgentApi(data) {
  return request({
    url: 'ai/agents',
    method: 'post',
    data,
  });
}

export function updateAiAgentApi(id, data) {
  return request({
    url: `ai/agents/${id}`,
    method: 'put',
    data,
  });
}

export function deleteAiAgentApi(id) {
  return request({
    url: `ai/agents/${id}`,
    method: 'delete',
  });
}

export function setAiAgentStatusApi(id, status) {
  return request({
    url: `ai/agents/set_status/${id}/${status}`,
    method: 'put',
  });
}

export function getAiAgentKbDocsApi(id) {
  return request({
    url: `ai/agents/${id}/kb_docs`,
    method: 'get',
  });
}

export function importAiAgentKbDocApi(id, data) {
  return request({
    url: `ai/agents/${id}/kb_docs/import`,
    method: 'post',
    data,
  });
}

export function deleteAiAgentKbDocApi(id, docId) {
  return request({
    url: `ai/agents/${id}/kb_docs/${docId}`,
    method: 'delete',
  });
}

export function getAiPowerConfigApi() {
  return request({
    url: 'ai/power_config',
    method: 'get',
  });
}

export function saveAiPowerConfigApi(data) {
  return request({
    url: 'ai/power_config',
    method: 'post',
    data,
  });
}

export function getQingyanConfigApi() {
  return request({
    url: 'ai/qingyan_config',
    method: 'get',
  });
}

export function saveQingyanConfigApi(data) {
  return request({
    url: 'ai/qingyan_config',
    method: 'post',
    data,
  });
}

export function verifyQingyanAssistantApi(data) {
  return request({
    url: 'ai/qingyan_config/verify',
    method: 'post',
    data,
  });
}

