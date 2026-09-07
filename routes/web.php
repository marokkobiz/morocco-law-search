<?php

use App\Http\Controllers\Admin\AdvisorManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LegalAidController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Advisor\CaseController;
use App\Http\Controllers\Advisor\DashboardController as AdvisorDashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Legal Aid – now the shop (renamed from /shop, physical views in resources/views/legal-aid/*)
Route::get('/test/beta/legal-aid', [ShopController::class, 'index'])->name('legal-aid');
Route::get('/test/beta/legal-aid/cart', [ShopController::class, 'cart'])->name('legal-aid.cart');
Route::get('/test/beta/legal-aid/api/products', [ShopController::class, 'apiProducts'])->name('legal-aid.api.products');
Route::post('/test/beta/legal-aid/checkout', [ShopController::class, 'createCheckoutSession'])->name('legal-aid.checkout.create');
Route::get('/test/beta/legal-aid/success/{order}', [ShopController::class, 'success'])->name('legal-aid.success');
Route::get('/test/beta/legal-aid/cancel/{order}', [ShopController::class, 'cancel'])->name('legal-aid.cancel');

// Legacy shop URLs – 301 redirect for browsing, keep API aliases for old Stripe sessions / JS
Route::get('/shop', fn() => redirect('/test/beta/legal-aid', 301))->name('shop.index');
Route::get('/shop/cart', fn() => redirect('/test/beta/legal-aid/cart', 301))->name('shop.cart');
Route::post('/shop/checkout', [ShopController::class, 'createCheckoutSession'])->name('shop.checkout.create');
Route::get('/shop/success/{order}', [ShopController::class, 'success'])->name('shop.success');
Route::get('/shop/cancel/{order}', [ShopController::class, 'cancel'])->name('shop.cancel');
Route::get('/shop/api/products', [ShopController::class, 'apiProducts'])->name('shop.api.products');

// Legacy legal-aid ticket routes (kept for existing tickets / tests, not linked from nav)
Route::get('/legal-aid/confirm/{token}', [LegalAidController::class, 'confirmBooking'])->name('legal-aid.confirm-booking');
Route::get('/legal-aid/confirmed/{ticket}', [LegalAidController::class, 'confirmed'])->name('legal-aid.confirmed');
Route::get('/legal-aid/payment/{ticket}', [LegalAidController::class, 'payment'])->name('legal-aid.payment');
Route::get('/legal-aid/ticket/{ticket}/pdf', [LegalAidController::class, 'downloadTicketPdf'])->name('legal-aid.ticket-pdf');
Route::post('/legal-aid/payment/{ticket}/stripe/checkout', [StripePaymentController::class, 'createCheckoutSession'])->name('legal-aid.payment.checkout');
Route::get('/legal-aid/payment/{ticket}/stripe/success', [StripePaymentController::class, 'checkoutSuccess'])->name('legal-aid.payment.checkout.success');
Route::post('/legal-aid/payment/{ticket}/stripe/intent', [StripePaymentController::class, 'createIntent'])->name('legal-aid.payment.intent');
Route::post('/legal-aid/payment/{ticket}/stripe/verify', [StripePaymentController::class, 'verify'])->name('legal-aid.payment.verify');

// Stripe webhook (signature-verified, CSRF-exempt — see bootstrap/app.php)
Route::post('/stripe/webhook', [StripePaymentController::class, 'webhook'])
    ->name('stripe.webhook');

Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr', 'ar'])) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::get('/forgot-password', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Workspace
    Route::prefix('app')->name('app.')->group(function () {
        Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('workspace');
        Route::get('/search', [WorkspaceController::class, 'index'])->name('search');
        Route::get('/laws/{document}', [WorkspaceController::class, 'show'])->name('law.show');
    });
});

// Admin routes (Secured with auth and admin middleware)
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::post('/users/{user}/toggle-advisor', [UserController::class, 'toggleAdvisor'])->name('users.toggle-advisor');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

        // Advisor management (admin only)
        Route::get('/advisors', [AdvisorManagementController::class, 'index'])->name('advisors.index');
        Route::get('/advisors/create', [AdvisorManagementController::class, 'create'])->name('advisors.create');
        Route::post('/advisors', [AdvisorManagementController::class, 'store'])->name('advisors.store');
        Route::get('/advisors/{advisor}/edit', [AdvisorManagementController::class, 'edit'])->name('advisors.edit');
        Route::put('/advisors/{advisor}', [AdvisorManagementController::class, 'update'])->name('advisors.update');
        Route::delete('/advisors/{advisor}', [AdvisorManagementController::class, 'destroy'])->name('advisors.destroy');
        Route::post('/advisors/{advisor}/suspend', [AdvisorManagementController::class, 'suspend'])->name('advisors.suspend');
        Route::post('/advisors/{advisor}/unsuspend', [AdvisorManagementController::class, 'unsuspend'])->name('advisors.unsuspend');
        Route::post('/advisors/{advisor}/reset-password', [AdvisorManagementController::class, 'resetPassword'])->name('advisors.reset-password');
    });

// Advisor portal (advisors only — admins are blocked)
Route::middleware(['auth', 'advisor'])
    ->prefix('advisor')
    ->name('advisor.')
    ->group(function () {
        Route::get('/dashboard', [AdvisorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/{legalAidRequest}', [CaseController::class, 'show'])->name('cases.show');
        Route::post('/cases/{legalAidRequest}/services/{service}/toggle', [CaseController::class, 'toggleService'])->name('cases.toggle-service');
        Route::post('/cases/{legalAidRequest}/first-contact', [CaseController::class, 'markFirstContact'])->name('cases.first-contact');
        Route::post('/cases/{legalAidRequest}/close', [CaseController::class, 'close'])->name('cases.close');
        Route::post('/cases/{legalAidRequest}/reopen', [CaseController::class, 'reopen'])->name('cases.reopen');
        Route::post('/cases/{legalAidRequest}/notes', [CaseController::class, 'storeNote'])->name('cases.notes');
    });
