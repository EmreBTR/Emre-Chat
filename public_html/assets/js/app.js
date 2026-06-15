function chatApp() {
  const api = (path) => path

  const fetchJson = async (path, opts = {}) => {
    const res = await fetch(api(path), {
      credentials: "same-origin",
      ...opts,
    })
    const ct = res.headers.get("content-type") || ""
    const data = ct.includes("application/json") ? await res.json() : { ok: false, error: "bad_response" }
    if (!res.ok) {
      return { ok: false, error: data.error || "http_" + res.status, data }
    }
    return data
  }

  const nowLabel = () =>
    new Date().toLocaleTimeString("tr-TR", {
      hour: "2-digit",
      minute: "2-digit",
    })

  const ensurePresenceKey = () => {
    const key = "ec_presence_key_v1"
    const existing = localStorage.getItem(key)
    if (existing) return existing
    const v = globalThis.crypto && crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + "_" + String(Math.random()).slice(2)
    localStorage.setItem(key, v)
    return v
  }

  const parseFirstLink = (text) => {
    const m = String(text || "").match(/https?:\/\/[^\s]+/i)
    if (!m) return null
    try {
      const url = new URL(m[0])
      return { url: url.toString(), host: url.host }
    } catch {
      return null
    }
  }

  const extractReply = (text) => {
    const s = String(text || "")
    const m = s.match(/^\[\[reply:(\d+):([^:]*):([^\]]*)\]\]\n/)
    if (!m) return { cleanText: s, reply: null }
    const cleanText = s.slice(m[0].length)
    const sender = decodeURIComponent(m[2] || "")
    const t = decodeURIComponent(m[3] || "")
    return { cleanText, reply: { id: Number(m[1] || 0), sender, text: t } }
  }

  const accentMap = {
    mint: { a: "16 185 129", b: "99 102 241" },
    violet: { a: "99 102 241", b: "236 72 153" },
    amber: { a: "245 158 11", b: "34 211 238" },
    rose: { a: "244 63 94", b: "16 185 129" },
  }

  return {
    rail: "public",
    activeTab: "public",
    search: "",
    groups: [{ id: 0, name: "Global", last: "", time: "" }],
    activeGroupId: 0,

    messages: [],
    lastId: 0,
    es: null,
    connection: "idle",

    presenceKey: ensurePresenceKey(),
    presenceCount: 0,

    typingPeers: {},
    typingLabel: "",

    membersOpen: true,
    draft: "",
    sending: false,
    replyTo: null,

    emojiOpen: false,
    emojis: ["😀", "😄", "😅", "😂", "🥹", "😍", "😎", "🤝", "🔥", "✨", "⚡", "💡", "✅", "🫡", "🎯", "💬", "❤️", "💜", "💚", "🧠", "🧩", "🛰️", "🧊", "🌙", "☀️", "🌊", "🍀", "🪄", "🫶", "👀", "🙏", "🤍"],

    drawerOpen: false,
    settingsTab: "profile",
    settings: {
      theme: "dark_blue",
      fontScale: 1.0,
      wallpaper: "mesh",
      sounds: true,
      compact: false,
      reduceMotion: false,
      accent: "mint",
    },

    user: null,
    csrf: "",

    authModalOpen: false,
    authMode: "login",
    authBusy: false,
    authError: "",
    authForm: {
      username: "",
      identifier: "",
      email: "",
      password: "",
    },

    typingCooldownUntil: 0,
    typingSendTimer: null,
    lastAckAt: 0,

    get activeGroup() {
      return this.groups.find((g) => g.id === this.activeGroupId) || null
    },

    get filteredGroups() {
      const q = (this.search || "").trim().toLowerCase()
      if (!q) return this.groups
      return this.groups.filter((g) => (g.name || "").toLowerCase().includes(q))
    },

    get userLabel() {
      const u = this.user || null
      if (!u) return "Bağlanıyor…"
      if (u.is_guest) return u.guest_name ? u.guest_name : "Misafir"
      return "@" + (u.username || "user")
    },

    get connectionLabel() {
      if (this.connection === "open") return "Bağlı"
      if (this.connection === "error") return "Bağlantı sorunu (yeniden deneniyor)"
      if (this.connection === "connecting") return "Bağlanıyor…"
      return "Hazır"
    },

    get wallpaperClass() {
      if (this.settings.wallpaper === "dots") return "wallpaper-dots"
      if (this.settings.wallpaper === "grid") return "wallpaper-grid"
      if (this.settings.wallpaper === "waves") return "wallpaper-waves"
      return ""
    },

    get memberList() {
      const seen = new Map()
      for (let i = this.messages.length - 1; i >= 0; i--) {
        const m = this.messages[i]
        const key = m.is_guest_sender === 1 ? "g:" + (m.guest_name || "") : "u:" + (m.sender_username || m.sender_id || "")
        const label = this.senderLabel(m)
        if (!key || !label) continue
        if (!seen.has(key)) {
          seen.set(key, { key, label, kind: m.is_guest_sender === 1 ? "Misafir" : "Üye" })
        }
        if (seen.size >= 18) break
      }
      return Array.from(seen.values())
    },

    init() {
      this.applySettings()
      this.bootstrap()
      window.addEventListener("focus", () => this.maybeAckRead())
    },

    async bootstrap() {
      this.connection = "connecting"
      const me = await fetchJson("/api/auth.php?action=me")
      if (me.ok) {
        this.user = me.user
        this.csrf = me.csrf || ""
      }
      if (this.user && this.user.is_guest && !this.user.guest_name) {
        await this.ensureGuestIdentity()
      }
      const st = await fetchJson("/api/settings.php")
      if (st.ok && st.settings) {
        this.settings = { ...this.settings, ...st.settings }
        this.applySettings()
      }
      this.selectGroup(0)
    },

    selectGroup(id) {
      this.activeGroupId = id
      this.messages = []
      this.lastId = 0
      this.presenceCount = 0
      this.typingPeers = {}
      this.typingLabel = ""
      this.stopStream()
      this.startStream()
    },

    stopStream() {
      if (this.es) {
        this.es.close()
        this.es = null
      }
    },

    startStream() {
      const url = `/api/stream.php?scope=group&group_id=${encodeURIComponent(String(this.activeGroupId))}&last_id=${encodeURIComponent(
        String(this.lastId)
      )}&pk=${encodeURIComponent(this.presenceKey)}`

      this.connection = "connecting"
      const es = new EventSource(url, { withCredentials: true })
      this.es = es

      es.addEventListener("open", () => {
        this.connection = "open"
      })

      es.addEventListener("presence", (ev) => {
        try {
          const data = JSON.parse(ev.data)
          this.presenceCount = Number(data.count || 0)
        } catch {}
      })

      es.addEventListener("typing", (ev) => {
        try {
          const data = JSON.parse(ev.data)
          this.onTyping(data)
        } catch {}
      })

      es.addEventListener("message", (ev) => {
        this.connection = "open"
        const data = JSON.parse(ev.data)
        this.upsertMessage(data)
      })

      es.addEventListener("error", () => {
        this.connection = "error"
      })
    },

    decorateMessage(raw) {
      const { cleanText, reply } = extractReply(raw.message_text || "")
      const link = parseFirstLink(cleanText)
      return { ...raw, message_text: cleanText, _replyTo: reply, _link: link }
    },

    upsertMessage(raw) {
      const id = Number(raw.id || 0)
      if (!id) return
      if (id > this.lastId) this.lastId = id

      const m = this.decorateMessage(raw)
      const idx = this.messages.findIndex((x) => Number(x.id) === id)
      if (idx >= 0) {
        this.messages[idx] = { ...this.messages[idx], ...m }
      } else {
        this.messages.push(m)
      }

      const g = this.activeGroup
      if (g) {
        g.last = String(m.message_text || "")
        g.time = nowLabel()
      }

      this.$nextTick(() => {
        this.scrollToBottomIfNear()
        this.maybeAckDelivered(m)
        this.maybeAckRead()
      })

      if (!this.isMine(m) && this.settings.sounds) {
        this.playSound("in")
      }
    },

    scrollToBottom() {
      const el = this.$refs.scroller
      if (!el) return
      el.scrollTop = el.scrollHeight
    },

    scrollToBottomIfNear() {
      const el = this.$refs.scroller
      if (!el) return
      const near = el.scrollHeight - el.scrollTop - el.clientHeight < 120
      if (near) el.scrollTop = el.scrollHeight
    },

    isMine(m) {
      const u = this.user || null
      if (!u) return false
      if (u.is_guest) {
        return !!u.guest_name && m.is_guest_sender === 1 && m.guest_name === u.guest_name
      }
      return m.sender_id && Number(m.sender_id) === Number(u.user_id)
    },

    senderLabel(m) {
      if (m.is_guest_sender === 1) return m.guest_name || "Misafir"
      if (m.sender_username) return "@" + String(m.sender_username)
      if (m.sender_id) return "@user"
      return "Sistem"
    },

    avatarGlyph(m) {
      const s = this.senderLabel(m).replace("@", "").trim()
      return s ? s.slice(0, 1).toUpperCase() : "?"
    },

    bubbleClass(m) {
      const mine = this.isMine(m)
      const mentioned = this.isMentioned(m)
      const parts = []
      parts.push(mine ? "bubble-me" : "bubble-them")
      if (mentioned) parts.push("bubble-mention")
      return parts.join(" ")
    },

    isMentioned(m) {
      const u = this.user || null
      if (!u) return false
      const t = String(m.message_text || "")
      if (u.is_guest && u.guest_name) return t.includes(u.guest_name)
      if (!u.is_guest && u.username) return t.toLowerCase().includes("@" + String(u.username).toLowerCase())
      return false
    },

    formatTs(ts) {
      if (!ts) return ""
      return String(ts).slice(11, 16)
    },

    statusGlyph(status) {
      if (status === "read") return "✓✓"
      if (status === "delivered") return "✓✓"
      return "✓"
    },

    setReply(m) {
      this.replyTo = m
      this.$nextTick(() => {
        const el = this.$el.querySelector('textarea')
        if (el) el.focus()
      })
    },

    async copyMessage(m) {
      const text = String(m.message_text || "")
      try {
        await navigator.clipboard.writeText(text)
      } catch {}
    },

    insertEmoji(e) {
      this.draft = (this.draft || "") + e
      this.emojiOpen = false
      this.$nextTick(() => {
        const el = this.$el.querySelector('textarea')
        if (el) el.focus()
      })
      this.onDraft()
    },

    async ensureGuestIdentity() {
      const u = this.user || null
      if (u && u.is_guest && u.guest_name) return true
      const r = await fetchJson("/api/auth.php", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ action: "guest" }),
      })
      if (!r.ok) return false
      this.user = r.user
      this.csrf = r.csrf || this.csrf
      return true
    },

    onDraft() {
      const now = Date.now()
      if (now < this.typingCooldownUntil) return
      this.typingCooldownUntil = now + 900

      if (this.typingSendTimer) {
        clearTimeout(this.typingSendTimer)
      }
      this.typingSendTimer = setTimeout(() => {
        this.sendTyping()
      }, 250)
    },

    async sendTyping() {
      if (!this.csrf) return
      if (!this.draft || String(this.draft).trim() === "") return
      await this.ensureGuestIdentity()
      await fetchJson("/api/send.php", {
        method: "POST",
        headers: {
          "content-type": "application/json",
          "X-CSRF-Token": this.csrf,
        },
        body: JSON.stringify({
          action: "typing",
          target: { type: "group", groupId: this.activeGroupId },
        }),
      })
    },

    onTyping(ev) {
      const label = ev.is_guest === 1 ? ev.guest_name || "Misafir" : ev.user_id ? "@user" : "Üye"
      const key = ev.is_guest === 1 ? "g:" + String(ev.guest_name || "") : "u:" + String(ev.user_id || "")
      if (!key || key === "g:" || key === "u:0") return

      this.typingPeers[key] = { label, until: Date.now() + 3500 }
      this.refreshTypingLabel()
    },

    refreshTypingLabel() {
      const now = Date.now()
      const entries = Object.entries(this.typingPeers).filter(([, v]) => v && v.until > now)
      if (entries.length === 0) {
        this.typingPeers = {}
        this.typingLabel = ""
        return
      }
      const labels = entries.slice(0, 2).map(([, v]) => v.label)
      this.typingLabel = labels.join(", ") + " yazıyor…"
      setTimeout(() => this.refreshTypingLabel(), 600)
    },

    async send() {
      const text = (this.draft || "").trim()
      if (!text) return
      if (this.sending) return

      this.sending = true
      try {
        const okGuest = await this.ensureGuestIdentity()
        if (!okGuest) return

        let payloadText = text
        if (this.replyTo) {
          const sender = encodeURIComponent(this.senderLabel(this.replyTo))
          const snippet = encodeURIComponent(String(this.replyTo.message_text || "").slice(0, 140))
          payloadText = `[[reply:${Number(this.replyTo.id || 0)}:${sender}:${snippet}]]\n` + payloadText
        }

        const r = await fetchJson("/api/send.php", {
          method: "POST",
          headers: {
            "content-type": "application/json",
            "X-CSRF-Token": this.csrf,
          },
          body: JSON.stringify({
            target: { type: "group", groupId: this.activeGroupId },
            text: payloadText,
          }),
        })

        if (r.ok && r.message) {
          this.upsertMessage(r.message)
          if (this.settings.sounds) {
            this.playSound("out")
          }
        }
        this.draft = ""
        this.replyTo = null
      } finally {
        this.sending = false
      }
    },

    async maybeAckDelivered(m) {
      if (this.isMine(m)) return
      if (m.status && m.status !== "sent") return
      const now = Date.now()
      if (now - this.lastAckAt < 500) return
      this.lastAckAt = now
      await fetchJson("/api/send.php", {
        method: "POST",
        headers: {
          "content-type": "application/json",
          "X-CSRF-Token": this.csrf,
        },
        body: JSON.stringify({ action: "ack", messageId: Number(m.id), status: "delivered" }),
      })
    },

    async maybeAckRead() {
      const el = this.$refs.scroller
      if (!el) return
      const near = el.scrollHeight - el.scrollTop - el.clientHeight < 90
      if (!near) return
      const last = this.messages[this.messages.length - 1]
      if (!last) return
      if (this.isMine(last)) return
      if (last.status === "read") return
      await fetchJson("/api/send.php", {
        method: "POST",
        headers: {
          "content-type": "application/json",
          "X-CSRF-Token": this.csrf,
        },
        body: JSON.stringify({ action: "ack", messageId: Number(last.id), status: "read" }),
      })
    },

    applySettings() {
      const root = document.documentElement
      const scale = typeof this.settings.fontScale === "number" ? this.settings.fontScale : 1
      root.style.fontSize = `${Math.round(16 * scale)}px`

      root.dataset.compact = this.settings.compact ? "1" : "0"
      root.dataset.reduceMotion = this.settings.reduceMotion ? "1" : "0"

      const accentKey = this.settings.accent && accentMap[this.settings.accent] ? this.settings.accent : "mint"
      const acc = accentMap[accentKey]
      root.style.setProperty("--accent", acc.a)
      root.style.setProperty("--accent2", acc.b)
      root.style.setProperty("--meshA", acc.a)
      root.style.setProperty("--meshB", acc.b)

      const theme = this.settings.theme || "dark_blue"
      root.dataset.theme = theme
      if (theme === "light") {
        root.style.setProperty("--bg", "245 247 250")
        root.style.setProperty("--panel", "255 255 255")
        root.style.setProperty("--panel2", "248 250 252")
        root.style.setProperty("--fg", "12 12 13")
        root.style.setProperty("--muted", "65 74 87")
        root.style.setProperty("--ring", "12 12 13")
      } else if (theme === "cyberpunk") {
        root.style.setProperty("--bg", "7 8 12")
        root.style.setProperty("--panel", "10 12 18")
        root.style.setProperty("--panel2", "13 16 25")
        root.style.setProperty("--fg", "232 233 238")
        root.style.setProperty("--muted", "165 172 198")
        root.style.setProperty("--ring", "255 255 255")
      } else if (theme === "amoled") {
        root.style.setProperty("--bg", "0 0 0")
        root.style.setProperty("--panel", "10 10 10")
        root.style.setProperty("--panel2", "14 14 14")
        root.style.setProperty("--fg", "235 235 235")
        root.style.setProperty("--muted", "160 160 160")
        root.style.setProperty("--ring", "255 255 255")
      } else {
        root.style.setProperty("--bg", "5 10 25")
        root.style.setProperty("--panel", "7 14 34")
        root.style.setProperty("--panel2", "10 18 46")
        root.style.setProperty("--fg", "233 238 255")
        root.style.setProperty("--muted", "167 177 206")
        root.style.setProperty("--ring", "255 255 255")
      }
    },

    async saveSettings() {
      this.applySettings()
      await fetchJson("/api/settings.php", {
        method: "POST",
        headers: {
          "content-type": "application/json",
          "X-CSRF-Token": this.csrf,
        },
        body: JSON.stringify({ settings: this.settings }),
      })
    },

    setTheme(t) {
      this.settings.theme = t
      this.saveSettings()
    },

    setWallpaper(w) {
      this.settings.wallpaper = w
      this.saveSettings()
    },

    playSound(kind) {
      const ctx = new (window.AudioContext || window.webkitAudioContext)()
      const o = ctx.createOscillator()
      const g = ctx.createGain()
      o.type = kind === "out" ? "triangle" : "sine"
      o.frequency.value = kind === "out" ? 560 : 420
      g.gain.value = 0.0001
      o.connect(g)
      g.connect(ctx.destination)
      const t = ctx.currentTime
      g.gain.setValueAtTime(0.0001, t)
      g.gain.exponentialRampToValueAtTime(0.06, t + 0.01)
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.12)
      o.start(t)
      o.stop(t + 0.13)
      o.onended = () => ctx.close().catch(() => {})
    },

    openAuth(mode) {
      this.authError = ""
      this.authMode = mode
      this.authForm = { username: "", identifier: "", email: "", password: "" }
      this.authModalOpen = true
    },

    async submitAuth() {
      if (this.authBusy) return
      this.authBusy = true
      this.authError = ""
      try {
        if (this.authMode === "register") {
          const r = await fetchJson("/api/auth.php", {
            method: "POST",
            headers: { "content-type": "application/json" },
            body: JSON.stringify({
              action: "register",
              username: this.authForm.username,
              email: this.authForm.email || null,
              password: this.authForm.password,
            }),
          })
          if (!r.ok) {
            this.authError = "Kayıt başarısız"
            return
          }
          this.user = r.user
          this.csrf = r.csrf || this.csrf
          this.authModalOpen = false
          await this.bootstrap()
          return
        }

        const r = await fetchJson("/api/auth.php", {
          method: "POST",
          headers: { "content-type": "application/json" },
          body: JSON.stringify({
            action: "login",
            identifier: this.authForm.identifier,
            password: this.authForm.password,
          }),
        })
        if (!r.ok) {
          this.authError = "Giriş başarısız"
          return
        }
        this.user = r.user
        this.csrf = r.csrf || this.csrf
        this.authModalOpen = false
        await this.bootstrap()
      } finally {
        this.authBusy = false
      }
    },

    async logout() {
      await fetchJson("/api/auth.php", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ action: "logout" }),
      })
      this.user = null
      this.csrf = ""
      this.stopStream()
      await this.bootstrap()
    },
  }
}
