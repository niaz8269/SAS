<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\EmailWebhookController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Partner\OrganisationController;
use App\Http\Controllers\Partner\SubmissionController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ScholarshipController;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (no sign-in required: CORE-01)
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

Route::get('/scholarships', [ScholarshipController::class, 'index'])->name('scholarships.index');
Route::get('/scholarships/{programme:slug}/{cycle}', [ScholarshipController::class, 'show'])->name('scholarships.show');
Route::get('/go/{route}', [ScholarshipController::class, 'out'])->name('scholarships.out');

Route::get('/universities', [CatalogueController::class, 'universities'])->name('universities.index');
Route::get('/universities/{university:slug}', [CatalogueController::class, 'university'])->name('universities.show');
Route::get('/programmes', [CatalogueController::class, 'programmes'])->name('programmes.index');
Route::get('/destinations', [PageController::class, 'destinations'])->name('destinations.index');
Route::get('/destinations/{country:slug}', [PageController::class, 'destination'])->name('destinations.show');
Route::get('/services', [PageController::class, 'services'])->name('services.index');
Route::get('/services/{service:slug}', [PageController::class, 'service'])->name('services.show');
Route::get('/resources', [PageController::class, 'resources'])->name('resources.index');
Route::get('/resources/{article:slug}', [PageController::class, 'resource'])->name('resources.show');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/image-credits', [PageController::class, 'credits'])->name('credits');
Route::get('/partners', [PageController::class, 'partners'])->name('partners');
Route::get('/legal/{page}', [PageController::class, 'legal'])->name('legal.show');
Route::get('/pages/{slug}', [PageController::class, 'cms'])->where('slug', '[A-Za-z0-9_-]+')->name('pages.show');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');

Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:enquiries')->name('enquiries.store');
Route::get('/enquiries/thanks', [EnquiryController::class, 'thanks'])->name('enquiries.thanks');
Route::get('/enquiries/{enquiry:public_id}', [EnquiryController::class, 'show'])->name('enquiries.show');
Route::post('/enquiries/{enquiry:public_id}/reply', [EnquiryController::class, 'reply'])->middleware('throttle:enquiries')->name('enquiries.reply');

Route::get('/consultation', [ConsultationController::class, 'create'])->name('consultations.create');
Route::post('/consultations', [ConsultationController::class, 'store'])->middleware('throttle:enquiries')->name('consultations.store');
Route::get('/consultation/requested', [ConsultationController::class, 'thanks'])->name('consultations.thanks');

Route::get('/corrections/new', [CorrectionController::class, 'create'])->name('corrections.create');
Route::post('/corrections', [CorrectionController::class, 'store'])->middleware('throttle:enquiries')->name('corrections.store');

Route::post('/webhooks/email/{provider}', EmailWebhookController::class)->middleware('throttle:120,1')->name('webhooks.email');

