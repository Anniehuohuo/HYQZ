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

function isLeakage(s) {
  const t = String(s || '')
  const patterns = ['可用工具', 'simple_browser', 'msearch(', 'mclick(', 'open_url(', 'tool_calls', 'tools:']
  for (const p of patterns) {
    if (t.toLowerCase().includes(String(p).toLowerCase())) return true
  }
  return /\b(msearch|mclick|open_url)\s*\(/i.test(t)
}

async function postJson(url, body, headers = {}, timeoutMs = 60000) {
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

async function postSse(url, body, headers = {}, timeoutMs = 60000) {
  const controller = new AbortController()
  const t = setTimeout(() => controller.abort(), timeoutMs)
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: Object.assign({ 'Content-Type': 'application/json', Accept: 'text/event-stream' }, headers),
      body: JSON.stringify(body),
      signal: controller.signal,
    })
    if (!res.ok || !res.body) {
      const text = await res.text().catch(() => '')
      throw new Error(`SSE http_failed: ${res.status} ${text.slice(0, 200)}`)
    }
    const reader = res.body.getReader()
    const decoder = new TextDecoder()
    let buf = ''
    let content = ''
    let doneSeen = false
    let sessionId = 0
    let firstPayload = null

    while (true) {
      const { done, value } = await reader.read()
      if (done) break
      buf += decoder.decode(value, { stream: true })
      const lines = buf.split(/\r?\n/)
      buf = lines.pop() || ''
      for (const raw of lines) {
        const line = String(raw || '').trim()
        if (!line.startsWith('data:')) continue
        const dataStr = line.replace(/^data:\s*/, '')
        if (dataStr === '[DONE]') {
          doneSeen = true
          continue
        }
        let payload = null
        try {
          payload = JSON.parse(dataStr)
        } catch (e) {
          payload = null
        }
        if (!payload || typeof payload !== 'object') continue
        if (!firstPayload) firstPayload = payload
        if (payload.session_id) sessionId = Number(payload.session_id) || sessionId
        if (payload.error) throw new Error(`SSE error: ${String(payload.error)}`)
        if (payload.content) content += String(payload.content)
      }
      if (content.length >= 600) break
    }
    return { content, doneSeen, sessionId, firstPayload }
  } finally {
    clearTimeout(t)
  }
}

async function main() {
  const args = parseArgs(process.argv)
  const question = String(args.q || getEnv('QUESTION', '')).trim()
  if (!question) throw new Error('Missing question. Use --q "..." or set env QUESTION.')

  const localBaseUrl = getEnv('LOCAL_BASE_URL', '')
  const localPath = getEnv('LOCAL_CHAT_PATH', 'ai/chat')
  const localAgentId = getEnv('LOCAL_AGENT_ID', '')
  const authHeaderName = getEnv('LOCAL_AUTH_HEADER_NAME', 'Authori-zation')
  const authHeaderValue = getEnv('LOCAL_AUTH_HEADER_VALUE', '')
  if (!localBaseUrl || !localAgentId || !authHeaderValue) {
    throw new Error('Missing LOCAL_BASE_URL/LOCAL_AGENT_ID/LOCAL_AUTH_HEADER_VALUE.')
  }

  const outDir = path.join(process.cwd(), 'scripts_out', `contract_${nowIsoCompact()}`)
  ensureDir(outDir)

  const url = `${localBaseUrl.replace(/\/$/, '')}/${String(localPath).replace(/^\//, '')}`
  const headers = { [authHeaderName]: authHeaderValue }

  const nonStream = await postJson(url, { agent_id: String(localAgentId), message: question, stream: 0 }, headers, 60000)
  if (!nonStream.data || typeof nonStream.data !== 'object' || Number(nonStream.data.status || 0) !== 200) {
    throw new Error(`Non-stream failed: ${String(nonStream.text).slice(0, 200)}`)
  }
  const reply = String((nonStream.data.data && nonStream.data.data.reply) || '')
  const nonStreamLeak = isLeakage(reply)

  const sse = await postSse(url, { agent_id: String(localAgentId), message: question, stream: true }, headers, 60000)
  const sseLeak = isLeakage(sse.content) || isLeakage(JSON.stringify(sse.firstPayload || {}))

  const report = []
  report.push('# AI Chat 契约测试报告')
  report.push('')
  report.push(`- question: ${question}`)
  report.push(`- non_stream_len: ${reply.length}`)
  report.push(`- non_stream_leakage: ${nonStreamLeak ? 'YES' : 'NO'}`)
  report.push(`- sse_first_600_len: ${sse.content.length}`)
  report.push(`- sse_done_seen: ${sse.doneSeen ? 'YES' : 'NO'}`)
  report.push(`- sse_leakage: ${sseLeak ? 'YES' : 'NO'}`)
  report.push('')
  report.push('## non-stream reply')
  report.push('```text')
  report.push(reply)
  report.push('```')
  report.push('')
  report.push('## sse first 600 chars')
  report.push('```text')
  report.push(sse.content)
  report.push('```')
  report.push('')
  fs.writeFileSync(path.join(outDir, 'ai_chat_contract_report.md'), report.join('\n'), 'utf8')

  const pass = !nonStreamLeak && !sseLeak && sse.content.length > 0
  console.log(`Output: ${outDir}${path.sep}ai_chat_contract_report.md`)
  process.exit(pass ? 0 : 1)
}

main().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err))
  process.exit(1)
})

