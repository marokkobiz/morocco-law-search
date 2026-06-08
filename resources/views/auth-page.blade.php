@extends('layouts.auth')

@section('title', $mode === 'register' ? 'Create account' : 'Login')

@section('content')
  @php
    $lang = isset($lang) && in_array($lang, ['en', 'fr', 'ar'], true)
      ? $lang
      : (in_array(request('lang'), ['en', 'fr', 'ar'], true) ? request('lang') : 'en');
    $path = request()->path();
    $copy = [
      'en' => [
        'platform' => 'Platform access',
        'welcome' => 'Welcome back',
        'createTitle' => 'Create account',
        'loginTitle' => 'Login',
        'createDesc' => 'Create a lawyer profile for account review and future paid access.',
        'loginDesc' => 'Account access is prepared for the legal research platform.',
        'fullName' => 'Full name',
        'fullNamePlaceholder' => 'Your name',
        'company' => 'Company / firm',
        'companyPlaceholder' => 'Law firm or company',
        'phone' => 'Phone',
        'email' => 'Work email',
        'password' => 'Password',
        'passwordPlaceholder' => 'Enter your password',
        'bar' => 'Bar / court',
        'barPlaceholder' => 'Select the bar or court',
        'note' => 'Payment hookup can be connected after the account intake and pricing are confirmed.',
        'submitCreate' => 'Create account',
        'submitLogin' => 'Login',
        'toLogin' => 'Already have an account? Login',
        'toRegister' => 'Need access? Create account',
      ],
      'fr' => [
        'platform' => 'Acces plateforme',
        'welcome' => 'Bon retour',
        'createTitle' => 'Creer un compte',
        'loginTitle' => 'Connexion',
        'createDesc' => 'Creez un profil avocat pour la validation du compte et le futur acces payant.',
        'loginDesc' => 'L acces au compte est prepare pour la plateforme de recherche juridique.',
        'fullName' => 'Nom complet',
        'fullNamePlaceholder' => 'Votre nom',
        'company' => 'Cabinet / societe',
        'companyPlaceholder' => 'Cabinet d avocat ou societe',
        'phone' => 'Telephone',
        'email' => 'Email professionnel',
        'password' => 'Mot de passe',
        'passwordPlaceholder' => 'Entrez votre mot de passe',
        'bar' => 'Barreau / tribunal',
        'barPlaceholder' => 'Selectionnez le barreau ou tribunal',
        'note' => 'Le paiement pourra etre connecte apres validation de l inscription et des prix.',
        'submitCreate' => 'Creer le compte',
        'submitLogin' => 'Connexion',
        'toLogin' => 'Deja un compte ? Connexion',
        'toRegister' => 'Besoin d acces ? Creer un compte',
      ],
      'ar' => [
        'platform' => 'الدخول إلى المنصة',
        'welcome' => 'مرحبا بعودتك',
        'createTitle' => 'إنشاء حساب',
        'loginTitle' => 'تسجيل الدخول',
        'createDesc' => 'أنشئ ملفا مهنيا للمحامي من أجل مراجعة الحساب والاستعداد للولوج المدفوع.',
        'loginDesc' => 'تم تجهيز ولوج الحساب لمنصة البحث القانوني.',
        'fullName' => 'الاسم الكامل',
        'fullNamePlaceholder' => 'اسمك الكامل',
        'company' => 'المكتب / الشركة',
        'companyPlaceholder' => 'مكتب المحاماة أو الشركة',
        'phone' => 'الهاتف',
        'email' => 'البريد المهني',
        'password' => 'كلمة المرور',
        'passwordPlaceholder' => 'أدخل كلمة المرور',
        'bar' => 'هيئة المحامين / المحكمة',
        'barPlaceholder' => 'اختر الهيئة أو المحكمة',
        'note' => 'يمكن ربط الأداء بعد تأكيد بيانات الحساب والأسعار.',
        'submitCreate' => 'إنشاء الحساب',
        'submitLogin' => 'تسجيل الدخول',
        'toLogin' => 'لديك حساب؟ تسجيل الدخول',
        'toRegister' => 'تحتاج إلى ولوج؟ إنشاء حساب',
      ],
    ][$lang];
    $courts = $lang === 'ar'
      ? ['هيئة القنيطرة / محكمة القنيطرة', 'هيئة الدار البيضاء', 'هيئة الرباط', 'هيئة مراكش', 'هيئة فاس', 'هيئة طنجة', 'هيئة أكادير', 'هيئة أو محكمة أخرى']
      : ['Kenitra Bar / Kenitra Court', 'Casablanca Bar', 'Rabat Bar', 'Marrakech Bar', 'Fes Bar', 'Tangier Bar', 'Agadir Bar', 'Other bar or court'];
  @endphp

  <section class="auth-card {{ $mode === 'register' ? 'auth-card-register' : '' }}">
    <nav class="auth-language-switcher" aria-label="Language">
      <a href="/{{ $path }}?lang=en" class="{{ $lang === 'en' ? 'is-active' : '' }}">EN</a>
      <a href="/{{ $path }}?lang=fr" class="{{ $lang === 'fr' ? 'is-active' : '' }}">FR</a>
      <a href="/{{ $path }}?lang=ar" class="{{ $lang === 'ar' ? 'is-active' : '' }}">AR</a>
    </nav>

    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">
      {{ $mode === 'register' ? $copy['platform'] : $copy['welcome'] }}
    </p>
    <h1>{{ $mode === 'register' ? $copy['createTitle'] : $copy['loginTitle'] }}</h1>
    <p class="mt-1 text-sm text-gray-500">
      {{ $mode === 'register' ? $copy['createDesc'] : $copy['loginDesc'] }}
    </p>

    @if ($errors->any())
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
        {{ $errors->first() }}
      </div>
    @endif

    <form action="{{ $mode === 'register' ? '/register' : '/login' }}" method="post" data-auth-gate>
      @csrf
      <input type="hidden" name="lang" value="{{ $lang }}">

      @if ($mode === 'register')
        <div class="auth-field-grid">
          <label>
            {{ $copy['fullName'] }}
            <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ $copy['fullNamePlaceholder'] }}" autocomplete="name" required>
          </label>
          <label>
            {{ $copy['company'] }}
            <input type="text" name="company" value="{{ old('company') }}" placeholder="{{ $copy['companyPlaceholder'] }}" autocomplete="organization" required>
          </label>
          <label>
            {{ $copy['phone'] }}
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+212 6 00 00 00 00" autocomplete="tel" required>
          </label>
          <label>
            {{ $copy['email'] }}
            <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" required>
          </label>
          <label class="auth-field-full">
            {{ $copy['bar'] }}
            <select name="bar" required>
              <option value="">{{ $copy['barPlaceholder'] }}</option>
              @foreach ($courts as $court)
                <option value="{{ $court }}" @selected(old('bar') === $court)>{{ $court }}</option>
              @endforeach
            </select>
          </label>
        </div>
      @else
        <label>
          {{ $copy['email'] }}
          <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" required>
        </label>
      @endif

      <label>
        {{ $copy['password'] }}
        <input type="password" name="password" placeholder="{{ $copy['passwordPlaceholder'] }}" autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" required>
      </label>

      @if ($mode === 'register')
        <p class="auth-note">{{ $copy['note'] }}</p>
      @endif

      <button type="submit">{{ $mode === 'register' ? $copy['submitCreate'] : $copy['submitLogin'] }}</button>
    </form>

    <a href="{{ $mode === 'register' ? '/login' : '/register' }}?lang={{ $lang }}" class="auth-link">
      {{ $mode === 'register' ? $copy['toLogin'] : $copy['toRegister'] }}
    </a>
  </section>
@endsection
