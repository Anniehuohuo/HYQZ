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
  const out = {}
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i]
    if (!a) continue
    if (!a.startsWith('--')) continue
    const eq = a.indexOf('=')
    if (eq > -1) out[a.slice(2, eq)] = a.slice(eq + 1)
    else out[a.slice(2)] = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : 'true'
  }
  return out
}

function ensureDir(p) {
  fs.mkdirSync(p, { recursive: true })
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

function normalize(s) {
  return String(s || '').replace(/\r\n/g, '\n').replace(/[ \t]+/g, ' ').trim()
}

function charTokens(s) {
  const t = normalize(s)
  return t ? Array.from(t) : []
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

function bleu(ref, cand, maxN = 4) {
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
      match += Math.min(refN.get(g) || 0, cnt)
    }
    const p = total === 0 ? 0 : match / total
    const smooth = (match + 1) / (total + 1)
    precisions.push(p > 0 ? p : smooth)
  }
  const logAvg = precisions.reduce((sum, p) => sum + Math.log(p), 0) / maxN
  const bp = c.length > r.length ? 1 : Math.exp(1 - r.length / c.length)
  return bp * Math.exp(logAvg)
}

function lcsLength(a, b) {
  const m = a.length
  const n = b.length
  if (!m || !n) return 0
  let prev = new Array(n + 1).fill(0)
  let cur = new Array(n + 1).fill(0)
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (a[i - 1] === b[j - 1]) cur[j] = prev[j - 1] + 1
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

function keywordHit(s) {
  const t = String(s || '')
  const keys = ['场景练习', '进入下一个场景', '下一个场景', '我们进入下一个场景']
  const hits = keys.filter((k) => t.includes(k))
  return { hits, ok: hits.length > 0 }
}

function firstDiffSnippet(a, b, win = 80) {
  const x = normalize(a)
  const y = normalize(b)
  const n = Math.min(x.length, y.length)
  let idx = 0
  while (idx < n && x[idx] === y[idx]) idx++
  if (idx === n && x.length === y.length) return { idx: -1, a: '', b: '' }
  const start = Math.max(0, idx - win)
  const endA = Math.min(x.length, idx + win)
  const endB = Math.min(y.length, idx + win)
  return { idx, a: x.slice(start, endA), b: y.slice(start, endB) }
}

async function getQingyanToken(baseUrl, apiKey, apiSecret) {
  const url = `${baseUrl.replace(/\/$/, '')}/get_token`
  const resp = await jsonPost(url, { api_key: apiKey, api_secret: apiSecret }, {}, 30000)
  if (!resp.data || typeof resp.data !== 'object') throw new Error(`qingyan.get_token bad_json: HTTP ${resp.status}`)
  if (Number(resp.data.status || 1) !== 0) throw new Error(`qingyan.get_token rejected: ${String(resp.data.message || '') || 'unknown'}`)
  const token = String(resp.data.result && resp.data.result.access_token ? resp.data.result.access_token : '')
  if (!token) throw new Error('qingyan.get_token missing access_token')
  return token
}

async function callQingyanOnce({ baseUrl, token, assistantId, prompt }) {
  const url = `${baseUrl.replace(/\/$/, '')}/stream_sync`
  const resp = await jsonPost(
    url,
    { assistant_id: assistantId, prompt },
    { Authorization: `Bearer ${token}`, 'Accept-Encoding': 'identity' },
    60000
  )
  if (!resp.data || typeof resp.data !== 'object') throw new Error(`qingyan.stream_sync bad_json: HTTP ${resp.status}`)
  if (Number(resp.data.status || 1) !== 0) throw new Error(`qingyan.stream_sync rejected: ${String(resp.data.message || '') || 'unknown'}`)
  const result = resp.data.result && typeof resp.data.result === 'object' ? resp.data.result : {}
  return String(extractQingyanText(result) || '')
}

async function callLocalOnce({ baseUrl, pathName, authHeaderName, authHeaderValue, agentId, message }) {
  const url = `${baseUrl.replace(/\/$/, '')}/${String(pathName || '').replace(/^\//, '')}`
  const headers = {}
  if (authHeaderName && authHeaderValue) headers[String(authHeaderName)] = String(authHeaderValue)
  const resp = await jsonPost(url, { agent_id: String(agentId), message: String(message), stream: 0 }, headers, 60000)
  if (!resp.data || typeof resp.data !== 'object') throw new Error(`local.ai_chat bad_json: HTTP ${resp.status}`)
  if (Number(resp.data.status || 0) !== 200) throw new Error(`local.ai_chat rejected: ${String(resp.data.msg || resp.data.message || 'unknown')}`)
  const data = resp.data.data && typeof resp.data.data === 'object' ? resp.data.data : {}
  return String(data.reply || '')
}

async function main() {
  const args = parseArgs(process.argv)
  const question = String(args.q || getEnv('QUESTION', '')).trim()
  if (!question) throw new Error('Missing question. Use --q "..." or set env QUESTION.')

  const qyBaseUrl = getEnv('QINGYAN_BASE_URL', 'https://chatglm.cn/chatglm/assistant-api/v1')
  const qyApiKey = getEnv('QINGYAN_API_KEY', '')
  const qyApiSecret = getEnv('QINGYAN_API_SECRET', '')
  const qyAssistantId = getEnv('QINGYAN_ASSISTANT_ID', '')

  const localBaseUrl = getEnv('LOCAL_BASE_URL', '')
  const localPath = getEnv('LOCAL_CHAT_PATH', 'ai/chat')
  const localAgentId = getEnv('LOCAL_AGENT_ID', '')
  const localAuthHeaderName = getEnv('LOCAL_AUTH_HEADER_NAME', 'Authori-zation')
  const localAuthHeaderValue = getEnv('LOCAL_AUTH_HEADER_VALUE', '')

  if (!qyApiKey || !qyApiSecret || !qyAssistantId) throw new Error('Missing QINGYAN_API_KEY/QINGYAN_API_SECRET/QINGYAN_ASSISTANT_ID.')
  if (!localBaseUrl || !localAgentId) throw new Error('Missing LOCAL_BASE_URL/LOCAL_AGENT_ID.')

  const outDir = path.join(process.cwd(), 'scripts_out', `scene_practice_${nowIsoCompact()}`)
  ensureDir(outDir)

  const token = await getQingyanToken(qyBaseUrl, qyApiKey, qyApiSecret)
  const qyText = await callQingyanOnce({ baseUrl: qyBaseUrl, token, assistantId: qyAssistantId, prompt: question })
  const localText = await callLocalOnce({
    baseUrl: localBaseUrl,
    pathName: localPath,
    authHeaderName: localAuthHeaderName,
    authHeaderValue: localAuthHeaderValue,
    agentId: localAgentId,
    message: question,
  })

  const b = bleu(qyText, localText)
  const r = rougeL(qyText, localText)
  const cRef = completenessScore(qyText)
  const cLoc = completenessScore(localText)
  const cDiff = Math.abs(cRef - cLoc) / Math.max(1, cRef)
  const kRef = keywordHit(qyText)
  const kLoc = keywordHit(localText)
  const diff = firstDiffSnippet(qyText, localText)

  const report = []
  report.push('# 3-8岁表扬技巧：场景练习模块一致性报告')
  report.push('')
  report.push(`- BLEU: ${b.toFixed(4)}`)
  report.push(`- ROUGE-L: ${r.toFixed(4)}`)
  report.push(`- 完整度差异: ${(cDiff * 100).toFixed(2)}%`)
  report.push(`- 清言命中关键词: ${kRef.ok ? kRef.hits.join(' / ') : '无'}`)
  report.push(`- 本系统命中关键词: ${kLoc.ok ? kLoc.hits.join(' / ') : '无'}`)
  report.push('')
  report.push('## 首个差异片段（用于快速定位是否尾段缺失）')
  report.push('')
  report.push(`- diff_index: ${diff.idx}`)
  report.push('')
  report.push('### 清言片段')
  report.push('```text')
  report.push(diff.a)
  report.push('```')
  report.push('')
  report.push('### 本系统片段')
  report.push('```text')
  report.push(diff.b)
  report.push('```')
  report.push('')
  report.push('## 全量输出')
  report.push('')
  report.push('### 清言输出')
  report.push('```text')
  report.push(qyText)
  report.push('```')
  report.push('')
  report.push('### 本系统输出')
  report.push('```text')
  report.push(localText)
  report.push('```')
  report.push('')

  fs.writeFileSync(path.join(outDir, 'scene_practice_diff_report.md'), report.join('\n'), 'utf8')
  fs.writeFileSync(path.join(outDir, 'qingyan.txt'), qyText, 'utf8')
  fs.writeFileSync(path.join(outDir, 'local.txt'), localText, 'utf8')

  const ok = kRef.ok ? kLoc.ok : true
  console.log(`Output: ${outDir}${path.sep}scene_practice_diff_report.md`)
  console.log(JSON.stringify({ bleu: b, rougeL: r, completenessDiff: cDiff, qingyanHasPractice: kRef.ok, localHasPractice: kLoc.ok }, null, 2))
  process.exit(ok ? 0 : 1)
}

main().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err))
  process.exit(1)
})

