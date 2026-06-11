@extends('layouts.app')

@section('title', 'Moroccan Legal Research | MarocLoi')

@section('content')
  @php
    $lang = request('lang');
    $lang = in_array($lang, ['en', 'fr', 'ar'], true) ? $lang : 'ar';
    $copy = [
      'en' => [
        'badge' => 'Professional Legal Research',
        'title' => 'Moroccan Legal<br>Loipedia',
        'subtitle' => 'Search Moroccan legislation, official bulletins, legal texts, and source-backed analysis from one professional workspace.',
        'placeholder' => 'Search article, code, bulletin, or legal issue...',
        'search' => 'Search',
        'stats' => ['Indexed Articles', 'Legal Sources', 'Platform Uptime', 'Daily Updates'],
        'sourcesLabel' => 'Sources',
        'sourcesTitle' => 'Official materials stay visible.',
        'sourcesDesc' => 'Every result includes source metadata, publication dates, and direct links to official documents.',
        'sourceCards' => [
          ['Official Bulletins', 'Publication metadata, dates, source URLs and indexed document versions from the Official Bulletin.'],
          ['Legal Texts', 'Codes, dahirs, decrees, orders and article-level references with version tracking.'],
          ['Citations', 'Results include source links, article references, and official document identifiers.'],
        ],
        'coverageLabel' => 'Coverage',
        'coverageTitle' => 'Built for trust, not overclaiming.',
        'coverageDesc' => 'Coverage depends on indexed official sources. The platform communicates available sources transparently, not all Moroccan laws.',
        'coverageItems' => [
          ['Indexed corpus', 'Active versions are preferred and clearly labeled'],
          ['Legacy fallback', 'Older versions are labeled and distinguishable'],
          ['Official sources', 'Every document links back to its original source'],
        ],
        'databaseLabel' => 'Indexed Database',
        'databaseTitle' => 'Available Sources',
        'databaseDesc' => 'Browse our curated collection of Moroccan legal documents from verified official sources.',
        'ctaTitle' => 'Start Your Legal Research Today',
        'ctaDesc' => 'Access thousands of Moroccan legal documents from a single, professional workspace.',
        'create' => 'Create Free Account',
        'learn' => 'Learn More',
      ],
      'fr' => [
        'badge' => 'Recherche juridique professionnelle',
        'title' => 'Loipedia<br>juridique marocain',
        'subtitle' => 'Recherchez la legislation marocaine, les bulletins officiels, les textes juridiques et des analyses appuyees par des sources dans un seul espace professionnel.',
        'placeholder' => 'Rechercher un article, code, bulletin ou sujet juridique...',
        'search' => 'Rechercher',
        'stats' => ['Articles indexes', 'Sources juridiques', 'Disponibilite', 'Mises a jour'],
        'sourcesLabel' => 'Sources',
        'sourcesTitle' => 'Les sources officielles restent visibles.',
        'sourcesDesc' => 'Chaque resultat inclut les metadonnees, les dates de publication et les liens directs vers les documents officiels.',
        'sourceCards' => [
          ['Bulletins officiels', 'Metadonnees de publication, dates, URLs sources et versions indexees des documents du Bulletin Officiel.'],
          ['Textes juridiques', 'Codes, dahirs, decrets, arretes et references article par article avec suivi des versions.'],
          ['Citations', 'Les resultats incluent les liens sources, references d articles et identifiants des documents officiels.'],
        ],
        'coverageLabel' => 'Couverture',
        'coverageTitle' => 'Concu pour la confiance, pas pour surpromettre.',
        'coverageDesc' => 'La couverture depend des sources officielles indexees. La plateforme indique clairement les sources disponibles.',
        'coverageItems' => [
          ['Corpus indexe', 'Les versions actives sont privilegiees et clairement indiquees'],
          ['Secours legacy', 'Les anciennes versions sont etiquetees et distinguables'],
          ['Sources officielles', 'Chaque document renvoie vers sa source originale'],
        ],
        'databaseLabel' => 'Base indexee',
        'databaseTitle' => 'Sources disponibles',
        'databaseDesc' => 'Parcourez une collection de documents juridiques marocains issus de sources officielles verifiees.',
        'ctaTitle' => 'Commencez votre recherche juridique',
        'ctaDesc' => 'Accedez a des milliers de documents juridiques marocains depuis un espace professionnel unique.',
        'create' => 'Creer un compte gratuit',
        'learn' => 'En savoir plus',
      ],
      'ar' => [
        'badge' => 'بحث قانوني مهني',
        'title' => 'Loipedia<br>القانوني المغربي',
        'subtitle' => 'ابحث في التشريع المغربي والنشرات الرسمية والنصوص القانونية والتحليلات المدعومة بالمصادر من فضاء مهني واحد.',
        'placeholder' => 'ابحث عن مادة، مدونة، نشرة رسمية، أو موضوع قانوني...',
        'search' => 'بحث',
        'stats' => ['مادة مفهرسة', 'مصدر قانوني', 'جاهزية المنصة', 'تحديثات يومية'],
        'sourcesLabel' => 'المصادر',
        'sourcesTitle' => 'المواد الرسمية تبقى واضحة.',
        'sourcesDesc' => 'كل نتيجة تعرض بيانات المصدر وتاريخ النشر وروابط مباشرة للوثائق الرسمية.',
        'sourceCards' => [
          ['النشرات الرسمية', 'بيانات النشر والتواريخ وروابط المصدر والنسخ المفهرسة من النشرة الرسمية.'],
          ['النصوص القانونية', 'مدونات وظهائر ومراسيم وقرارات ومراجع على مستوى المواد مع تتبع النسخ.'],
          ['الإحالات', 'النتائج تتضمن روابط المصادر ومراجع المواد ومعرفات الوثائق الرسمية.'],
        ],
        'coverageLabel' => 'التغطية',
        'coverageTitle' => 'مصمم للثقة وليس للمبالغة.',
        'coverageDesc' => 'التغطية مرتبطة بالمصادر الرسمية المفهرسة. المنصة تعرض بوضوح ما هو متاح داخل corpus.',
        'coverageItems' => [
          ['Corpus مفهرس', 'تفضل النسخ السارية ويتم تمييزها بوضوح'],
          ['مصادر قديمة احتياطية', 'النسخ القديمة موسومة ويمكن تمييزها'],
          ['مصادر رسمية', 'كل وثيقة تعود إلى مصدرها الأصلي'],
        ],
        'databaseLabel' => 'قاعدة مفهرسة',
        'databaseTitle' => 'المصادر المتاحة',
        'databaseDesc' => 'تصفح مجموعة منتقاة من الوثائق القانونية المغربية من مصادر رسمية موثوقة.',
        'ctaTitle' => 'ابدأ بحثك القانوني اليوم',
        'ctaDesc' => 'ادخل إلى آلاف الوثائق القانونية المغربية من فضاء مهني واحد.',
        'create' => 'إنشاء حساب مجاني',
        'learn' => 'معرفة المزيد',
      ],
    ][$lang];
  @endphp

  {{-- Hero Section --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 min-h-[calc(100vh-5rem)] flex items-center">
    <div class="absolute inset-0 pointer-events-none">
      <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-blue-500/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-indigo-500/15 rounded-full blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-blue-600/5 rounded-full blur-3xl"></div>
    </div>
    <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 60px 60px;"></div>

    <div class="relative z-10 container-page pt-20 md:pt-24 pb-16 md:pb-24">
      <div class="max-w-4xl">
        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold tracking-wider uppercase bg-white/10 text-blue-200 border border-white/10 backdrop-blur-sm mb-8">
          <span class="w-2 h-2 rounded-full bg-blue-400 shadow-lg shadow-blue-400/50"></span>
          {{ $copy['badge'] }}
        </span>

        <h1 class="text-5xl sm:text-6xl md:text-7xl lg:text-8xl font-serif font-bold leading-none tracking-tight">
          <span class="gradient-text">{!! $copy['title'] !!}</span>
        </h1>

        <p class="mt-6 text-lg sm:text-xl text-blue-100/80 max-w-2xl leading-relaxed font-sans">
          {{ $copy['subtitle'] }}
        </p>

        <form class="flex items-center gap-2 p-2 mt-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl shadow-blue-500/10 max-w-2xl" action="/login" method="get">
          <input type="hidden" name="lang" value="{{ $lang }}">
          <div class="flex items-center gap-3 flex-1 px-4">
            <svg class="w-5 h-5 text-blue-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="search" name="q" placeholder="{{ $copy['placeholder'] }}" class="w-full bg-transparent border-none text-white placeholder:text-blue-200/50 text-sm md:text-base font-medium focus:outline-none">
          </div>
          <button type="submit" class="btn-primary flex-shrink-0">
            {{ $copy['search'] }}
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </button>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12" data-animate="fade-up">
          <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0s">
            <strong class="block text-2xl font-bold text-white">16,000+</strong>
            <span class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ $copy['stats'][0] }}</span>
          </div>
          <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.1s">
            <strong class="block text-2xl font-bold text-white">200+</strong>
            <span class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ $copy['stats'][1] }}</span>
          </div>
          <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.2s">
            <strong class="block text-2xl font-bold text-white">99.9%</strong>
            <span class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ $copy['stats'][2] }}</span>
          </div>
          <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4" style="--delay: 0.3s">
            <strong class="block text-2xl font-bold text-white">Real-time</strong>
            <span class="block mt-1 text-xs font-semibold tracking-wide text-blue-200/70 uppercase">{{ $copy['stats'][3] }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Sources Section --}}
  <section id="sources" class="py-20 md:py-28">
    <div class="container-page" data-animate="fade-up">
      <div class="max-w-3xl">
        <span class="section-label">{{ $copy['sourcesLabel'] }}</span>
        <h2 class="section-title mt-4">{{ $copy['sourcesTitle'] }}</h2>
        <p class="section-desc">{{ $copy['sourcesDesc'] }}</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 mt-12">
        <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0s">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900">{{ $copy['sourceCards'][0][0] }}</h3>
          <p class="mt-3 text-gray-600 leading-relaxed">{{ $copy['sourceCards'][0][1] }}</p>
        </article>

        <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0.1s">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900">{{ $copy['sourceCards'][1][0] }}</h3>
          <p class="mt-3 text-gray-600 leading-relaxed">{{ $copy['sourceCards'][1][1] }}</p>
        </article>

        <article class="card card-hover p-6 md:p-8" data-animate="fade-up" style="--delay: 0.2s">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-5 shadow-lg shadow-blue-500/20">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900">{{ $copy['sourceCards'][2][0] }}</h3>
          <p class="mt-3 text-gray-600 leading-relaxed">{{ $copy['sourceCards'][2][1] }}</p>
        </article>
      </div>
    </div>
  </section>

  {{-- Coverage Section --}}
  <section id="coverage" class="py-20 md:py-28 bg-gradient-to-b from-gray-50 to-white">
    <div class="container-page">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div data-animate="fade-up">
          <span class="section-label">{{ $copy['coverageLabel'] }}</span>
          <h2 class="section-title mt-4">{{ $copy['coverageTitle'] }}</h2>
          <p class="section-desc">{{ $copy['coverageDesc'] }}</p>
        </div>

        <div class="card p-6 md:p-8 border-blue-100 bg-gradient-to-br from-blue-50/50 to-white" data-animate="fade-up" style="--delay: 0.15s">
          <div class="space-y-5">
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0 mt-1">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <strong class="block text-gray-900 font-bold">{{ $copy['coverageItems'][0][0] }}</strong>
                <span class="text-sm text-gray-600 mt-1 block">{{ $copy['coverageItems'][0][1] }}</span>
              </div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0 mt-1">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <strong class="block text-gray-900 font-bold">{{ $copy['coverageItems'][1][0] }}</strong>
                <span class="text-sm text-gray-600 mt-1 block">{{ $copy['coverageItems'][1][1] }}</span>
              </div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0 mt-1">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <strong class="block text-gray-900 font-bold">{{ $copy['coverageItems'][2][0] }}</strong>
                <span class="text-sm text-gray-600 mt-1 block">{{ $copy['coverageItems'][2][1] }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Database Section --}}
  <section id="database" class="py-20 md:py-28">
    <div class="container-page" data-animate="fade-up">
      <div class="max-w-3xl">
        <span class="section-label">{{ $copy['databaseLabel'] }}</span>
        <h2 class="section-title mt-4">{{ $copy['databaseTitle'] }}</h2>
        <p class="section-desc">{{ $copy['databaseDesc'] }}</p>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mt-12">
        <div class="card p-5 text-center hover:border-blue-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0s">
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center mx-auto font-bold text-sm">BO</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">Bulletin Officiel</span>
        </div>
        <div class="card p-5 text-center hover:border-emerald-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0.05s">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto font-bold text-sm">CT</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">Code du Travail</span>
        </div>
        <div class="card p-5 text-center hover:border-rose-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0.1s">
          <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center mx-auto font-bold text-sm">CP</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">Code Pénal</span>
        </div>
        <div class="card p-5 text-center hover:border-violet-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0.15s">
          <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center mx-auto font-bold text-sm">CF</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">Code de la Famille</span>
        </div>
        <div class="card p-5 text-center hover:border-amber-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0.2s">
          <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center mx-auto font-bold text-sm">DO</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">DOC</span>
        </div>
        <div class="card p-5 text-center hover:border-cyan-200 transition-colors cursor-default" data-animate="scale-in" style="--delay: 0.25s">
          <div class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center mx-auto font-bold text-sm">IM</div>
          <span class="block mt-3 text-sm font-semibold text-gray-800">Immobilier</span>
        </div>
      </div>
    </div>
  </section>

  {{-- CTA Section --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 py-20 md:py-28">
    <div class="absolute inset-0 pointer-events-none">
      <div class="absolute -top-20 -right-20 w-80 h-80 bg-blue-400/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 container-page text-center" data-animate="fade-up">
      <h2 class="text-3xl md:text-5xl font-serif font-bold text-white leading-tight">{{ $copy['ctaTitle'] }}</h2>
      <p class="mt-4 text-lg md:text-xl text-blue-100 max-w-2xl mx-auto">{{ $copy['ctaDesc'] }}</p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-10">
        <a href="/register?lang={{ $lang }}" class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-blue-700 bg-white hover:bg-blue-50 shadow-2xl shadow-blue-900/30 transition-all duration-200 no-underline">
          {{ $copy['create'] }}
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </a>
        <a href="https://www.marokkobiz.com/" class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-white border border-white/30 hover:bg-white/10 transition-all duration-200 no-underline">
          {{ $copy['learn'] }}
        </a>
      </div>
    </div>
  </section>
@endsection
