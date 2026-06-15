<?php

declare(strict_types=1);

$config = require __DIR__ . '/../core/bootstrap.php';
$pdo = chat_try_pdo($config);
$identity = chat_get_identity();

if (!is_array($identity) || (string) ($identity['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'forbidden';
    exit;
}

$bans = [];
if ($pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT ip_address, reason, expires_at FROM banned_ips ORDER BY id DESC LIMIT 50');
    $bans = $stmt->fetchAll();
}

?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - EmreCloud Chat</title>
  <link rel="stylesheet" href="/assets/css/app.css" />
</head>
<body class="min-h-dvh bg-[rgb(var(--bg))] text-[rgb(var(--fg))]">
  <div class="mx-auto max-w-5xl px-6 py-10">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-2xl font-semibold tracking-tight">Admin Panel</div>
        <div class="mt-1 text-sm text-white/60">chat.emrecloud.com.tr</div>
      </div>
      <a href="/" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold hover:bg-white/10">Sohbete Dön</a>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
        <div class="text-sm font-semibold">Toplu Yayın (Taslak)</div>
        <div class="mt-2 text-sm text-white/60">
          İlk sürümde yayın kuyruğu ve gönderim endpointleri eklenmedi. Sonraki fazda public gruplara ve aktif misafirlere sırayla dağıtım yapılacak.
        </div>
      </div>

      <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
        <div class="text-sm font-semibold">Canlı Monitor (Taslak)</div>
        <div class="mt-2 text-sm text-white/60">
          SSE bağlantı sayısı ve DB ping metrikleri sonraki fazda eklenecek.
        </div>
      </div>
    </div>

    <div class="mt-6 rounded-3xl border border-white/10 bg-white/5 p-5">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold">Banlı IP’ler</div>
        <div class="text-xs text-white/60">Son 50 kayıt</div>
      </div>
      <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
        <table class="w-full text-left text-sm">
          <thead class="bg-white/5 text-xs text-white/60">
            <tr>
              <th class="px-4 py-3">IP</th>
              <th class="px-4 py-3">Sebep</th>
              <th class="px-4 py-3">Bitiş</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
          <?php foreach ($bans as $ban): ?>
            <tr>
              <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars((string) ($ban['ip_address'] ?? '')); ?></td>
              <td class="px-4 py-3 text-white/70"><?php echo htmlspecialchars((string) ($ban['reason'] ?? '')); ?></td>
              <td class="px-4 py-3 text-white/70"><?php echo htmlspecialchars((string) ($ban['expires_at'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
