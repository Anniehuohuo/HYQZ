import request from '@/libs/request';

export function forumPostListApi(params) {
  return request({
    url: 'forum/post',
    method: 'get',
    params,
  });
}

export function forumCommentListApi(params) {
  return request({
    url: 'forum/comment',
    method: 'get',
    params,
  });
}

export function forumLikeListApi(params) {
  return request({
    url: 'forum/like',
    method: 'get',
    params,
  });
}

export function forumDraftListApi(params) {
  return request({
    url: 'forum/draft',
    method: 'get',
    params,
  });
}
