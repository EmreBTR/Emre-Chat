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

  return {
    activeTab: "public",
    search: "",
    groups: [{ id: 0, name: "Global", last: "", time: "" }],
    activeGroupId: 0,
    messages: [],
    lastId: 0,
    es: null,
    connection: "idle",
    draft: "",
    sending: false,

    drawerOpen: false,
    settingsTab: "profile",
    settings: {
      theme: "dark_blue",
      fontScale: 1.0,
      wallpaper: "mesh",
      sounds: true,
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

    init() {
      this.applySettings()
      this.bootstrap()
    },

    async bootstrap() {
      this.connection = "connecting"
      const me = await fetchJson("/api/auth.php?action=me")
      if (me.ok) {
        this.user = me.user
        this.csrf = me.csrf || ""
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
      )}`

      this.connection = "connecting"
      const es = new EventSource(url, { withCredentials: true })
      this.es = es

      es.addEventListener("open", () => {
        this.connection = "open"
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

    upsertMessage(m) {
      const id = Number(m.id || 0)
      if (!id) return
      if (id > this.lastId) this.lastId = id
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

      this.$nextTick(() => this.scrollToBottom())
      if (this.settings.sounds) {
        this.playSound("in")
      }
    },

    scrollToBottom() {
      const el = this.$refs.scroller
      if (!el) return
      el.scrollTop = el.scrollHeight
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

    formatTs(ts) {
      if (!ts) return ""
      return String(ts).slice(11, 16)
    },

    statusGlyph(status) {
      if (status === "read") return "✓✓"
      if (status === "delivered") return "✓✓"
      return "✓"
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

    async send() {
      const text = (this.draft || "").trim()
      if (!text) return
      if (this.sending) return

      this.sending = true
      try {
        const okGuest = await this.ensureGuestIdentity()
        if (!okGuest) {
          this.sending = false
          return
        }

        const r = await fetchJson("/api/send.php", {
          method: "POST",
          headers: {
            "content-type": "application/json",
            "X-CSRF-Token": this.csrf,
          },
          body: JSON.stringify({
            target: { type: "group", groupId: this.activeGroupId },
            text,
          }),
        })

        if (r.ok && r.message) {
          this.upsertMessage(r.message)
          if (this.settings.sounds) {
            this.playSound("out")
          }
        }
        this.draft = ""
      } finally {
        this.sending = false
      }
    },

    applySettings() {
      const root = document.documentElement
      const scale = typeof this.settings.fontScale === "number" ? this.settings.fontScale : 1
      root.style.fontSize = `${Math.round(16 * scale)}px`

      const theme = this.settings.theme || "dark_blue"
      if (theme === "light") {
        root.style.setProperty("--bg", "245 247 250")
        root.style.setProperty("--panel", "255 255 255")
        root.style.setProperty("--fg", "12 12 13")
        root.style.setProperty("--meshA", "16 185 129")
        root.style.setProperty("--meshB", "59 130 246")
      } else if (theme === "cyberpunk") {
        root.style.setProperty("--bg", "7 8 12")
        root.style.setProperty("--panel", "10 12 18")
        root.style.setProperty("--fg", "232 233 238")
        root.style.setProperty("--meshA", "236 72 153")
        root.style.setProperty("--meshB", "34 211 238")
      } else if (theme === "amoled") {
        root.style.setProperty("--bg", "0 0 0")
        root.style.setProperty("--panel", "10 10 10")
        root.style.setProperty("--fg", "235 235 235")
        root.style.setProperty("--meshA", "16 185 129")
        root.style.setProperty("--meshB", "99 102 241")
      } else {
        root.style.setProperty("--bg", "5 10 25")
        root.style.setProperty("--panel", "7 14 34")
        root.style.setProperty("--fg", "233 238 255")
        root.style.setProperty("--meshA", "16 185 129")
        root.style.setProperty("--meshB", "99 102 241")
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

    playSound(kind) {
      const file = kind === "out" ? "/assets/sounds/out.mp3" : "/assets/sounds/in.mp3"
      const audio = new Audio(file)
      audio.volume = 0.6
      audio.play().catch(() => {})
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
