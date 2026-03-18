import LayoutMain from '@/layout';
import setting from '@/setting';
let routePre = setting.routePre;

const pre = 'ai_';

export default {
  path: routePre + '/ai',
  alias: [routePre + '/ai/'],
  name: 'ai',
  header: 'ai',
  redirect: `${routePre}/ai/home_agent`,
  meta: {
    title: '智能体中心',
    icon: 'md-cube'
  },
  component: LayoutMain,
  children: [
    {
      path: 'home_agent',
      alias: ['home_agent/'],
      name: `${pre}homeAgent`,
      meta: {
        title: '首页引流助手',
        auth: ['ai-home-agent']
      },
      component: () => import('@/pages/ai/homeAgent/index'),
    },
    {
      path: 'agent_matrix',
      alias: ['agent_matrix/'],
      name: `${pre}agentMatrix`,
      meta: {
        title: '技能课超市',
        auth: ['ai-agent-matrix']
      },
      component: () => import('@/pages/ai/agentMatrix/index'),
    },
  ],
};
