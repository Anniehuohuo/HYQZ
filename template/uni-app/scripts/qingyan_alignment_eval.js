const fs = require('fs')
const path = require('path')

function nowIsoCompact() {
  const d = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
}

function getEnv(name, fallback = '') {
  const v = process.env[name]
  return v === undefined || v === null || v === '' ? fallback : String(v)
}

function parseArgs(argv) {
  const out = { _: [] }
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i]
    if (!a) continue
    if (a.startsWith('--')) {
      const eq = a.indexOf('=')
      if (eq > -1) {
        out[a.slice(2, eq)] = a.slice(eq + 1)
      } else {
        const k = a.slice(2)
        const next = argv[i + 1]
        if (next && !next.startsWith('--')) {
          out[k] = next
          i++
        } else {
          out[k] = 'true'
        }
      }
    } else {
      out._.push(a)
    }
  }
  return out
}

function jsonPost(url, body, headers = {}, timeoutMs = 60000) {
  const controller = new AbortController()
  const t = setTimeout(() => controller.abort(), timeoutMs)
  return fetch(url, {
    method: 'POST',
    headers: Object.assign({ 'Content-Type': 'application/json', Accept: 'application/json' }, headers),
    body: JSON.stringify(body),
    signal: controller.signal,
  })
    .then(async (res) => {
      const text = await res.text()
      let data = null
      try {
        data = JSON.parse(text)
      } catch (e) {
        data = null
      }
      return { ok: res.ok, status: res.status, text, data }
    })
    .finally(() => clearTimeout(t))
}

function extractQingyanText(result) {
  if (!result || typeof result !== 'object') return ''
  const msg = result.message || result
  if (!msg || typeof msg !== 'object') return ''
  const c = msg.content
  if (typeof c === 'string') return c
  if (!c || typeof c !== 'object') return ''
  if (Array.isArray(c)) {
    let out = ''
    for (const item of c) {
      if (!item || typeof item !== 'object') continue
      const type = String(item.type || '')
      if (type !== 'text' && type !== 'markdown') continue
      const t = item.text || item.content || ''
      if (typeof t === 'string' && t) out += t
    }
    return out
  }
  const type = String(c.type || '')
  if (type !== 'text' && type !== 'markdown') return ''
  const t = c.text || c.content || ''
  return typeof t === 'string' ? t : ''
}

function normalizeForMetric(s) {
  return String(s || '')
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+/g, ' ')
    .trim()
}

function charTokens(s) {
  const text = normalizeForMetric(s)
  if (!text) return []
  return Array.from(text)
}

function ngrams(tokens, n) {
  const out = new Map()
  if (tokens.length < n) return out
  for (let i = 0; i <= tokens.length - n; i++) {
    const g = tokens.slice(i, i + n).join('')
    out.set(g, (out.get(g) || 0) + 1)
  }
  return out
}

function bleuScore(ref, cand, maxN = 4) {
  const r = charTokens(ref)
  const c = charTokens(cand)
  if (!r.length || !c.length) return 0
  const precisions = []
  for (let n = 1; n <= maxN; n++) {
    const refN = ngrams(r, n)
    const candN = ngrams(c, n)
    let match = 0
    let total = 0
    for (const [g, cnt] of candN.entries()) {
      total += cnt
      const rc = refN.get(g) || 0
      match += Math.min(rc, cnt)
    }
    const p = total === 0 ? 0 : match / total
    const smooth = (match + 1) / (total + 1)
    precisions.push(p > 0 ? p : smooth)
  }
  const logAvg = precisions.reduce((sum, p) => sum + Math.log(p), 0) / maxN
  const bp = c.length > r.length ? 1 : Math.exp(1 - r.length / c.length)
  return bp * Math.exp(logAvg)
}

function lcsLength(aTokens, bTokens) {
  const m = aTokens.length
  const n = bTokens.length
  if (!m || !n) return 0
  let prev = new Array(n + 1).fill(0)
  let cur = new Array(n + 1).fill(0)
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (aTokens[i - 1] === bTokens[j - 1]) cur[j] = prev[j - 1] + 1
      else cur[j] = Math.max(prev[j], cur[j - 1])
    }
    const tmp = prev
    prev = cur
    cur = tmp
  }
  return prev[n]
}

function rougeL(ref, cand) {
  const r = charTokens(ref)
  const c = charTokens(cand)
  if (!r.length || !c.length) return 0
  const lcs = lcsLength(r, c)
  const prec = lcs / c.length
  const rec = lcs / r.length
  if (prec + rec === 0) return 0
  return (2 * prec * rec) / (prec + rec)
}

