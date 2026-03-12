<?php

namespace app\services\ai;

use app\dao\ai\AiAgentDao;
use app\dao\ai\AiCategoryDao;
use app\dao\ai\AiChatMessageDao;
use app\dao\ai\AiChatSessionDao;
use app\services\BaseServices;
use app\services\product\product\StoreProductServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use think\facade\Db;

class AiAgentServices extends BaseServices
{
    protected AiCategoryDao $categoryDao;
    protected AiChatSessionDao $chatSessionDao;
    protected AiChatMessageDao $chatMessageDao;

    public function __construct(AiAgentDao $dao, AiCategoryDao $categoryDao, AiChatSessionDao $chatSessionDao, AiChatMessageDao $chatMessageDao)
    {
        $this->dao = $dao;
        $this->categoryDao = $categoryDao;
        $this->chatSessionDao = $chatSessionDao;
        $this->chatMessageDao = $chatMessageDao;
    }

    public function getAdminList(array $where, int $page, int $limit): array
    {
        $this->ensureProviderColumns();
        $this->ensureKbDocTable();
        $count = $this->dao->count($where);
        $list = $this->dao->getList($where, $page, $limit);
        if ($list) {
            $agentIds = array_values(array_unique(array_map(function ($item) {
                return (int)($item['id'] ?? 0);
            }, $list)));
            $cateIds = array_values(array_unique(array_map(function ($item) {
                return (int)($item['category_id'] ?? 0);
            }, $list)));
            $cateRows = $this->categoryDao->selectList([['id', 'in', $cateIds]], 'id,cate_key,cate_name');
            $cateMap = [];
            foreach ($cateRows as $row) {
                $cateMap[(int)$row['id']] = $row;
            }
            $bindMap = [];
            $priceMap = [];
            $kbCountMap = [];
            if ($agentIds) {
                try {
                    $bindRows = Db::name('ai_agent_goods')->whereIn('agent_id', $agentIds)->where('status', 1)->select()->toArray();
                    $productIds = [];
                    foreach ($bindRows as $r) {
                        $aid = (int)($r['agent_id'] ?? 0);
                        if ($aid <= 0) continue;
                        $bindMap[$aid] = $r;
                        $pid = (int)($r['product_id'] ?? 0);
                        if ($pid > 0) $productIds[] = $pid;
                    }
                    $productIds = array_values(array_unique($productIds));
                    if ($productIds) {
                        $rows = Db::name('store_product_attr_value')->whereIn('product_id', $productIds)->where('type', 0)->field('product_id,price')->select()->toArray();
                        foreach ($rows as $r) {
                            $pid = (int)($r['product_id'] ?? 0);
                            if ($pid <= 0) continue;
                            $priceMap[$pid] = (float)($r['price'] ?? 0);
                        }
                    }
                } catch (\Throwable $e) {
                    $bindMap = [];
                    $priceMap = [];
                }
                try {
                    $rows = Db::name('ai_agent_kb_doc')
                        ->whereIn('agent_id', $agentIds)
                        ->where('status', 1)
                        ->field('agent_id,COUNT(*) as c')
                        ->group('agent_id')
                        ->select()
                        ->toArray();
                    foreach ($rows as $row) {
                        $kbCountMap[(int)($row['agent_id'] ?? 0)] = (int)($row['c'] ?? 0);
                    }
                } catch (\Throwable $e) {
                    $kbCountMap = [];
                }
            }
            foreach ($list as &$item) {
                $c = $cateMap[(int)$item['category_id']] ?? null;
                $item['cate_key'] = $c['cate_key'] ?? '';
                $item['cate_name'] = $c['cate_name'] ?? '';
                $bind = $bindMap[(int)$item['id']] ?? null;
                $item['product_id'] = (int)($bind['product_id'] ?? 0);
                $item['gift_power'] = (int)($bind['gift_power'] ?? 0);
                $item['unlock_price'] = (float)($priceMap[(int)($bind['product_id'] ?? 0)] ?? 0);
                if (!isset($item['provider']) || $item['provider'] === '') $item['provider'] = 'local';
                if (!isset($item['provider_assistant_id'])) $item['provider_assistant_id'] = '';
                $meta = $this->decodeProviderMeta($item['provider_meta'] ?? '');
                $item['welcome'] = (string)($meta['welcome'] ?? '');
                $item['suggestions'] = $this->normalizeSuggestions($meta['suggestions'] ?? null);
                $item['context_mode'] = (string)($meta['context_mode'] ?? 'platform');
                $item['system_prompt'] = (string)($meta['system_prompt'] ?? '');
                $item['temperature'] = $meta['temperature'] ?? '';
                $item['managed_model'] = (string)($meta['managed_model'] ?? '');
                $item['managed_knowledge'] = (string)($meta['managed_knowledge'] ?? '');
                $item['kb_doc_count'] = (int)($kbCountMap[(int)$item['id']] ?? 0);
            }
            unset($item);
        }
        return compact('list', 'count');
    }

