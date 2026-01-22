import LayoutMain from '@/layout';
import setting from '@/setting';
let routePre = setting.routePre;

const pre = 'forum_';

export default {
  path: routePre + '/forum',
  name: 'forum',
  header: 'forum',
  redirect: {
    name: `${pre}post`,
  },
  component: LayoutMain,
  children: [
    {
      path: 'post/index',
      name: `${pre}post`,
      meta: {
        auth: ['forum-post-index'],
        title: '帖子管理',
        keepAlive: true,
      },
      component: () => import('@/pages/forum/post/index'),
    },
    {
      path: 'comment/index',
      name: `${pre}comment`,
      meta: {
        auth: ['forum-comment-index'],
        title: '评论管理',
        keepAlive: true,
      },
      component: () => import('@/pages/forum/comment/index'),
    },
    {
      path: 'like/index',
      name: `${pre}like`,
      meta: {
        auth: ['forum-like-index'],
        title: '点赞管理',
        keepAlive: true,
      },
      component: () => import('@/pages/forum/like/index'),
    },
    {
      path: 'draft/index',
      name: `${pre}draft`,
      meta: {
        auth: ['forum-draft-index'],
        title: '草稿管理',
        keepAlive: true,
      },
      component: () => import('@/pages/forum/draft/index'),
    },
  ],
};
