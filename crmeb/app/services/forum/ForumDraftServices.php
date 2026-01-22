<?php

namespace app\services\forum;

use app\dao\forum\ForumDraftDao;
use app\services\BaseServices;
use crmeb\exceptions\ApiException;

class ForumDraftServices extends BaseServices
{
    public function __construct(ForumDraftDao $dao)
    {
        $this->dao = $dao;
    }

    public function getDraftList(int $uid): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $where = ['uid' => $uid, 'is_del' => 0];
        $list = $this->dao->getList($where, $page, $limit, 'id,uid,post_id,tab,title,content,add_time,update_time');
        $count = $this->dao->count($where);
        $list = array_map([$this, 'formatDraft'], $list);
        return compact('list', 'count');
    }

    public function getAdminDraftList(array $where): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $list = $this->dao->getList($where, $page, $limit, 'id,uid,post_id,tab,title,add_time,is_del');
        $count = $this->dao->count($where);
        $list = array_map(function ($d) {
            return [
                'id' => (int)$d['id'],
                'uid' => (int)($d['uid'] ?? 0),
                'post_id' => (int)($d['post_id'] ?? 0),
                'tab' => (string)($d['tab'] ?? ''),
                'title' => (string)($d['title'] ?? ''),
                'add_time' => $this->parseUnixTime($d['add_time'] ?? 0),
                'is_del' => (int)($d['is_del'] ?? 0),
            ];
        }, $list);
        return compact('list', 'count');
    }

    public function getDraftDetail(int $uid, int $draftId): array
    {
        $draft = $this->dao->get($draftId);
        if (!$draft || (int)$draft['is_del'] === 1) throw new ApiException('草稿不存在或已删除');
        if ((int)$draft['uid'] !== $uid) throw new ApiException('无权限');
        return $this->formatDraft($draft->toArray());
    }

    public function saveDraft(int $uid, array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $tab = trim((string)($data['tab'] ?? ''));
        $title = (string)($data['title'] ?? '');
        $content = (string)($data['content'] ?? '');
        $postId = (int)($data['postId'] ?? 0);

        if ($tab === '' && trim($title) === '' && trim($content) === '') {
            throw new ApiException('草稿内容为空');
        }
        if ($id) {
            $draft = $this->dao->get($id);
            if (!$draft || (int)$draft['is_del'] === 1) throw new ApiException('草稿不存在或已删除');
            if ((int)$draft['uid'] !== $uid) throw new ApiException('无权限');
            $draft->save([
                'post_id' => $postId,
                'tab' => $tab ?: $draft['tab'],
                'title' => $title,
                'content' => $content,
            ]);
            return $id;
        }

        $res = $this->dao->save([
            'uid' => $uid,
            'post_id' => $postId,
            'tab' => $tab,
            'title' => $title,
            'content' => $content,
            'is_del' => 0,
        ]);
        if (!$res) throw new ApiException('保存草稿失败');
        return (int)$res->id;
    }

    public function deleteDraft(int $uid, int $draftId): bool
    {
        $draft = $this->dao->get($draftId);
        if (!$draft || (int)$draft['is_del'] === 1) throw new ApiException('草稿不存在或已删除');
        if ((int)$draft['uid'] !== $uid) throw new ApiException('无权限');
        return false !== $draft->save(['is_del' => 1]);
    }

    public function adminDeleteDraft(int $draftId): void
    {
        $draft = $this->dao->get($draftId);
        if (!$draft) throw new ApiException('草稿不存在');
        if ((int)$draft['is_del'] === 0) {
            $draft->save(['is_del' => 1]);
            return;
        }

        $this->dao->delete($draftId);
    }

    private function formatDraft(array $d): array
    {
        $addTime = $this->parseUnixTime($d['add_time'] ?? 0);
        $updateTime = $this->parseUnixTime($d['update_time'] ?? 0);
        return [
            'id' => (int)$d['id'],
            'postId' => (int)($d['post_id'] ?? 0),
            'tab' => (string)($d['tab'] ?? ''),
            'title' => (string)($d['title'] ?? ''),
            'content' => (string)($d['content'] ?? ''),
            'add_time' => $this->normalizeDateTimeValue($d['add_time'] ?? null),
            'update_time' => $this->normalizeDateTimeValue($d['update_time'] ?? null),
            'createdAt' => $addTime ? date('Y-m-d H:i:s', $addTime) : '',
            'updatedAt' => $updateTime ? date('Y-m-d H:i:s', $updateTime) : '',
            'createdAtTs' => $addTime,
            'updatedAtTs' => $updateTime,
            'updatedTime' => $updateTime ? date('Y-m-d H:i', $updateTime) : '',
        ];
    }

    private function normalizeDateTimeValue($value): string
    {
        if (is_string($value)) {
            $s = trim($value);
            if ($s !== '' && !is_numeric($s)) return $s;
        }
        $ts = $this->parseUnixTime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : '';
    }

    private function parseUnixTime($value): int
    {
        if ($value === null || $value === '' || $value === false) return 0;
        if (is_int($value)) return $value > 0 ? $value : 0;
        if (is_numeric($value)) return (int)$value;
        $ts = strtotime((string)$value);
        return $ts ? (int)$ts : 0;
    }
}
