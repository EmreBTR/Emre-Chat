<?php

declare(strict_types=1);

?><!doctype html>
<html lang="tr" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EmreCloud Chat</title>
  <link rel="stylesheet" href="/assets/css/app.css" />
  <script>
    window.__CHAT__ = { baseUrl: "https://chat.emrecloud.com.tr" }
  </script>
  <script defer src="/assets/js/app.js"></script>
  <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body class="h-dvh bg-[rgb(var(--bg))] text-[rgb(var(--fg))]" x-data="chatApp()" x-init="init()">
  <div class="mx-auto flex h-dvh w-full max-w-[1500px] overflow-hidden border-x border-white/10">
    <aside class="flex h-full w-[380px] shrink-0 flex-col bg-[rgb(var(--panel))]">
      <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <div class="flex items-center gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-sm font-semibold text-white/90 ring-1 ring-white/10">
            EC
          </div>
          <div class="leading-tight">
            <div class="text-sm font-semibold tracking-tight">EmreCloud Chat</div>
            <div class="text-xs text-white/60" x-text="userLabel"></div>
          </div>
        </div>

        <button type="button" class="inline-flex h-9 items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-xs text-white/80 hover:bg-white/10" @click="drawerOpen = true">
          Ayarlar
        </button>
      </div>

      <div class="border-b border-white/10 px-3 py-2">
        <div class="grid grid-cols-2 gap-2 rounded-xl bg-white/5 p-1">
          <button type="button" class="rounded-lg px-2 py-2 text-xs font-medium transition" :class="activeTab === 'public' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="activeTab = 'public'">
            Public
          </button>
          <button type="button" class="rounded-lg px-2 py-2 text-xs font-medium transition" :class="activeTab === 'dm' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="activeTab = 'dm'">
            Özel
          </button>
        </div>
      </div>

      <div class="px-3 py-3">
        <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2">
          <input type="text" class="w-full bg-transparent text-sm outline-none placeholder:text-white/40" placeholder="Ara" x-model="search" />
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
        <template x-if="activeTab === 'public'">
          <div class="space-y-1">
            <template x-for="g in filteredGroups" :key="g.id">
              <button type="button" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-white/5" :class="activeGroupId === g.id ? 'bg-white/5 ring-1 ring-white/15' : ''" @click="selectGroup(g.id)">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-500/20">
                  #
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <div class="truncate text-sm font-semibold" x-text="g.name"></div>
                    <div class="shrink-0 text-[11px] text-white/50" x-text="g.time || ''"></div>
                  </div>
                  <div class="truncate text-xs text-white/55" x-text="g.last || 'Herkese açık kanal'"></div>
                </div>
              </button>
            </template>
          </div>
        </template>

        <template x-if="activeTab === 'dm'">
          <div class="px-3 py-6 text-sm text-white/60">
            Üyeler arası 1-1 sohbet ilk sürümde kapalı.
          </div>
        </template>
      </div>
    </aside>

    <main class="relative flex h-full min-w-0 flex-1 flex-col bg-[rgb(var(--bg))]">
      <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <div class="flex min-w-0 items-center gap-3">
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-500/20">
            #
          </div>
          <div class="min-w-0 leading-tight">
            <div class="truncate text-sm font-semibold" x-text="activeGroup?.name || 'Kanal'"></div>
            <div class="truncate text-xs text-white/55" x-text="connectionLabel"></div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" class="inline-flex h-9 items-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs text-white/80 hover:bg-white/10" @click="drawerOpen = true">
            Profil
          </button>
        </div>
      </div>

      <div class="relative min-h-0 flex-1 overflow-hidden">
        <div class="absolute inset-0 chat-bg"></div>
        <div x-ref="scroller" class="relative h-full overflow-y-auto px-4 py-5">
          <div class="mx-auto w-full max-w-[880px] space-y-2">
            <template x-for="m in messages" :key="m.id">
              <div class="flex w-full" :class="isMine(m) ? 'justify-end' : 'justify-start'">
                <div class="max-w-[78%] rounded-2xl px-3 py-2 text-sm shadow-sm ring-1 ring-white/10" :class="isMine(m) ? 'bg-emerald-500/15 text-emerald-50 ring-emerald-500/20' : 'bg-white/5 text-white ring-white/10'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                  <div class="text-[11px] font-semibold opacity-70" x-text="senderLabel(m)"></div>
                  <div class="mt-0.5 whitespace-pre-wrap break-words" x-text="m.message_text"></div>
                  <div class="mt-1 flex items-center justify-end gap-2 text-[11px] opacity-60">
                    <span x-text="formatTs(m.created_at)"></span>
                    <span x-text="statusGlyph(m.status)"></span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <div class="border-t border-white/10 px-4 py-3">
        <div class="mx-auto flex w-full max-w-[880px] items-end gap-2">
          <div class="flex-1 rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
            <textarea rows="1" class="max-h-32 w-full resize-none bg-transparent text-sm outline-none placeholder:text-white/40" placeholder="Mesaj yaz" x-model="draft" @keydown.enter.prevent="send()"></textarea>
          </div>
          <button type="button" class="inline-flex h-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 px-4 text-sm font-semibold text-black hover:bg-emerald-400 active:bg-emerald-600 disabled:opacity-50" @click="send()" :disabled="sending">
            Gönder
          </button>
        </div>
      </div>
    </main>
  </div>

  <div class="fixed inset-0 z-40" x-show="drawerOpen" x-transition.opacity style="display: none" @keydown.escape.window="drawerOpen = false">
    <div class="absolute inset-0 bg-black/60" @click="drawerOpen = false"></div>
    <aside class="absolute right-0 top-0 h-dvh w-[380px] border-l border-white/10 bg-[rgb(var(--panel))] shadow-2xl" x-show="drawerOpen" x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
      <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
        <div class="text-sm font-semibold">Ayarlar</div>
        <button type="button" class="inline-flex h-9 items-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs text-white/80 hover:bg-white/10" @click="drawerOpen = false">Kapat</button>
      </div>

      <div class="px-4 py-3">
        <div class="grid grid-cols-3 gap-2 rounded-xl bg-white/5 p-1">
          <button type="button" class="rounded-lg px-2 py-2 text-xs font-medium transition" :class="settingsTab === 'profile' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="settingsTab = 'profile'">Profil</button>
          <button type="button" class="rounded-lg px-2 py-2 text-xs font-medium transition" :class="settingsTab === 'notifications' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="settingsTab = 'notifications'">Bildirim</button>
          <button type="button" class="rounded-lg px-2 py-2 text-xs font-medium transition" :class="settingsTab === 'appearance' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="settingsTab = 'appearance'">Görünüm</button>
        </div>
      </div>

      <div class="space-y-3 px-4 pb-6">
        <template x-if="settingsTab === 'profile'">
          <div class="space-y-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-xs text-white/60">Hesap</div>
              <div class="mt-1 text-sm font-semibold" x-text="userLabel"></div>
              <div class="mt-1 text-xs text-white/50">Domain: chat.emrecloud.com.tr</div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold hover:bg-white/10" @click="openAuth('login')">Giriş</button>
              <button type="button" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold hover:bg-white/10" @click="openAuth('register')">Kayıt</button>
            </div>
            <button type="button" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold hover:bg-white/10" @click="logout()">Çıkış</button>
          </div>
        </template>

        <template x-if="settingsTab === 'notifications'">
          <div class="space-y-3">
            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4">
              <div>
                <div class="text-sm font-semibold">Ses</div>
                <div class="text-xs text-white/60">Yeni mesajda ses çal</div>
              </div>
              <input type="checkbox" class="h-4 w-4 accent-emerald-500" x-model="settings.sounds" @change="saveSettings()" />
            </label>
          </div>
        </template>

        <template x-if="settingsTab === 'appearance'">
          <div class="space-y-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-sm font-semibold">Tema</div>
              <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setTheme('light')">Light</button>
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setTheme('dark_blue')">Gece Safir</button>
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setTheme('cyberpunk')">Cyberpunk</button>
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setTheme('amoled')">AMOLED</button>
              </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-sm font-semibold">Yazı Boyutu</div>
              <input type="range" min="0.85" max="1.25" step="0.01" class="mt-3 w-full" x-model.number="settings.fontScale" @input="applySettings()" @change="saveSettings()" />
              <div class="mt-2 text-xs text-white/60" x-text="settings.fontScale.toFixed(2) + 'x'"></div>
            </div>
          </div>
        </template>
      </div>
    </aside>
  </div>

  <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4" x-show="authModalOpen" x-transition.opacity style="display: none" @keydown.escape.window="authModalOpen = false">
    <div class="w-full max-w-md rounded-3xl border border-white/10 bg-[rgb(var(--panel))] p-5 shadow-2xl">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold" x-text="authMode === 'login' ? 'Giriş' : 'Kayıt'"></div>
        <button type="button" class="inline-flex h-9 items-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs text-white/80 hover:bg-white/10" @click="authModalOpen = false">Kapat</button>
      </div>
      <div class="mt-4 space-y-3">
        <template x-if="authMode === 'register'">
          <input type="text" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none placeholder:text-white/40" placeholder="Kullanıcı adı" x-model="authForm.username" />
        </template>
        <input type="text" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none placeholder:text-white/40" placeholder="E-posta veya kullanıcı adı" x-model="authForm.identifier" x-show="authMode === 'login'" />
        <template x-if="authMode === 'register'">
          <input type="email" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none placeholder:text-white/40" placeholder="E-posta (opsiyonel)" x-model="authForm.email" />
        </template>
        <input type="password" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none placeholder:text-white/40" placeholder="Şifre" x-model="authForm.password" />
        <button type="button" class="w-full rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-black hover:bg-emerald-400 disabled:opacity-50" @click="submitAuth()" :disabled="authBusy">
          Devam
        </button>
        <div class="text-xs text-rose-300" x-show="authError" x-text="authError"></div>
      </div>
    </div>
  </div>
</body>
</html>
