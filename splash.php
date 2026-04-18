<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Shamanicca — Sacred Style for the Modern Mystic</title>
  <meta name="description" content="Intentionally crafted clothing and sacred objects for those who move through the world with awareness, ritual, and soul." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,900;1,300&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { poppins: ['Poppins', 'sans-serif'] },
          colors: {
            primary: '#675dff',
            'primary-light': '#e7e1ff',
            'primary-dark': '#371b97',
            brand: {
              50:  '#f5f0ff',
              100: '#ede3ff',
              900: '#1a0a2e',
            },
          },
          borderRadius: {
            'site-sm': '15px',
            'site-md': '18px',
            'site-lg': '24px',
          },
        },
      },
    };
  </script>
  <style>
    * { font-family: 'Poppins', sans-serif; }
    .hero-bg {
      background-image:
        linear-gradient(160deg,
          rgba(26, 10, 46, 0.92) 0%,
          rgba(74, 35, 90, 0.80) 40%,
          rgba(15, 10, 20, 0.96) 100%),
        url('<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/splash-hero.jpg');
      background-size: cover;
      background-position: center;
    }
    @keyframes fade-up {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-up            { animation: fade-up 0.7s ease both; }
    .animate-fade-up-delay-1    { animation: fade-up 0.7s 0.12s ease both; }
    .animate-fade-up-delay-2    { animation: fade-up 0.7s 0.24s ease both; }
    .animate-fade-up-delay-3    { animation: fade-up 0.7s 0.38s ease both; }
    .animate-fade-up-delay-4    { animation: fade-up 0.7s 0.52s ease both; }
  </style>
</head>
<body class="bg-white text-stone-900 antialiased overflow-hidden h-screen">

  <div class="flex flex-col lg:flex-row h-screen">

    <!-- ── LEFT PANEL ─────────────────────────────────────── -->
    <div class="flex flex-col justify-between px-8 py-10 lg:px-16 lg:py-14 lg:w-[46%] bg-white z-10 h-full overflow-y-auto">

      <!-- Logo — same source as checkout page (WordPress Customizer logo) -->
      <div class="animate-fade-up">
        <a href="https://shamanicca.com" aria-label="Shamanicca home">
          <?php
            $logo_id  = get_theme_mod( 'custom_logo' );
            $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
          ?>
          <?php if ( $logo_url ) : ?>
            <img
              src="<?php echo esc_url( $logo_url ); ?>"
              alt="Shamanicca"
              style="height:34px;width:auto;display:block;"
            />
          <?php else : ?>
            <span class="text-xl font-bold tracking-tight text-stone-900">Shamanicca</span>
          <?php endif; ?>
        </a>
      </div>

      <!-- Main copy -->
      <div class="flex flex-col gap-6 max-w-md">

        <!-- Eyebrow -->
        <p class="animate-fade-up text-xs font-semibold tracking-[0.2em] uppercase text-stone-400">
          Sacred Style · Intentional Living
        </p>

        <!-- H1 -->
        <h1 class="animate-fade-up-delay-1 text-4xl lg:text-5xl xl:text-[3.25rem] font-bold leading-[1.12] text-stone-900">
          Sacred Style<br />
          for the<br />
          <span class="text-primary">Modern Mystic</span>
        </h1>

        <!-- H2 / sub-headline -->
        <h2 class="animate-fade-up-delay-2 text-base lg:text-lg font-light text-stone-500 leading-relaxed">
          Where ancient wisdom meets contemporary expression.
        </h2>

        <!-- Body copy -->
        <p class="animate-fade-up-delay-3 text-sm lg:text-base text-stone-600 leading-relaxed">
          Intentionally crafted clothing, adornments, and sacred objects — for those who move through the world with awareness, ritual, and soul. Each piece carries a story of transformation.
        </p>

        <!-- CTA -->
        <div class="animate-fade-up-delay-4 flex flex-col sm:flex-row gap-3 pt-2">
          <a
            href="https://shamanicca.com"
            class="inline-flex items-center justify-center gap-2 bg-primary text-white text-sm font-semibold tracking-wide px-7 py-4 rounded-site-sm lg:rounded-site-lg hover:bg-primary-dark transition-colors duration-300"
          >
            Explore the Collection
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </a>
        </div>
      </div>

      <!-- Footer -->
      <div class="animate-fade-up text-xs text-stone-400 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
        <span>© <?php echo esc_html( date( 'Y' ) ); ?> Shamanicca. All rights reserved.</span>
        <span class="hidden sm:inline text-stone-200">·</span>
        <a href="https://shamanicca.com/privacy-policy" class="hover:text-stone-700 transition-colors">Privacy</a>
      </div>
    </div>

    <!-- ── RIGHT PANEL — hero (only rendered when image file exists) ── -->
    <?php
      $hero_file = get_stylesheet_directory() . '/images/splash-hero.jpg';
      $hero_url  = esc_url( get_stylesheet_directory_uri() . '/images/splash-hero.jpg' );
      $has_hero  = file_exists( $hero_file );
    ?>
    <?php if ( $has_hero ) : ?>
    <!-- Right panel hidden by default via inline style; JS reveals it only after image loads -->
    <div id="splash-hero-panel" class="hidden lg:flex lg:w-[54%] hero-bg relative items-end p-12" style="opacity:0;transition:opacity 0.6s ease;">
      <p class="text-white/30 text-xs font-light tracking-[0.3em] uppercase select-none">
        Mindfulness · Alchemy · Shamanism
      </p>
    </div>
    <script>
      (function () {
        var panel = document.getElementById('splash-hero-panel');
        var img   = new Image();
        img.onload = function () {
          if (panel) panel.style.opacity = '1';
        };
        img.onerror = function () {
          if (panel) panel.style.display = 'none';
        };
        img.src = '<?php echo $hero_url; ?>';
      })();
    </script>
    <?php endif; ?>

  </div>

  <!-- Mobile hero strip (below fold on small screens) -->
  <div class="lg:hidden fixed bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-brand-900 via-brand-700 to-brand-900"></div>

</body>
</html>
