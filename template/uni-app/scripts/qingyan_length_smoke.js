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

async function jsonPost(url, body, headers = {}, timeoutMs = 60000) {
  const controller = new AbortController()
  const t = setTimeout(() => controller.abort(), timeoutMs)
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: Object.assign({ 'Content-Type': 'application/json', Accept: 'application/json' }, headers),
      body: JSON.stringify(body),
      signal: controller.signal,
    })
    const text = await res.text()
    let data = null
    try {
      data = JSON.parse(text)
    } catch (e) {
      data = null
    }
    return { ok: res.ok, status: res.status, text, data }
  } finally {
    clearTimeout(t)
  }
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

async function getQingyanToken(baseUrl, apiKey, apiSecret) {
  const url = `${baseUrl.replace(/\/$/, '')}/get_token`
  const resp = await jsonPost(url, { api_key: apiKey, api_secret: apiSecret }, {}, 30000)
  if (!resp.data || typeof resp.data !== 'object') throw new Error(`qingyan.get_token bad_json: HTTP ${resp.status}`)
  if (Number(resp.data.status || 1) !== 0) throw new Error(`qingyan.get_token rejected: ${String(resp.data.message || '') || 'unknown'}`)
  const token = String(resp.data.result && resp.data.result.access_token ? resp.data.result.access_token : '')
  if (!token) throw new Error('qingyan.get_token missing access_token')
  return token
}

async function callQingyanSync({ baseUrl, token, assistantId, prompt }) {
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

async function callLocalNonStream({ baseUrl, pathName, authHeaderName, authHeaderValue, agentId, message }) {
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

  const outDir = path.join(process.cwd(), 'scripts_out', `qingyan_len_${nowIsoCompact()}`)
  ensureDir(outDir)

  const token = await getQingyanToken(qyBaseUrl, qyApiKey, qyApiSecret)
  const qyText = await callQingyanSync({ baseUrl: qyBaseUrl, token, assistantId: qyAssistantId, prompt: question })
  const localText = await callLocalNonStream({
    baseUrl: localBaseUrl,
    pathName: localPath,
    authHeaderName: localAuthHeaderName,
    authHeaderValue: localAuthHeaderValue,
    agentId: localAgentId,
    message: question,
  })

  const qLen = Array.from(qyText).length
  const lLen = Array.from(localText).length
  const ratio = qLen > 0 ? lLen / qLen : 0
  const threshold = Number(args.threshold || getEnv('LEN_RATIO_THRESHOLD', '0.85')) || 0.85
  const pass = ratio >= threshold

  const report = []
  report.push('# 清言长度对齐冒烟测试')
  report.push('')
  report.push(`- threshold: ${threshold}`)
  report.push(`- qingyan_len: ${qLen}`)
  report.push(`- local_len: ${lLen}`)
  report.push(`- ratio(local/qingyan): ${ratio.toFixed(4)}`)
  report.push(`- result: ${pass ? 'PASS' : 'FAIL'}`)
  report.push('')
  fs.writeFileSync(path.join(outDir, 'report.md'), report.join('\n'), 'utf8')
  console.log(`Output: ${outDir}${path.sep}report.md`)
  process.exit(pass ? 0 : 1)
}

main().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err))
  process.exit(1)
})

