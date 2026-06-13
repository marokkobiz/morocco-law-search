@extends('layouts.auth')

@section('title', 'Billing')

@section('content')
  @php
    $copy = [
      'en' => [
        'label' => 'Billing',
        'title' => 'Activate access',
        'desc' => 'Your account is created. Activate billing to use the protected legal research workspace.',
        'ready' => 'Stripe checkout is configured.',
        'missing' => 'Stripe checkout is not configured yet. Add STRIPE_SECRET and STRIPE_PRICE_ID before enforcing paid access.',
        'continue' => 'Continue to workspace',
        'checkout' => 'Start payment',
      ],
      'fr' => [
        'label' => 'Facturation',
        'title' => 'Activer l acces',
        'desc' => 'Votre compte est cree. Activez la facturation pour utiliser l espace de recherche juridique protege.',
        'ready' => 'Le paiement Stripe est configure.',
        'missing' => 'Le paiement Stripe n est pas encore configure. Ajoutez STRIPE_SECRET et STRIPE_PRICE_ID avant d imposer l acces payant.',
        'continue' => 'Continuer vers l espace',
        'checkout' => 'Commencer le paiement',
      ],
      'ar' => [
        'label' => 'الفوترة',
        'title' => 'تفعيل الولوج',
        'desc' => 'تم إنشاء حسابك. فعّل الأداء لاستعمال فضاء البحث القانوني المحمي.',
        'ready' => 'تم إعداد أداء Stripe.',
        'missing' => 'لم يتم إعداد أداء Stripe بعد. أضف STRIPE_SECRET و STRIPE_PRICE_ID قبل فرض الولوج المدفوع.',
        'continue' => 'المتابعة إلى الفضاء',
        'checkout' => 'بدء الأداء',
      ],
    ][$lang] ?? [];
  @endphp

  <section class="auth-card">
    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">{{ $copy['label'] }}</p>
    <h1>{{ $copy['title'] }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ $copy['desc'] }}</p>

    @if ($errors->any())
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
        {{ $errors->first() }}
      </div>
    @endif

    <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">
      {{ $stripeReady ? $copy['ready'] : $copy['missing'] }}
    </div>

    <form action="/billing/checkout" method="post" class="mt-5">
      @csrf
      <button type="submit">
        {{ config('billing.require_payment') ? $copy['checkout'] : $copy['continue'] }}
      </button>
    </form>
  </section>
@endsection
