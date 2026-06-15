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
<body class="h-dvh overflow-hidden bg-[rgb(var(--bg))] text-[rgb(var(--fg))]" x-data="chatApp()" x-init="init()">
  <div class="relative h-dvh w-full">
    <div class="absolute inset-0 chat-bg"></div>
    <div class="pointer-events-none absolute inset-0 noise"></div>

    <div class="relative mx-auto flex h-dvh w-full max-w-[1650px] overflow-hidden px-3 py-3">
      <div class="flex h-full w-full overflow-hidden rounded-[28px] border border-white/10">
        <nav class="glass flex h-full w-[78px] shrink-0 flex-col items-center gap-2 p-3">
          <button type="button" class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-sm font-black tracking-tight ring-1 ring-white/10 hover:bg-white/15" @click="rail = 'home'">
            EC
          </button>

          <div class="mt-2 flex w-full flex-col gap-2">
            <button type="button" class="grid h-11 w-11 place-items-center rounded-2xl text-lg ring-1 ring-white/10 transition hover:bg-white/10" :class="rail === 'public' ? 'bg-white text-black' : 'bg-white/5'" @click="rail = 'public'; activeTab = 'public'">#</button>
            <button type="button" class="grid h-11 w-11 place-items-center rounded-2xl text-lg ring-1 ring-white/10 transition hover:bg-white/10" :class="rail === 'dm' ? 'bg-white text-black' : 'bg-white/5'" @click="rail = 'dm'; activeTab = 'dm'">◎</button>
          </div>

          <div class="mt-auto flex w-full flex-col gap-2">
            <button type="button" class="grid h-11 w-11 place-items-center rounded-2xl bg-white/5 text-lg ring-1 ring-white/10 transition hover:bg-white/10" @click="drawerOpen = true">⚙</button>
          </div>
        </nav>

        <aside class="glass-2 flex h-full w-[360px] shrink-0 flex-col border-l border-white/10">
          <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-4">
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold tracking-tight">EmreCloud Chat</div>
              <div class="mt-1 flex items-center gap-2 text-xs text-white/60">
                <span class="truncate" x-text="userLabel"></span>
                <span class="pill" x-show="connection !== 'open'">
                  <span class="pulse-soft">●</span>
                  <span x-text="connectionLabel"></span>
                </span>
              </div>
            </div>
            <button type="button" class="btn btn-ghost h-10 px-3 text-xs" @click="openAuth('login')">Giriş</button>
          </div>

          <div class="px-4 py-3">
            <div class="flex items-center gap-2">
              <input type="text" class="input" placeholder="Ara (kanal, mesaj, @handle)" x-model="search" />
            </div>
          </div>

          <div class="px-4 pb-2">
            <div class="grid grid-cols-2 gap-2 rounded-2xl bg-white/5 p-1 ring-1 ring-white/10">
              <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold transition" :class="activeTab === 'public' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="activeTab = 'public'">Kanallar</button>
              <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold transition" :class="activeTab === 'dm' ? 'bg-white text-black' : 'text-white/80 hover:bg-white/10'" @click="activeTab = 'dm'">DM</button>
            </div>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
            <template x-if="activeTab === 'public'">
              <div class="space-y-1 px-2">
                <div class="px-2 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-white/45">Public Channels</div>
                <template x-for="g in filteredGroups" :key="g.id">
                  <button type="button" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-white/5" :class="activeGroupId === g.id ? 'bg-white/7 ring-1 ring-white/15' : ''" @click="selectGroup(g.id)">
                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-white/5 text-xs font-black ring-1 ring-white/10">#</div>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center justify-between gap-2">
                        <div class="truncate text-sm font-semibold" x-text="g.name"></div>
                        <div class="shrink-0 text-[11px] text-white/45" x-text="g.time || ''"></div>
                      </div>
                      <div class="truncate text-xs text-white/55" x-text="g.last || 'Herkese açık kanal'"></div>
                    </div>
                  </button>
                </template>

                <div class="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                  Yeni kanallar, roller ve davet linkleri sıradaki fazda açılacak.
                </div>
              </div>
            </template>

            <template x-if="activeTab === 'dm'">
              <div class="space-y-2 px-4 py-4 text-sm text-white/60">
                <div class="text-sm font-semibold text-white/85">DM</div>
                <div>Bu sürümde DM UI hazır, backend akışı bir sonraki fazda açılacak.</div>
              </div>
            </template>
          </div>
        </aside>

        <main class="relative flex h-full min-w-0 flex-1 flex-col">
          <div class="glass flex items-center justify-between gap-3 border-l border-white/10 px-5 py-4">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <div class="grid h-9 w-9 place-items-center rounded-2xl bg-white/5 ring-1 ring-white/10">#</div>
                <div class="truncate text-sm font-semibold tracking-tight" x-text="activeGroup?.name || 'Kanal'"></div>
                <div class="pill">
                  <span x-text="presenceCount + ' aktif'"></span>
                </div>
              </div>
              <div class="mt-1 text-xs text-white/55">
                <span x-show="typingLabel" x-text="typingLabel"></span>
                <span x-show="!typingLabel" x-text="connectionLabel"></span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-ghost h-10 px-3 text-xs" @click="membersOpen = !membersOpen" x-text="membersOpen ? 'Üyeler' : 'Üyeler'"></button>
              <button type="button" class="btn btn-ghost h-10 px-3 text-xs" @click="drawerOpen = true">Ayarlar</button>
            </div>
          </div>

          <div class="relative min-h-0 flex-1 overflow-hidden border-l border-white/10">
            <div class="absolute inset-0">
              <div class="absolute inset-0 chat-bg"></div>
              <div class="pointer-events-none absolute inset-0 opacity-70" :class="wallpaperClass"></div>
            </div>

            <div x-ref="scroller" class="relative h-full overflow-y-auto px-5 py-6">
              <div class="mx-auto w-full max-w-[980px] space-y-2">
                <template x-for="m in messages" :key="m.id">
                  <div class="group flex w-full gap-3" :class="isMine(m) ? 'justify-end' : 'justify-start'">
                    <div class="hidden w-10 shrink-0 xl:block" x-show="!isMine(m)">
                      <div class="grid h-10 w-10 place-items-center rounded-2xl bg-white/5 text-xs font-black ring-1 ring-white/10">
                        <span x-text="avatarGlyph(m)"></span>
                      </div>
                    </div>

                    <div class="relative max-w-[78%]">
                      <div class="absolute -right-2 -top-2 flex items-center gap-1 opacity-0 transition group-hover:opacity-100" :class="isMine(m) ? '' : 'right-auto left-0'">
                        <button type="button" class="grid h-8 w-8 place-items-center rounded-xl bg-white/8 ring-1 ring-white/10 hover:bg-white/12" @click="setReply(m)">↩</button>
                        <button type="button" class="grid h-8 w-8 place-items-center rounded-xl bg-white/8 ring-1 ring-white/10 hover:bg-white/12" @click="copyMessage(m)">⧉</button>
                      </div>

                      <div class="float-in bubble rounded-[22px] px-4 py-3 text-sm shadow-[0_18px_45px_rgba(0,0,0,0.22)]" :class="bubbleClass(m)">
                        <div class="flex items-center justify-between gap-3">
                          <div class="text-[11px] font-semibold tracking-wide opacity-70" x-text="senderLabel(m)"></div>
                          <div class="text-[11px] opacity-60">
                            <span x-text="formatTs(m.created_at)"></span>
                            <span class="ml-2" x-text="statusGlyph(m.status)"></span>
                          </div>
                        </div>

                        <template x-if="m._replyTo">
                          <div class="mt-2 rounded-2xl border border-white/10 bg-black/10 px-3 py-2 text-xs">
                            <div class="font-semibold opacity-75" x-text="m._replyTo.sender"></div>
                            <div class="mt-0.5 clamp-2 opacity-70" x-text="m._replyTo.text"></div>
                          </div>
                        </template>

                        <div class="mt-2 whitespace-pre-wrap break-words" x-text="m.message_text"></div>

                        <template x-if="m._link">
                          <a class="mt-3 block rounded-2xl border border-white/10 bg-white/5 px-3 py-3 text-xs transition hover:bg-white/8" :href="m._link.url" target="_blank" rel="noreferrer">
                            <div class="font-semibold" x-text="m._link.host"></div>
                            <div class="mt-1 opacity-70" x-text="m._link.url"></div>
                          </a>
                        </template>
                      </div>
                    </div>
                  </div>
                </template>

                <template x-if="messages.length === 0">
                  <div class="mx-auto max-w-lg rounded-3xl border border-white/10 bg-white/5 p-5 text-center">
                    <div class="text-sm font-semibold">Kanal boş</div>
                    <div class="mt-2 text-sm text-white/60">İlk mesajı sen gönder. Misafir modu otomatik handle atar.</div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div class="glass-2 border-l border-t border-white/10 px-5 py-4">
            <div class="mx-auto w-full max-w-[980px]">
              <template x-if="replyTo">
                <div class="mb-2 flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs">
                  <div class="min-w-0">
                    <div class="font-semibold">Yanıt</div>
                    <div class="truncate opacity-70" x-text="replyTo.message_text"></div>
                  </div>
                  <button type="button" class="grid h-8 w-8 place-items-center rounded-xl bg-white/5 ring-1 ring-white/10 hover:bg-white/10" @click="replyTo = null">×</button>
                </div>
              </template>

              <div class="flex items-end gap-2">
                <button type="button" class="grid h-12 w-12 place-items-center rounded-2xl bg-white/5 text-lg ring-1 ring-white/10 transition hover:bg-white/10" @click="emojiOpen = !emojiOpen">☺</button>

                <div class="flex-1">
                  <textarea rows="1" class="input max-h-36 resize-none" placeholder="Mesaj yaz" x-model="draft" @input="onDraft()" @keydown.enter.prevent="send()"></textarea>
                </div>

                <button type="button" class="btn btn-accent h-12 px-5 disabled:opacity-50" @click="send()" :disabled="sending">Gönder</button>
              </div>

              <div class="relative">
                <div class="absolute bottom-2 left-0 z-20 w-[320px] rounded-3xl border border-white/10 bg-[rgb(var(--panel))] p-3 shadow-2xl" x-show="emojiOpen" x-transition.opacity style="display: none" @click.outside="emojiOpen = false">
                  <div class="grid grid-cols-8 gap-2 text-lg">
                    <template x-for="e in emojis" :key="e">
                      <button type="button" class="grid h-9 w-9 place-items-center rounded-2xl bg-white/5 ring-1 ring-white/10 hover:bg-white/10" @click="insertEmoji(e)" x-text="e"></button>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>

        <aside class="glass-2 hidden h-full w-[300px] shrink-0 flex-col border-l border-white/10 xl:flex" x-show="membersOpen">
          <div class="border-b border-white/10 px-4 py-4">
            <div class="text-sm font-semibold">Aktif</div>
            <div class="mt-1 text-xs text-white/60"><span x-text="presenceCount"></span> kişi</div>
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <div class="space-y-2">
              <template x-for="u in memberList" :key="u.key">
                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-3">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold" x-text="u.label"></div>
                    <div class="mt-0.5 truncate text-xs text-white/60" x-text="u.kind"></div>
                  </div>
                  <div class="h-2 w-2 rounded-full bg-emerald-400"></div>
                </div>
              </template>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <div class="fixed inset-0 z-40" x-show="drawerOpen" x-transition.opacity style="display: none" @keydown.escape.window="drawerOpen = false">
    <div class="absolute inset-0 bg-black/60" @click="drawerOpen = false"></div>
    <aside class="absolute right-0 top-0 h-dvh w-[420px] border-l border-white/10 bg-[rgb(var(--panel))] shadow-2xl" x-show="drawerOpen" x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
      <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
        <div class="text-sm font-semibold">Ayarlar</div>
        <button type="button" class="btn btn-ghost h-10 px-3 text-xs" @click="drawerOpen = false">Kapat</button>
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
            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4">
              <div>
                <div class="text-sm font-semibold">Animasyon azalt</div>
                <div class="text-xs text-white/60">Düşük hareket (reduce motion)</div>
              </div>
              <input type="checkbox" class="h-4 w-4 accent-emerald-500" x-model="settings.reduceMotion" @change="saveSettings()" />
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
              <div class="text-sm font-semibold">Wallpaper</div>
              <div class="mt-3 grid grid-cols-3 gap-2">
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setWallpaper('mesh')">Mesh</button>
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setWallpaper('dots')">Dots</button>
                <button type="button" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold hover:bg-white/10" @click="setWallpaper('grid')">Grid</button>
              </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-sm font-semibold">Yazı Boyutu</div>
              <input type="range" min="0.85" max="1.25" step="0.01" class="mt-3 w-full" x-model.number="settings.fontScale" @input="applySettings()" @change="saveSettings()" />
              <div class="mt-2 text-xs text-white/60" x-text="settings.fontScale.toFixed(2) + 'x'"></div>
            </div>
            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4">
              <div>
                <div class="text-sm font-semibold">Yoğun mod</div>
                <div class="text-xs text-white/60">Daha sıkı boşluklar</div>
              </div>
              <input type="checkbox" class="h-4 w-4 accent-emerald-500" x-model="settings.compact" @change="saveSettings()" />
            </label>
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
