<?php
// Yönetim paneli renk modu: Açık / Koyu / Sistem (varsayılan Sistem). Tercih tarayıcıda saklanır (localStorage 'modrix-admin-theme').
// <head> içinde, stillerden sonra çağrılır: sayfa çizilmeden önce doğru mod uygulanır (beyaz parlama olmaz).
// Seçici: View::render('partials/admin-color-mode-switch')
?>
<script>
  (() => {
    const pref = (() => { try { return localStorage.getItem('modrix-admin-theme') || 'system'; } catch { return 'system'; } })();
    const dark = pref === 'dark' || (pref === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.themePref = pref;
  })();
</script>
<style>
  /* Koyu mod renkleri (Tailwind @theme değişkenlerinin koyu karşılıkları) */
  html.dark {
    color-scheme: dark;
    --color-background: #09090b;
    --color-foreground: #fafafa;
    --color-card: #111113;
    --color-muted: #1f1f23;
    --color-muted-foreground: #a1a1aa;
    --color-accent: #232327;
    --color-accent-foreground: #fafafa;
    --color-sidebar: #0c0c0f;
    --color-border: #27272a;
    --color-input: #34343a;
    --color-destructive: #f05252;
  }
  /* Açık renk tonlu kutular (uyarı, başarı, hata) koyuda koyu tonlara */
  html.dark .bg-white:not(.keep-light) { background-color: var(--color-card); }  /* .keep-light: QR kod gibi beyaz kalması gerekenler */
  html.dark .bg-amber-50 { background-color: rgb(120 53 15 / .28); }
  html.dark .border-amber-200, html.dark .border-amber-300 { border-color: rgb(180 83 9 / .45); }
  html.dark .text-amber-900, html.dark .text-amber-700 { color: #fcd34d; }
  html.dark .bg-emerald-50 { background-color: rgb(6 78 59 / .35); }
  html.dark .border-emerald-200 { border-color: rgb(4 120 87 / .45); }
  html.dark .text-emerald-900, html.dark .text-emerald-700 { color: #6ee7b7; }
  html.dark .text-emerald-600 { color: #34d399; }
  html.dark .bg-red-50 { background-color: rgb(127 29 29 / .3); }
  html.dark .border-red-200 { border-color: rgb(185 28 28 / .45); }
  html.dark .text-red-900, html.dark .text-red-600 { color: #fca5a5; }
  html.dark img[src$=".svg"].invert-on-dark { filter: invert(1); }
  html.dark .ql-toolbar .ql-stroke { stroke: var(--color-foreground); }
  html.dark .ql-toolbar .ql-fill { fill: var(--color-foreground); }
  html.dark .ql-toolbar .ql-picker { color: var(--color-foreground); }
  html.dark .ql-picker-options { background-color: var(--color-card) !important; }
</style>
