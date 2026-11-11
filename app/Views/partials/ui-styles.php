<?php
// Sitenin ortak arayüz sınıfları (Tailwind bileşenleri): wrap, btn, input, card, badge, alert, prose-content...
// Temalar renk/yazı tipi gibi @theme değerlerini kendisi verir, sonra bunu ekler:
//   <?= View::render('partials/ui-styles') ? >
// Yerine kendi sınıflarını yazmak isteyen tema bu dosyayı app/Themes/{Tema}/views/partials/ui-styles.php ile ezer.
?>
  <style type="text/tailwindcss">
    @layer base {
      *, ::before, ::after { @apply border-border; }
      body { @apply bg-background font-sans text-foreground antialiased; }
    }
    @layer components {
      .wrap { @apply mx-auto w-full max-w-6xl px-4 sm:px-6; }
      .btn { @apply inline-flex h-9 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md px-4 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:pointer-events-none disabled:opacity-50; }
      .btn-sm { @apply h-8 px-3 text-xs; }
      .btn-lg { @apply h-10 px-6; }
      .btn-primary { @apply bg-primary text-primary-foreground shadow-xs hover:bg-primary/90; }
      .btn-secondary { @apply border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground; }
      .btn-ghost { @apply hover:bg-accent hover:text-accent-foreground; }
      .label { @apply text-sm font-medium leading-none; }
      .input { @apply mt-2 flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20; }
      textarea.input { @apply h-auto min-h-20 py-2; }
      .input-error { @apply border-destructive focus-visible:ring-destructive/20; }
      .field-error { @apply mt-2 text-sm text-destructive; }
      .field-help { @apply mt-2 text-sm text-muted-foreground; }
      .card { @apply rounded-lg border bg-card shadow-xs; }
      .badge { @apply inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap; }
      /* Modül sayfalarındaki durum rozetleri (mağaza, randevu, sipariş...) */
      .badge-default { @apply border-transparent bg-primary text-primary-foreground; }
      .badge-gray { @apply border-transparent bg-muted text-muted-foreground; }
      .badge-green { @apply border-emerald-200 bg-emerald-50 text-emerald-700; }
      .badge-yellow { @apply border-amber-200 bg-amber-50 text-amber-800; }
      .badge-red { @apply border-red-200 bg-red-50 text-red-700; }
      .alert { @apply mb-6 w-full rounded-lg border px-4 py-3 text-sm; }
      .alert-ok { @apply border-emerald-200 bg-emerald-50 text-emerald-900; }
      .alert-error { @apply border-red-200 bg-red-50 text-red-900; }
      .alert-info { @apply bg-card; }
      .alert-warning { @apply border-amber-200 bg-amber-50 text-amber-900; }
      /* Editörden gelen yazı içeriği (Html::render) */
      .prose-content { @apply leading-7 text-foreground/90; }
      .prose-content > * + * { @apply mt-5; }
      .prose-content h2 { @apply mt-10 text-2xl font-semibold tracking-tight text-foreground; }
      .prose-content h3 { @apply mt-8 text-xl font-semibold tracking-tight text-foreground; }
      .prose-content h4 { @apply mt-6 font-semibold text-foreground; }
      .prose-content a { @apply font-medium text-foreground underline underline-offset-4 hover:text-primary; }
      .prose-content strong, .prose-content b { @apply font-semibold text-foreground; }
      .prose-content ul { @apply list-disc space-y-1.5 pl-6; }
      .prose-content ol { @apply list-decimal space-y-1.5 pl-6; }
      .prose-content blockquote { @apply border-l-2 pl-5 italic text-muted-foreground; }
      .prose-content pre { @apply overflow-x-auto rounded-md border bg-muted px-4 py-3 font-mono text-sm leading-6; }
      .prose-content code { @apply rounded-sm bg-muted px-1.5 py-0.5 font-mono text-sm; }
      .prose-content pre code { @apply bg-transparent p-0; }
      .prose-content hr { @apply my-8; }
      .prose-content table { @apply block w-full overflow-x-auto border-collapse text-sm sm:table; }
      .prose-content th, .prose-content td { @apply border px-3 py-2 text-left align-top; }
      .prose-content th { @apply bg-muted font-semibold text-foreground; }
      .prose-content tr:nth-child(even) td { @apply bg-muted/40; }
      .prose-content img { @apply my-2 h-auto max-w-full rounded-md border; }
      /* Editörde seçilen görsel boyutu ve hizalaması (Html::IMAGE_CLASSES) */
      .prose-content img.img-sm { width: 33%; }
      .prose-content img.img-md { width: 50%; }
      .prose-content img.img-lg { width: 75%; }
      .prose-content img.img-center { display: block; margin-left: auto; margin-right: auto; }
      .prose-content img.img-left { float: left; margin: 0.25rem 1.5rem 0.75rem 0; }
      .prose-content img.img-right { float: right; margin: 0.25rem 0 0.75rem 1.5rem; }
      .prose-content::after { content: ''; display: table; clear: both; }
      .prose-content h2, .prose-content h3, .prose-content hr, .prose-content table { clear: both; }
      @media (max-width: 639px) {
        .prose-content img.img-left, .prose-content img.img-right { float: none; display: block; margin: 0.5rem auto; }
        .prose-content img.img-sm, .prose-content img.img-md { width: 75%; }
      }
    }
  </style>
