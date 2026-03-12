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

async function jsonGet(url, headers = {}, timeoutMs = 60000) {
  const controller = new AbortController()
  const t = setTimeout(() => controller.abort(), timeoutMs)
  try {
    const res = await fetch(url, {
      method: 'GET',
      headers: Object.assign({ Accept: 'application/json' }, headers),
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

async function main() {
  const args = parseArgs(process.argv)
  const question = String(args.q || getEnv('QUESTION', '你好')).trim()

  const baseUrl = getEnv('LOCAL_BASE_URL', '')
  const authHeaderName = getEnv('LOCAL_AUTH_HEADER_NAME', 'Authori-zation')
  const authHeaderValue = getEnv('LOCAL_AUTH_HEADER_VALUE', '')
  const agentId = String(getEnv('LOCAL_AGENT_ID', '')).trim()
  if (!baseUrl || !authHeaderValue || !agentId) {
    throw new Error('Missing LOCAL_BASE_URL/LOCAL_AUTH_HEADER_VALUE/LOCAL_AGENT_ID.')
  }

  const outDir = path.join(process.cwd(), 'scripts_out', `clear_${nowIsoCompact()}`)
  ensureDir(outDir)

  const headers = { [authHeaderName]: authHeaderValue }
  const chatUrl = `${baseUrl.replace(/\/$/, '')}/ai/chat`
  const recentUrl = `${baseUrl.replace(/\/$/, '')}/ai/session/recent?agent_id=${encodeURIComponent(agentId)}`
  const histUrl = (sid) => `${baseUrl.replace(/\/$/, '')}/ai/chat/history?session_id=${encodeURIComponent(String(sid))}&page=1&limit=50`
  const clearUrl = `${baseUrl.replace(/\/$/, '')}/ai/chat/clear`

  const r0 = await jsonGet(recentUrl, headers)
  const beforeRecent = r0.data && r0.data.data ? r0.data.data : null

  const chat = await jsonPost(chatUrl, { agent_id: agentId, message: question, stream: 0 }, headers)
  if (!chat.data || typeof chat.data !== 'object' || Number(chat.data.status || 0) !== 200) {
    throw new Error(`chat failed: ${String(chat.text).slice(0, 200)}`)
  }
  const sessionId = Number((chat.data.data && chat.data.data.session_id) || 0) || 0
  if (!sessionId) throw new Error('chat did not return session_id')

  const h0 = await jsonGet(histUrl(sessionId), headers)
  const beforeCount = Array.isArray(h0.data && h0.data.data ? h0.data.data : null) ? h0.data.data.length : -1

  const cleared = await jsonPost(clearUrl, { session_id: sessionId, agent_id: Number(agentId) || 0 }, headers)
  if (!cleared.data || typeof cleared.data !== 'object' || Number(cleared.data.status || 0) !== 200) {
    throw new Error(`clear failed: ${String(cleared.text).slice(0, 200)}`)
  }

  const h1 = await jsonGet(histUrl(sessionId), headers)
  const afterCount = Array.isArray(h1.data && h1.data.data ? h1.data.data : null) ? h1.data.data.length : -1

  const r1 = await jsonGet(recentUrl, headers)
  const afterRecent = r1.data && r1.data.data ? r1.data.data : null

  const report = []
  report.push('# 聊天清除回归报告')
  report.push('')
  report.push(`- agent_id: ${agentId}`)
  report.push(`- session_id: ${sessionId}`)
  report.push(`- history_count_before: ${beforeCount}`)
  report.push(`- history_count_after: ${afterCount}`)
  report.push(`- recent_before: ${beforeRecent && beforeRecent.id ? String(beforeRecent.id) : 'none'}`)
  report.push(`- recent_after: ${afterRecent && afterRecent.id ? String(afterRecent.id) : 'none'}`)
  report.push('')
  fs.writeFileSync(path.join(outDir, 'report.md'), report.join('\n'), 'utf8')

  const pass = afterCount === 0 || afterCount === -1
  console.log(`Output: ${outDir}${path.sep}report.md`)
  process.exit(pass ? 0 : 1)
}

main().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err))
  process.exit(1)
})