function completenessScore(s) {
  const t = String(s || '').replace(/\s+/g, '')
  const chars = Array.from(t).filter((ch) => /[\p{L}\p{N}]/u.test(ch))
  return chars.length
}

async function getQingyanToken(baseUrl, apiKey, apiSecret) {
  const url = `${baseUrl.replace(/\/$/, '')}/get_token`
  const resp = await jsonPost(url, { api_key: apiKey, api_secret: apiSecret }, {}, 30000)
  if (!resp.data || typeof resp.data !== 'object') {
    throw new Error(`qingyan.get_token bad_json: HTTP ${resp.status}`)
  }
  if (Number(resp.data.status || 1) !== 0) {
    throw new Error(`qingyan.get_token rejected: ${String(resp.data.message || '') || 'unknown'}`)
  }
  const token = String(resp.data.result && resp.data.result.access_token ? resp.data.result.access_token : '')
  if (!token) throw new Error('qingyan.get_token missing access_token')
  return token
}

async function callQingyanOnce({ baseUrl, token, assistantId, prompt, conversationId }) {
  const url = `${baseUrl.replace(/\/$/, '')}/stream_sync`
  const payload = { assistant_id: assistantId, prompt }
  if (conversationId) payload.conversation_id = conversationId
  const resp = await jsonPost(
    url,
    payload,
    {
      Authorization: `Bearer ${token}`,
      'Accept-Encoding': 'identity',
    },
    60000
  )
  if (!resp.data || typeof resp.data !== 'object') {
    throw new Error(`qingyan.stream_sync bad_json: HTTP ${resp.status}`)
  }
  if (Number(resp.data.status || 1) !== 0) {
    throw new Error(`qingyan.stream_sync rejected: ${String(resp.data.message || '') || 'unknown'}`)
  }
  const result = resp.data.result && typeof resp.data.result === 'object' ? resp.data.result : {}
  const text = extractQingyanText(result)
  return { text: String(text || ''), conversation_id: String(result.conversation_id || '') }
}

async function callLocalOnce({ baseUrl, pathName, authHeaderName, authHeaderValue, agentId, message }) {
  const url = `${baseUrl.replace(/\/$/, '')}/${String(pathName || '').replace(/^\//, '')}`
  const headers = {}
  if (authHeaderName && authHeaderValue) headers[String(authHeaderName)] = String(authHeaderValue)
  const body = { agent_id: String(agentId), message: String(message), stream: 0 }
  const resp = await jsonPost(url, body, headers, 60000)
  if (!resp.data || typeof resp.data !== 'object') {
    throw new Error(`local.ai_chat bad_json: HTTP ${resp.status}`)
  }
  if (Number(resp.data.status || 0) !== 200) {
    throw new Error(`local.ai_chat rejected: ${String(resp.data.msg || resp.data.message || 'unknown')}`)
  }
  const data = resp.data.data && typeof resp.data.data === 'object' ? resp.data.data : {}
  const reply = String(data.reply || '')
  return { text: reply, session_id: Number(data.session_id || 0) || 0 }
}

function mean(nums) {
  if (!nums.length) return 0
  return nums.reduce((a, b) => a + b, 0) / nums.length
}

