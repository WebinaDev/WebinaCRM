/**
 * WebinoCRM realtime WebSocket server (run beside WordPress).
 * Env: PORT (8080), WS_SECRET (must match WordPress webinocrm_ws_secret)
 */
import { createServer } from "http"
import crypto from "crypto"
import { WebSocketServer } from "ws"

const PORT = Number(process.env.PORT || 8080)
const SECRET = process.env.WS_SECRET || "change-me-in-production"

/** @type {Map<number, Set<import('ws').WebSocket>>} */
const userSockets = new Map()

function verifyToken(token) {
  if (!token || typeof token !== "string") return null
  const parts = token.split(".")
  if (parts.length !== 3) return null
  const [userId, exp, sig] = parts
  const payload = `${userId}.${exp}`
  const expected = crypto.createHmac("sha256", SECRET).update(payload).digest("hex")
  if (sig !== expected) return null
  if (Date.now() > Number(exp) * 1000) return null
  const uid = Number(userId)
  return Number.isFinite(uid) && uid > 0 ? uid : null
}

function addSocket(userId, ws) {
  if (!userSockets.has(userId)) userSockets.set(userId, new Set())
  userSockets.get(userId).add(ws)
}

function removeSocket(userId, ws) {
  const set = userSockets.get(userId)
  if (!set) return
  set.delete(ws)
  if (set.size === 0) userSockets.delete(userId)
}

function broadcastToUsers(userIds, payload) {
  const data = JSON.stringify(payload)
  for (const uid of userIds) {
    const set = userSockets.get(Number(uid))
    if (!set) continue
    for (const ws of set) {
      if (ws.readyState === 1) ws.send(data)
    }
  }
}

const httpServer = createServer((req, res) => {
  if (req.method === "POST" && req.url === "/broadcast") {
    let body = ""
    req.on("data", (chunk) => { body += chunk })
    req.on("end", () => {
      const auth = req.headers["x-ws-secret"]
      if (auth !== SECRET) {
        res.writeHead(403)
        res.end("forbidden")
        return
      }
      try {
        const parsed = JSON.parse(body)
        const userIds = parsed.user_ids || []
        broadcastToUsers(userIds, parsed.data || {})
        res.writeHead(200, { "Content-Type": "application/json" })
        res.end(JSON.stringify({ ok: true }))
      } catch {
        res.writeHead(400)
        res.end("bad request")
      }
    })
    return
  }
  res.writeHead(200)
  res.end("webinocrm realtime ok")
})

const wss = new WebSocketServer({ server: httpServer })

wss.on("connection", (ws, req) => {
  const url = new URL(req.url || "/", `http://${req.headers.host}`)
  const token = url.searchParams.get("token")
  const userId = verifyToken(token)
  if (!userId) {
    ws.close(4001, "unauthorized")
    return
  }
  addSocket(userId, ws)
  ws.send(JSON.stringify({ type: "connected", user_id: userId }))

  ws.on("close", () => removeSocket(userId, ws))
  ws.on("error", () => removeSocket(userId, ws))
})

httpServer.listen(PORT, () => {
  console.log(`[webinocrm-realtime] listening on :${PORT}`)
})