/*
|--------------------------------------------------------------------------
| Authentication (AUTH-01 / AUTH-02)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:verification'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:verification')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Student workspace (separate experience from the public site)
|--------------------------------------------------------------------------
*/
Route::prefix('student')->name('student.')->middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/', Student\DashboardController::class)->name('dashboard');

    Route::get('/profile', [Student\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Student\ProfileController::class, 'update'])->name('profile.update');

    Route::get('/saved-scholarships', [Student\SavedScholarshipController::class, 'index'])->name('saved.index');
    Route::post('/saved-scholarships', [Student\SavedScholarshipController::class, 'store'])->name('saved.store');
    Route::delete('/saved-scholarships/{cycle}', [Student\SavedScholarshipController::class, 'destroy'])->name('saved.destroy');

    Route::get('/cases', [Student\CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{case:public_id}', [Student\CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case:public_id}/messages', [Student\CaseController::class, 'message'])->middleware('throttle:enquiries')->name('cases.messages.store');
    Route::put('/cases/{case:public_id}/documents', [Student\CaseController::class, 'share'])->name('cases.documents.update');
    Route::get('/applications', [Student\ApplicationController::class, 'index'])->name('applications.index');
    Route::post('/applications', [Student\ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{plan:public_id}', [Student\ApplicationController::class, 'show'])->name('applications.show');
    Route::patch('/applications/{plan:public_id}', [Student\ApplicationController::class, 'update'])->name('applications.update');
    Route::delete('/applications/{plan:public_id}', [Student\ApplicationController::class, 'destroy'])->name('applications.destroy');
    Route::post('/applications/{plan:public_id}/acknowledge', [Student\ApplicationController::class, 'acknowledge'])->name('applications.acknowledge');
    Route::post('/applications/{plan:public_id}/tasks', [Student\ApplicationController::class, 'storeTask'])->name('applications.tasks.store');
    Route::patch('/applications/{plan:public_id}/tasks/{task}', [Student\ApplicationController::class, 'updateTask'])->name('applications.tasks.update');
    Route::delete('/applications/{plan:public_id}/tasks/{task}', [Student\ApplicationController::class, 'destroyTask'])->name('applications.tasks.destroy');
    Route::post('/applications/{plan:public_id}/documents', [Student\ApplicationController::class, 'attachDocument'])->name('applications.documents.attach');
    Route::delete('/applications/{plan:public_id}/documents/{document:public_id}', [Student\ApplicationController::class, 'detachDocument'])->name('applications.documents.detach');

    Route::get('/documents', [Student\DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [Student\DocumentController::class, 'store'])->middleware('throttle:uploads')->name('documents.store');
    Route::post('/documents/{document:public_id}/versions', [Student\DocumentController::class, 'storeVersion'])->middleware('throttle:uploads')->name('documents.versions.store');
    Route::get('/documents/{document:public_id}/download', [Student\DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document:public_id}', [Student\DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/enquiries', Student\EnquiryListController::class)->name('enquiries.index');

    Route::get('/security', [Student\SecurityController::class, 'show'])->name('security');
    Route::post('/security/enable', [Student\SecurityController::class, 'enable'])->middleware('throttle:login')->name('security.enable');
    Route::post('/security/disable', [Student\SecurityController::class, 'disable'])->middleware('throttle:login')->name('security.disable');
    Route::post('/security/recovery-codes', [Student\SecurityController::class, 'regenerate'])->middleware('throttle:login')->name('security.recovery');

    Route::get('/privacy', [Student\PrivacyController::class, 'show'])->name('privacy');
    Route::post('/privacy/export', [Student\PrivacyController::class, 'export'])->middleware('throttle:login')->name('privacy.export');
    Route::post('/privacy/delete-request', [Student\PrivacyController::class, 'requestDeletion'])->middleware('throttle:login')->name('privacy.delete.request');
    Route::get('/privacy/delete/{token}', [Student\PrivacyController::class, 'confirm'])->name('privacy.delete.confirm');
    Route::delete('/privacy/delete/{token}', [Student\PrivacyController::class, 'destroy'])->middleware('throttle:login')->name('privacy.delete.destroy');
    Route::get('/notifications', [Student\NotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/preferences', [Student\NotificationController::class, 'update'])->name('notifications.update');
    Route::post('/notifications/read-all', [Student\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [Student\NotificationController::class, 'read'])->name('notifications.read');
});

// Staff open a document a student shared on a case. The policy allows only active shares on cases the staff member may view.
Route::get('/files/{document:public_id}/download', [Student\DocumentController::class, 'download'])->middleware(['auth', 'active', 'verified'])->name('files.download');

/*
|--------------------------------------------------------------------------
| Partner area. R1: informational only; moderated self-service submissions arrive in R2.
|--------------------------------------------------------------------------
*/
Route::prefix('partner')->name('partner.')->middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/', PartnerController::class)->name('home');
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/new', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::post('/submissions', [SubmissionController::class, 'store'])->middleware('throttle:enquiries')->name('submissions.store');
    Route::get('/submissions/{submission:public_id}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::get('/submissions/{submission:public_id}/edit', [SubmissionController::class, 'edit'])->name('submissions.edit');
    Route::put('/submissions/{submission:public_id}', [SubmissionController::class, 'update'])->name('submissions.update');
    Route::get('/organisation', [OrganisationController::class, 'edit'])->name('organisation.edit');
    Route::put('/organisation', [OrganisationController::class, 'update'])->name('organisation.update');
});