async function main() {
  const args = parseArgs(process.argv)
  const count = Number(args.n || args.count || getEnv('SAMPLE_N', '30')) || 30
  const question = String(args.q || args.question || getEnv('QUESTION', '') || args._.join(' ')).trim()
  if (!question) {
    console.error('Missing question. Use --q "你的问题" or set env QUESTION.')
    process.exit(2)
  }

  const qyBaseUrl = getEnv('QINGYAN_BASE_URL', 'https://chatglm.cn/chatglm/assistant-api/v1')
  const qyApiKey = getEnv('QINGYAN_API_KEY', '')
  const qyApiSecret = getEnv('QINGYAN_API_SECRET', '')
  const qyAssistantId = getEnv('QINGYAN_ASSISTANT_ID', '')

  const localBaseUrl = getEnv('LOCAL_BASE_URL', '')
  const localPath = getEnv('LOCAL_CHAT_PATH', 'ai/chat')
  const localAgentId = getEnv('LOCAL_AGENT_ID', '')
  const localAuthHeaderName = getEnv('LOCAL_AUTH_HEADER_NAME', 'Authori-zation')
  const localAuthHeaderValue = getEnv('LOCAL_AUTH_HEADER_VALUE', '')

  const outDir = String(args.out || getEnv('OUT_DIR', '')).trim()
  const reportDir = outDir || path.join(process.cwd(), 'scripts_out', `qingyan_align_${nowIsoCompact()}`)
  fs.mkdirSync(reportDir, { recursive: true })

  if (!qyApiKey || !qyApiSecret || !qyAssistantId) {
    throw new Error('Missing QINGYAN_API_KEY/QINGYAN_API_SECRET/QINGYAN_ASSISTANT_ID.')
  }
  if (!localBaseUrl || !localAgentId) {
    throw new Error('Missing LOCAL_BASE_URL/LOCAL_AGENT_ID.')
  }

  const token = await getQingyanToken(qyBaseUrl, qyApiKey, qyApiSecret)

  const pairs = []
  for (let i = 0; i < count; i++) {
    const qy = await callQingyanOnce({
      baseUrl: qyBaseUrl,
      token,
      assistantId: qyAssistantId,
      prompt: question,
      conversationId: '',
    })
    const local = await callLocalOnce({
      baseUrl: localBaseUrl,
      pathName: localPath,
      authHeaderName: localAuthHeaderName,
      authHeaderValue: localAuthHeaderValue,
      agentId: localAgentId,
      message: question,
    })
    pairs.push({ i: i + 1, qingyan: qy.text, local: local.text })
    process.stdout.write(`Sample ${i + 1}/${count}\r`)
  }
  process.stdout.write('\n')

  const bleuList = []
  const rougeList = []
  const completenessDiffList = []
  const detail = []
  for (const p of pairs) {
    const ref = normalizeForMetric(p.qingyan)
    const cand = normalizeForMetric(p.local)
    const bleu = bleuScore(ref, cand)
    const rouge = rougeL(ref, cand)
    const cRef = completenessScore(ref)
    const cCand = completenessScore(cand)
    const diff = Math.abs(cRef - cCand) / Math.max(1, cRef)
    bleuList.push(bleu)
    rougeList.push(rouge)
    completenessDiffList.push(diff)
    detail.push({ i: p.i, bleu, rougeL: rouge, completenessRef: cRef, completenessLocal: cCand, completenessDiff: diff })
  }

  const avgBleu = mean(bleuList)
  const avgRouge = mean(rougeList)
  const avgCompletenessDiff = mean(completenessDiffList)
  const pass = avgBleu >= 0.85 && avgRouge >= 0.85 && avgCompletenessDiff < 0.05

  const summary = {
    question,
    count,
    thresholds: { bleu: 0.85, rougeL: 0.85, completenessDiff: 0.05 },
    average: { bleu: avgBleu, rougeL: avgRouge, completenessDiff: avgCompletenessDiff },
    pass,
    meta: {
      qingyan: { baseUrl: qyBaseUrl, assistantId: qyAssistantId, mode: 'stream_sync', conversationId: 'empty' },
      local: { baseUrl: localBaseUrl, path: localPath, agentId: localAgentId, mode: 'stream=0', authHeaderName: localAuthHeaderName },
    },
  }

  fs.writeFileSync(path.join(reportDir, 'pairs.json'), JSON.stringify(pairs, null, 2), 'utf8')
  fs.writeFileSync(path.join(reportDir, 'metrics.json'), JSON.stringify({ summary, detail }, null, 2), 'utf8')

  const md = []
  md.push(`# 清言对齐评测报告`)
  md.push(``)
  md.push(`- 样本数：${count}`)
  md.push(`- 问题：${question}`)
  md.push(`- 平均 BLEU：${avgBleu.toFixed(4)}（阈值 0.85）`)
  md.push(`- 平均 ROUGE-L：${avgRouge.toFixed(4)}（阈值 0.85）`)
  md.push(`- 平均信息完整度差异：${(avgCompletenessDiff * 100).toFixed(2)}%（阈值 < 5%）`)
  md.push(`- 结论：${pass ? 'PASS' : 'FAIL'}`)
  md.push(``)
  md.push(`## 采集配置`)
  md.push(`- 清言：${qyBaseUrl} /stream_sync（assistant_id=${qyAssistantId}，conversation_id 不传）`)
  md.push(`- 本地：${localBaseUrl}/${localPath}（agent_id=${localAgentId}，stream=0）`)
  md.push(``)
  md.push(`## 产物`)
  md.push(`- pairs.json：30 组原始回答对`)
  md.push(`- metrics.json：逐条指标 + 汇总`)
  md.push(``)
  fs.writeFileSync(path.join(reportDir, 'report.md'), md.join('\n'), 'utf8')

  console.log(`Output: ${reportDir}`)
  console.log(JSON.stringify(summary, null, 2))
  process.exit(pass ? 0 : 1)
}

main().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err))
  process.exit(1)
})