    public function create(array $data): void
    {
        $this->ensureProviderColumns();
        $categoryId = (int)($data['category_id'] ?? 0);
        if (!$this->categoryDao->be($categoryId)) {
            throw new AdminException('分类不存在');
        }

        $provider = trim((string)($data['provider'] ?? 'local'));
        if ($provider === '') $provider = 'local';
        if (!in_array($provider, ['local', 'qingyan', 'managed', 'coze'], true)) {
            throw new AdminException('provider不合法');
        }

        $botId = trim((string)($data['bot_id'] ?? ''));
        $apiKey = (string)($data['api_key'] ?? '');
        $assistantId = trim((string)($data['provider_assistant_id'] ?? ''));
        if ($provider === 'local' || $provider === 'coze') {
            if ($botId === '') throw new AdminException('bot_id不能为空');
            if (trim($apiKey) === '') throw new AdminException('api_key不能为空');
            if ($this->dao->be(['bot_id' => $botId])) throw new AdminException('bot_id已存在');
        } elseif ($provider === 'qingyan') {
            if ($assistantId === '') throw new AdminException('assistant_id不能为空');
            if ($this->dao->be([['provider', '=', 'qingyan'], ['provider_assistant_id', '=', $assistantId]])) {
                throw new AdminException('assistant_id已存在');
            }
            $botId = $botId === '' ? ('qy:' . $assistantId) : $botId;
            $apiKey = '';
        } else {
            if ($botId === '') {
                $botId = 'managed:' . substr(md5(uniqid('managed_', true)), 0, 20);
            }
            if ($this->dao->be(['bot_id' => $botId])) throw new AdminException('bot_id已存在');
            $apiKey = '';
            $assistantId = '';
        }

        $res = $this->dao->save([
            'agent_name' => (string)($data['agent_name'] ?? ''),
            'avatar' => (string)($data['avatar'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'category_id' => $categoryId,
            'bot_id' => $botId,
            'api_key' => (string)$apiKey,
            'provider' => $provider,
            'provider_assistant_id' => $assistantId,
            'provider_meta' => $this->encodeProviderMeta([
                'welcome' => trim((string)($data['welcome'] ?? '')),
                'suggestions' => $this->normalizeSuggestions($data['suggestions'] ?? null),
                'context_mode' => trim((string)($data['context_mode'] ?? 'platform')),
                'system_prompt' => trim((string)($data['system_prompt'] ?? '')),
                'temperature' => $data['temperature'] ?? '',
                'managed_model' => trim((string)($data['managed_model'] ?? '')),
                'managed_knowledge' => trim((string)($data['managed_knowledge'] ?? '')),
            ]),
            'tags' => (string)($data['tags'] ?? ''),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ]);
        $agentId = (int)($res->id ?? 0);
        if ($agentId > 0) {
            $this->ensureSellBinding($agentId, $data);
        }
    }

    public function update(int $id, array $data): void
    {
        $this->ensureProviderColumns();
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }

        $categoryId = (int)($data['category_id'] ?? $info['category_id']);
        if (!$this->categoryDao->be($categoryId)) {
            throw new AdminException('分类不存在');
        }

        $provider = trim((string)($data['provider'] ?? $info['provider'] ?? 'local'));
        if ($provider === '') $provider = 'local';
        if (!in_array($provider, ['local', 'qingyan', 'managed', 'coze'], true)) {
            throw new AdminException('provider不合法');
        }

        $botId = trim((string)($data['bot_id'] ?? $info['bot_id']));
        $apiKey = (string)($data['api_key'] ?? $info['api_key']);
        $assistantId = trim((string)($data['provider_assistant_id'] ?? $info['provider_assistant_id'] ?? ''));
        if ($provider === 'local' || $provider === 'coze') {
            if ($botId === '') throw new AdminException('bot_id不能为空');
            if (trim($apiKey) === '') throw new AdminException('api_key不能为空');
            if ($this->dao->be([['bot_id', '=', $botId], ['id', '<>', $id]])) throw new AdminException('bot_id已存在');
        } elseif ($provider === 'qingyan') {
            if ($assistantId === '') throw new AdminException('assistant_id不能为空');
            if ($this->dao->be([['provider', '=', 'qingyan'], ['provider_assistant_id', '=', $assistantId], ['id', '<>', $id]])) {
                throw new AdminException('assistant_id已存在');
            }
            $botId = $botId === '' ? ('qy:' . $assistantId) : $botId;
            $apiKey = '';
        } else {
            if ($botId === '') {
                $botId = 'managed:' . $id;
            }
            if ($this->dao->be([['bot_id', '=', $botId], ['id', '<>', $id]])) throw new AdminException('bot_id已存在');
            $apiKey = '';
            $assistantId = '';
        }

        $meta = $this->decodeProviderMeta($info['provider_meta'] ?? '');
        if (array_key_exists('welcome', $data)) {
            $meta['welcome'] = trim((string)($data['welcome'] ?? ''));
        }
        if (array_key_exists('suggestions', $data)) {
            $meta['suggestions'] = $this->normalizeSuggestions($data['suggestions'] ?? null);
        }
        if (array_key_exists('context_mode', $data)) {
            $meta['context_mode'] = trim((string)($data['context_mode'] ?? 'platform'));
        }
        if (array_key_exists('system_prompt', $data)) {
            $meta['system_prompt'] = trim((string)($data['system_prompt'] ?? ''));
        }
        if (array_key_exists('temperature', $data)) {
            $meta['temperature'] = $data['temperature'];
        }
        if (array_key_exists('managed_model', $data)) {
            $meta['managed_model'] = trim((string)($data['managed_model'] ?? ''));
        }
        if (array_key_exists('managed_knowledge', $data)) {
            $meta['managed_knowledge'] = trim((string)($data['managed_knowledge'] ?? ''));
        }

        $this->dao->update($id, [
            'agent_name' => (string)($data['agent_name'] ?? $info['agent_name']),
            'avatar' => (string)($data['avatar'] ?? $info['avatar']),
            'description' => (string)($data['description'] ?? $info['description']),
            'category_id' => $categoryId,
            'bot_id' => $botId,
            'api_key' => (string)$apiKey,
            'provider' => $provider,
            'provider_assistant_id' => $assistantId,
            'provider_meta' => $this->encodeProviderMeta($meta),
            'tags' => (string)($data['tags'] ?? $info['tags']),
            'sort' => (int)($data['sort'] ?? $info['sort']),
            'status' => (int)($data['status'] ?? $info['status']),
        ]);
        $this->ensureSellBinding($id, array_merge($info->toArray(), $data));
    }

    protected function decodeProviderMeta($raw): array
    {
        if (is_array($raw)) return $raw;
        $s = trim((string)$raw);
        if ($s === '') return [];
        $v = json_decode($s, true);
        return is_array($v) ? $v : [];
    }

    protected function encodeProviderMeta(array $meta): string
    {
        return json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    protected function normalizeSuggestions($value): array
    {
        $list = [];
        if (is_array($value)) {
            $list = $value;
        } elseif (is_string($value)) {
            $lines = preg_split("/\\r?\\n/", $value) ?: [];
            $list = $lines;
        }
        $out = [];
        foreach ((array)$list as $x) {
            $s = trim((string)$x);
            if ($s === '') continue;
            $out[] = $s;
            if (count($out) >= 6) break;
        }
        return $out;
    }

    protected function ensureProviderColumns(): void
    {
        $cacheKey = 'ai_agents_has_provider_columns';
        $cached = CacheService::get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return;
        }
        try {
            $prefix = (string)config('database.connections.mysql.prefix', '');
            $table = $prefix . 'ai_agents';
            $cols = Db::query("SHOW COLUMNS FROM `{$table}`");
            $hasProvider = false;
            $hasAssistant = false;
            foreach ((array)$cols as $c) {
                $f = (string)($c['Field'] ?? '');
                if ($f === 'provider') $hasProvider = true;
                if ($f === 'provider_assistant_id') $hasAssistant = true;
            }
            if (!$hasProvider) {
                Db::execute("ALTER TABLE `{$table}` ADD COLUMN `provider` varchar(20) NOT NULL DEFAULT 'local' AFTER `api_key`");
            }
            if (!$hasAssistant) {
                Db::execute("ALTER TABLE `{$table}` ADD COLUMN `provider_assistant_id` varchar(128) NOT NULL DEFAULT '' AFTER `provider`");
            }
            $cols = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'provider_meta'");
            $hasMeta = is_array($cols) && count($cols) > 0;
            if (!$hasMeta) {
                Db::execute("ALTER TABLE `{$table}` ADD COLUMN `provider_meta` text NULL AFTER `provider_assistant_id`");
            }
            CacheService::set($cacheKey, 1, 3600);
        } catch (\Throwable $e) {
            CacheService::set($cacheKey, 1, 3600);
        }
    }

    protected function ensureKbDocTable(): void
    {
        $cacheKey = 'ai_agent_kb_doc_table_ready';
        $cached = CacheService::get($cacheKey);
        if ($cached !== null && $cached !== '') return;
        try {
            $prefix = (string)config('database.connections.mysql.prefix', '');
            $table = $prefix . 'ai_agent_kb_doc';
            Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(10) unsigned NOT NULL DEFAULT '0',
  `attachment_id` int(10) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `file_ext` varchar(16) NOT NULL DEFAULT '',
  `content` longtext NULL,
  `chunks_json` longtext NULL,
  `chunk_count` int(10) unsigned NOT NULL DEFAULT '0',
  `content_len` int(10) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_status` (`agent_id`,`status`),
  KEY `idx_attachment` (`attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            CacheService::set($cacheKey, 1, 3600);
        } catch (\Throwable $e) {
            CacheService::set($cacheKey, 1, 300);
        }
    }

    public function getKbDocs(int $agentId): array
    {
        $this->ensureProviderColumns();
        $this->ensureKbDocTable();
        $info = $this->dao->get($agentId);
        if (!$info) throw new AdminException('智能体不存在');
        $provider = trim((string)($info['provider'] ?? 'local'));
        if ($provider !== 'managed') throw new AdminException('仅中台托管模式可管理文档知识库');
        $list = Db::name('ai_agent_kb_doc')
            ->where('agent_id', $agentId)
            ->where('status', 1)
            ->order('id DESC')
            ->field('id,agent_id,attachment_id,title,file_ext,chunk_count,content_len,created_at,updated_at')
            ->select()
            ->toArray();
        return ['list' => $list];
    }

    public function importKbDoc(int $agentId, int $attachmentId, string $attDir = ''): array
    {
        $attDir = trim($attDir);
        if ($attachmentId <= 0 && $attDir === '') throw new AdminException('附件ID或文件路径不能为空');
        $this->ensureProviderColumns();
        $this->ensureKbDocTable();
        $info = $this->dao->get($agentId);
        if (!$info) throw new AdminException('智能体不存在');
        $provider = trim((string)($info['provider'] ?? 'local'));
        if ($provider !== 'managed') throw new AdminException('仅中台托管模式可导入文档知识库');

        if ($attachmentId > 0) {
            $att = Db::name('system_attachment')->where('att_id', $attachmentId)->find();
        } else {
            $att = Db::name('system_attachment')->where('att_dir', $attDir)->order('att_id DESC')->find();
            $attachmentId = (int)($att['att_id'] ?? 0);
        }
        if (!$att) throw new AdminException('附件不存在');
        $title = trim((string)($att['real_name'] ?? $att['name'] ?? ''));
        $ext = strtolower((string)pathinfo($title !== '' ? $title : (string)($att['att_dir'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') $ext = strtolower((string)pathinfo((string)($att['att_dir'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md', 'csv', 'json', 'log', 'docx', 'doc'], true)) {
            throw new AdminException('暂仅支持 txt/md/csv/json/log/doc/docx 文档');
        }

        $text = $this->readAttachmentText((string)($att['att_dir'] ?? ''), $ext);
        $text = trim($text);
        if ($text === '') throw new AdminException('文档内容为空或无法解析');
        $chunks = $this->buildLightChunks($text, 600, 100);
        if (!$chunks) throw new AdminException('文档切片失败');
        $now = date('Y-m-d H:i:s');
        $payload = [
            'agent_id' => $agentId,
            'attachment_id' => $attachmentId,
            'title' => $title !== '' ? $title : ('文档' . $attachmentId),
            'file_ext' => $ext,
            'content' => $text,
            'chunks_json' => json_encode($chunks, JSON_UNESCAPED_UNICODE),
            'chunk_count' => count($chunks),
            'content_len' => mb_strlen($text),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $exist = Db::name('ai_agent_kb_doc')
            ->where('agent_id', $agentId)
            ->where('attachment_id', $attachmentId)
            ->where('status', 1)
            ->find();
        if ($exist) {
            Db::name('ai_agent_kb_doc')->where('id', (int)$exist['id'])->update($payload);
            $docId = (int)$exist['id'];
        } else {
            $docId = (int)Db::name('ai_agent_kb_doc')->insertGetId($payload);
        }
        return [
            'id' => $docId,
            'chunk_count' => count($chunks),
            'content_len' => mb_strlen($text),
        ];
    }

    public function deleteKbDoc(int $agentId, int $docId): void
    {
        $this->ensureKbDocTable();
        $info = $this->dao->get($agentId);
        if (!$info) throw new AdminException('智能体不存在');
        $row = Db::name('ai_agent_kb_doc')
            ->where('id', $docId)
            ->where('agent_id', $agentId)
            ->where('status', 1)
            ->find();
        if (!$row) throw new AdminException('文档不存在');
        Db::name('ai_agent_kb_doc')->where('id', $docId)->update([
            'status' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function readAttachmentText(string $attDir, string $ext): string
    {
        $path = trim($attDir);
        if ($path === '') return '';
        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            $uPath = (string)parse_url($path, PHP_URL_PATH);
            $path = $uPath !== '' ? $uPath : $path;
        }
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        $candidates = [];
        if ($path !== '') {
            $candidates[] = root_path() . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $candidates[] = root_path() . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        $file = '';
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $file = $candidate;
                break;
            }
        }
        if ($file === '') return '';
        if ($ext === 'docx') {
            return $this->readDocxText($file);
        }
        if ($ext === 'doc') {
            throw new AdminException('暂不支持旧版.doc解析，请另存为.docx后再导入');
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') return '';
        if ($ext === 'csv') {
            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $enc = mb_detect_encoding($raw, ['UTF-8', 'GBK', 'GB2312', 'BIG5'], true);
            if ($enc && strtoupper($enc) !== 'UTF-8') {
                $raw = @mb_convert_encoding($raw, 'UTF-8', $enc);
            }
        }
        return is_string($raw) ? $raw : '';
    }

    protected function readDocxText(string $file): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new AdminException('服务器缺少ZipArchive扩展，暂无法解析docx');
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return '';
        }
        $xml = str_replace(['</w:p>', '</w:tr>', '</w:tbl>'], ["\n", "\n", "\n"], $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', (string)$text);
        $text = preg_replace('/\n{3,}/u', "\n\n", (string)$text);
        return trim((string)$text);
    }

    protected function buildLightChunks(string $text, int $size = 600, int $overlap = 100): array
    {
        $s = trim(preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r"], "\n", $text)));
        if ($s === '') return [];
        $len = mb_strlen($s);
        $size = max(200, min(1200, $size));
        $overlap = max(0, min((int)($size / 2), $overlap));
        $step = max(1, $size - $overlap);
        $chunks = [];
        for ($start = 0; $start < $len; $start += $step) {
            $part = trim(mb_substr($s, $start, $size));
            if ($part === '') continue;
            $chunks[] = ['idx' => count($chunks) + 1, 'text' => $part];
            if (count($chunks) >= 300) break;
            if ($start + $size >= $len) break;
        }
        return $chunks;
    }

    public function delete(int $id): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }
        $this->transaction(function () use ($id) {
            $sessionIds = $this->chatSessionDao->getColumn(['agent_id' => $id], 'id');
            if ($sessionIds) {
                $this->chatMessageDao->delete([['session_id', 'in', $sessionIds]]);
                $this->chatSessionDao->delete([['agent_id', '=', $id]]);
            }
            $this->dao->delete($id);
        });
    }

    public function setStatus(int $id, int $status): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }
        $this->dao->update($id, ['status' => $status ? 1 : 0]);
    }

    public function autoBindGoods(int $limit = 200): array
    {
        $limit = max(1, min(1000, (int)$limit));
        $rows = [];
        try {
            $rows = Db::name('ai_agents')->alias('a')
                ->leftJoin('ai_agent_goods g', 'g.agent_id = a.id AND g.status = 1')
                ->where('a.status', 1)
                ->where(function ($q) {
                    $q->whereNull('g.id')->whereOr('g.product_id', 0);
                })
                ->field('a.id,a.agent_name,a.avatar,a.description,a.tags,a.status')
                ->order('a.id DESC')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $success = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $row) {
            $agentId = (int)($row['id'] ?? 0);
            if ($agentId <= 0) continue;
            try {
                $this->ensureSellBinding($agentId, $row);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['agent_id' => $agentId, 'msg' => $e->getMessage()];
            }
        }
        return [
            'total' => count($rows),
            'success' => $success,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    protected function ensureSellBinding(int $agentId, array $data): void
    {
        $auto = array_key_exists('auto_bind_product', $data) ? (int)$data['auto_bind_product'] : 1;
        if (!$auto) {
            return;
        }
        $giftPower = (int)($data['gift_power'] ?? 99);
        $productId = (int)($data['product_id'] ?? 0);
        $unlockPrice = (float)($data['unlock_price'] ?? 0);
        $bindRow = null;
        if ($productId <= 0) {
            try {
                $bindRow = Db::name('ai_agent_goods')->where('agent_id', $agentId)->where('status', 1)->find();
            } catch (\Throwable $e) {
                $bindRow = null;
            }
            if ($bindRow && (int)($bindRow['product_id'] ?? 0) > 0) {
                $productId = (int)$bindRow['product_id'];
            } else {
                $productId = $this->createSellProductForAgent($agentId, $data);
            }
        }
        if ($productId > 0) {
            app()->make(AiAgentGoodsServices::class)->bind($agentId, $productId, $giftPower, 1);
            if ($unlockPrice > 0) {
                $oldPrice = $this->getSellProductPrice($productId);
                if ($oldPrice > 0 && $unlockPrice < $oldPrice) {
                    $this->compensatePriceDropToPower($agentId, $productId, $oldPrice, $unlockPrice);
                }
                $this->updateSellProductPrice($productId, $unlockPrice);
            }
        }
    }

    protected function getSellProductPrice(int $productId): float
    {
        $productId = (int)$productId;
        if ($productId <= 0) return 0;
        try {
            $p = Db::name('store_product_attr_value')->where('product_id', $productId)->where('type', 0)->value('price');
            $v = (float)$p;
            if ($v > 0) return $v;
        } catch (\Throwable $e) {
        }
        try {
            $p = Db::name('store_product')->where('id', $productId)->value('price');
            return max(0, (float)$p);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function compensatePriceDropToPower(int $agentId, int $productId, float $oldPrice, float $newPrice): void
    {
        $agentId = (int)$agentId;
        $productId = (int)$productId;
        if ($agentId <= 0 || $productId <= 0) return;
        $oldCents = (int)round(max(0, $oldPrice) * 100);
        $newCents = (int)round(max(0, $newPrice) * 100);
        $diffCents = max(0, $oldCents - $newCents);
        if ($diffCents <= 0) return;

        $rate = $this->getBestPowerPerYuan();
        $refundPower = (int)ceil(($diffCents * $rate) / 100);
        if ($refundPower <= 0) return;

        $page = 1;
        $limit = 500;
        while (true) {
            $uids = [];
            try {
                $uids = Db::name('store_order')->alias('a')
                    ->join('store_order_cart_info c', 'a.id = c.oid')
                    ->where('a.paid', 1)
                    ->where('a.refund_status', 0)
                    ->where('a.is_del', 0)
                    ->where('a.is_system_del', 0)
                    ->where('c.product_id', $productId)
                    ->field('a.uid')
                    ->group('a.uid')
                    ->page($page, $limit)
                    ->column('a.uid');
            } catch (\Throwable $e) {
                $uids = [];
            }
            if (!$uids) break;
            foreach ($uids as $uid) {
                $uid = (int)$uid;
                if ($uid <= 0) continue;
                $ok = app()->make(AiPowerServices::class)->increaseBalance($uid, $refundPower);
                if ($ok) {
                    try {
                        Db::name('ai_power_bill')->insert([
                            'uid' => $uid,
                            'agent_id' => $agentId,
                            'session_id' => 'price_drop:' . $productId . ':' . $oldCents . '->' . $newCents,
                            'cost' => $refundPower,
                            'type' => 'price_drop',
                            'add_time' => time(),
                        ]);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (count($uids) < $limit) break;
            $page++;
        }
    }

    protected function getBestPowerPerYuan(): float
    {
        $raw = trim((string)sys_config('ai_power_recharge_packages', ''));
        $decoded = [];
        if ($raw !== '') {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) $decoded = $tmp;
        }
        if (!$decoded) {
            $decoded = [
                ['price' => '9.90', 'power' => 30],
                ['price' => '19.90', 'power' => 80],
                ['price' => '49.90', 'power' => 240],
            ];
        }
        $best = 0.0;
        foreach ($decoded as $it) {
            if (!is_array($it)) continue;
            $price = (float)($it['price'] ?? 0);
            $power = (int)($it['power'] ?? ($it['power_amount'] ?? 0));
            if ($price <= 0 || $power <= 0) continue;
            $best = max($best, $power / $price);
        }
        if ($best <= 0) $best = 4.0;
        return $best;
    }

    protected function createSellProductForAgent(int $agentId, array $data): int
    {
        $agentName = trim((string)($data['agent_name'] ?? ''));
        $desc = trim((string)($data['description'] ?? ''));
        $tags = trim((string)($data['tags'] ?? ''));
        if ($agentName === '') {
            throw new AdminException('智能体名称不能为空');
        }
        $img = trim((string)($data['avatar'] ?? ''));
        if ($img === '') {
            $img = (string)sys_config('wap_login_logo');
        }
        if ($img === '') {
            $img = (string)sys_config('site_logo');
        }
        $img = trim($img);
        if ($img === '') {
            throw new AdminException('请先配置移动端登录logo或上传智能体头像，用于自动生成售卖商品封面');
        }

        $cateId = $this->resolveSellProductCateId($img);
        $price = (float)($data['unlock_price'] ?? 0.01);
        if ($price <= 0) {
            $price = 0.01;
        }
        $priceText = number_format($price, 2, '.', '');
        $storeName = $agentName . '（智能体解锁）';
        $keyword = trim($tags . ' ' . $agentName);
        $storeInfo = $desc !== '' ? $desc : $agentName;
        $description = $desc !== '' ? $desc : $agentName;

        $productData = [
            'cate_id' => [$cateId],
            'store_name' => $storeName,
            'keyword' => $keyword,
            'store_info' => $storeInfo,
            'unit_name' => '套',
            'bar_code' => '',
            'slider_image' => [$img],
            'spec_type' => 0,
            'items' => [
                [
                    'value' => '规格',
                    'detail' => ['默认'],
                ],
            ],
            'attrs' => [
                [
                    'detail' => ['规格' => '默认'],
                    'price' => $priceText,
                    'vip_price' => $priceText,
                    'ot_price' => $priceText,
                    'cost' => '0',
                    'stock' => 999999,
                    'pic' => $img,
                    'bar_code' => '',
                    'bar_code_number' => '',
                    'weight' => 0,
                    'volume' => 0,
                    'brokerage' => 0,
                    'brokerage_two' => 0,
                    'is_default_select' => 1,
                ],
            ],
            'coupon_ids' => [],
            'description' => $description,
            'type' => 0,
            'recommend_list' => [],
            'is_sub' => [],
            'vip_product' => 0,
            'presale' => 0,
            'presale_time' => [],
            'is_limit' => 0,
            'min_qty' => 1,
            'limit_type' => 0,
            'limit_num' => 0,
            'virtual_type' => 1,
            'logistics' => [],
            'custom_form' => [],
            'params_list' => [],
            'label_list' => [],
            'protection_list' => [],
            'freight' => 1,
            'temp_id' => 0,
            'postage' => 0,
            'recommend' => [],
            'activity' => [],
            'label_id' => [],
            'is_show' => 1,
            'is_copy' => 0,
        ];

        $storeProductServices = app()->make(StoreProductServices::class);
        $res = $storeProductServices->save(0, $productData);
        $pid = 0;
        try {
            if (is_object($res) && isset($res->id)) {
                $pid = (int)$res->id;
            } elseif (is_array($res) && isset($res['id'])) {
                $pid = (int)$res['id'];
            }
        } catch (\Throwable $e) {
            $pid = 0;
        }
        if ($pid <= 0) {
            $pid = (int)Db::name('store_product')->where('store_name', $storeName)->order('id DESC')->value('id');
        }
        if ($pid <= 0) {
            throw new AdminException('自动创建售卖商品失败');
        }
        return $pid;
    }

    protected function updateSellProductPrice(int $productId, float $unlockPrice): void
    {
        $productId = (int)$productId;
        if ($productId <= 0) return;
        $unlockPrice = (float)$unlockPrice;
        if ($unlockPrice <= 0) return;
        $priceText = number_format($unlockPrice, 2, '.', '');
        try {
            Db::transaction(function () use ($productId, $priceText) {
                Db::name('store_product')->where('id', $productId)->update([
                    'price' => $priceText,
                    'ot_price' => $priceText,
                ]);
                Db::name('store_product_attr_value')->where('product_id', $productId)->where('type', 0)->update([
                    'price' => $priceText,
                    'ot_price' => $priceText,
                    'vip_price' => $priceText,
                ]);
                $attrResult = null;
                try {
                    $attrResult = Db::name('store_product_attr_result')->where('product_id', $productId)->where('type', 0)->find();
                } catch (\Throwable $e) {
                    $attrResult = null;
                }
                if ($attrResult && !empty($attrResult['result'])) {
                    $payload = json_decode((string)$attrResult['result'], true);
                    if (is_array($payload) && isset($payload['value']) && is_array($payload['value'])) {
                        foreach ($payload['value'] as &$sku) {
                            if (!is_array($sku)) continue;
                            if (array_key_exists('price', $sku)) $sku['price'] = $priceText;
                            if (array_key_exists('ot_price', $sku)) $sku['ot_price'] = $priceText;
                            if (array_key_exists('vip_price', $sku)) $sku['vip_price'] = $priceText;
                        }
                        unset($sku);
                        Db::name('store_product_attr_result')->where('product_id', $productId)->where('type', 0)->update([
                            'result' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                            'change_time' => time(),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
        }
    }

    protected function resolveSellProductCateId(string $img): int
    {
        $cid = 0;
        try {
            $cid = (int)Db::name('store_category')->where('is_show', 1)->order('sort DESC,id DESC')->value('id');
        } catch (\Throwable $e) {
            $cid = 0;
        }
        if ($cid > 0) return $cid;
        $now = time();
        $cid = (int)Db::name('store_category')->insertGetId([
            'pid' => 0,
            'cate_name' => 'AI智能体',
            'sort' => 0,
            'pic' => $img,
            'is_show' => 1,
            'add_time' => $now,
            'big_pic' => $img,
        ]);
        if ($cid <= 0) {
            throw new AdminException('自动创建商品分类失败');
        }
        return $cid;
    }
}
