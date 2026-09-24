<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentCollectionController as AdminPaymentCollectionController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\QuickCustomerController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Controllers\Admin\WorkforceController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Customer\PortalController as CustomerPortalController;
use App\Http\Controllers\EvidenceDownloadController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Technician\PaymentCollectionController as TechnicianPaymentCollectionController;
use App\Http\Controllers\Technician\PortalController as TechnicianPortalController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('public.tenant')->group(function (): void {
    Route::get('/', [WebsiteController::class, 'home'])->name('home');
    Route::get('/pages/{slug}', [WebsiteController::class, 'page'])->name('website.page');
    Route::get('/blog/{slug}', [WebsiteController::class, 'post'])->name('website.post');
    Route::post('/enquiries', [WebsiteController::class, 'storeLead'])->middleware('throttle:10,1')->name('website.enquiries.store');
    Route::get('/sitemap.xml', [WebsiteController::class, 'sitemap'])->name('website.sitemap');
    Route::get('/robots.txt', [WebsiteController::class, 'robots'])->name('website.robots');
});

Route::post('/payments/razorpay/webhook', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('payments.razorpay.webhook');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [OtpLoginController::class, 'create'])->name('login');
    Route::post('/login/otp', [OtpLoginController::class, 'store'])
        ->middleware('throttle:otp-request')
        ->name('login.otp.store');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('login.otp.verify');
});

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::post('/logout', [OtpLoginController::class, 'destroy'])->name('logout');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions/{pushSubscription}', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::get('/evidence/{jobEvidence}', EvidenceDownloadController::class)->middleware('signed')->name('evidence.download');
    Route::get('/payment-qr', [PaymentSettingsController::class, 'image'])->name('payment-qr.image');

    Route::prefix('admin')->name('admin.')->middleware('role:OWNER,ADMIN,MANAGER,DISPATCHER,ACCOUNTANT')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware('role:OWNER')->group(function (): void {
            Route::get('/system-updates', [SystemUpdateController::class, 'index'])->name('system-updates.index');
            Route::post('/system-updates', [SystemUpdateController::class, 'store'])->middleware('throttle:5,1')->name('system-updates.store');
            Route::post('/system-updates/{id}/apply', [SystemUpdateController::class, 'apply'])->middleware('throttle:3,1')->name('system-updates.apply');
            Route::get('/system-updates/{id}/status', [SystemUpdateController::class, 'status'])->name('system-updates.status');
        });

        Route::middleware('role:OWNER,ADMIN,MANAGER,DISPATCHER')->group(function (): void {
            Route::post('/customers/quick', [QuickCustomerController::class, 'store'])->name('customers.quick.store');
            Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
            Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
            Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
            Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
            Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
            Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
            Route::get('/jobs/{job}/suggestions', [JobController::class, 'suggestions'])->name('jobs.suggestions');
            Route::post('/jobs/{job}/assignments', [JobController::class, 'assign'])->name('jobs.assignments.store');
            Route::post('/jobs/{job}/transitions', [JobController::class, 'transition'])->name('jobs.transitions.store');
            Route::get('/jobs/{job}/job-card', [JobController::class, 'downloadJobCard'])->name('jobs.job-card');
            Route::get('/jobs/{job}/evidence/{evidence}', [JobController::class, 'evidence'])->name('jobs.evidence');
        });

        Route::middleware('role:OWNER,ADMIN,MANAGER,ACCOUNTANT')->group(function (): void {
            Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            Route::post('/vendors', [PurchaseController::class, 'storeVendor'])->name('vendors.store');
            Route::put('/vendors/{vendor}', [PurchaseController::class, 'updateVendor'])->name('vendors.update');
            Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
            Route::get('/purchases/{purchaseOrder}', [PurchaseController::class, 'show'])->name('purchases.show');
            Route::post('/purchases/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
            Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::post('/account-entries', [AccountController::class, 'store'])->name('account-entries.store');
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            Route::post('/stock-locations', [InventoryController::class, 'storeLocation'])->name('stock-locations.store');
            Route::post('/stock-movements', [InventoryController::class, 'storeMovement'])->name('stock-movements.store');
            Route::post('/tax-profiles', [InventoryController::class, 'storeTaxProfile'])->name('tax-profiles.store');
            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::post('/quotations', [BillingController::class, 'storeQuotation'])->name('quotations.store');
            Route::post('/quotations/{quotation}/accept', [BillingController::class, 'acceptQuotation'])->name('quotations.accept');
            Route::post('/invoices', [BillingController::class, 'storeInvoice'])->name('invoices.store');
            Route::post('/work-orders/{workOrder}/invoice', [BillingController::class, 'invoiceWorkOrder'])->name('work-orders.invoice');
            Route::get('/invoices/{invoice}/pdf', [BillingController::class, 'downloadInvoice'])->name('invoices.pdf');
            Route::post('/payments', [BillingController::class, 'storePayment'])->name('payments.store');
            Route::post('/payment-collections/{jobPaymentCollection}/review', [AdminPaymentCollectionController::class, 'review'])->name('payment-collections.review');
            Route::get('/payment-collections/{jobPaymentCollection}/proof', [AdminPaymentCollectionController::class, 'proof'])->name('payment-collections.proof');
            Route::post('/payment-settings/upi', [PaymentSettingsController::class, 'update'])->name('payment-settings.upi.update');
            Route::post('/invoices/{invoice}/gateway-order', [BillingController::class, 'gatewayOrder'])->name('invoices.gateway-order');
            Route::get('/warranties', [WarrantyController::class, 'index'])->name('warranties.index');
            Route::post('/warranties', [WarrantyController::class, 'store'])->name('warranties.store');
            Route::post('/warranty-claims', [WarrantyController::class, 'storeClaim'])->name('warranty-claims.store');
            Route::post('/warranty-claims/{warrantyClaim}/review', [WarrantyController::class, 'reviewClaim'])->name('warranty-claims.review');
            Route::post('/amc-contracts', [WarrantyController::class, 'storeAmc'])->name('amc-contracts.store');
            Route::get('/warranties/{warranty}/certificate', [WarrantyController::class, 'certificate'])->name('warranties.certificate');
            Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
            Route::post('/report-schedules', [AnalyticsController::class, 'storeSchedule'])->name('report-schedules.store');
            Route::post('/reports', [AnalyticsController::class, 'generate'])->name('reports.store');
            Route::get('/reports/{generatedReport}/download', [AnalyticsController::class, 'downloadReport'])->name('reports.download');
            Route::post('/backups', [AnalyticsController::class, 'backup'])->name('backups.store');
            Route::get('/backups/{systemBackup}/download', [AnalyticsController::class, 'downloadBackup'])->name('backups.download');
            Route::get('/readiness', [AnalyticsController::class, 'readiness'])->name('readiness');
        });

        Route::middleware('role:OWNER,ADMIN,MANAGER')->group(function (): void {
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
            Route::post('/feedback/{feedback}/review', [FeedbackController::class, 'review'])->name('feedback.review');
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notification-templates', [NotificationController::class, 'storeTemplate'])->name('notification-templates.store');
            Route::get('/workforce', [WorkforceController::class, 'index'])->name('workforce.index');
            Route::post('/leave-requests/{leaveRequest}/review', [WorkforceController::class, 'reviewLeave'])->name('leave-requests.review');
            Route::post('/payout-cycles', [WorkforceController::class, 'storePayout'])->name('payout-cycles.store');
            Route::get('/payout-cycles/{payoutCycle}/lines/{line}/payslip', [WorkforceController::class, 'payslip'])->name('payout-lines.payslip');
            Route::post('/payout-disputes/{payoutDispute}/resolve', [WorkforceController::class, 'resolveDispute'])->name('payout-disputes.resolve');
            Route::get('/cms', [CmsController::class, 'index'])->name('cms.index');
            Route::post('/cms/content', [CmsController::class, 'store'])->name('cms.content.store');
            Route::put('/cms/{type}/{content}', [CmsController::class, 'update'])->name('cms.content.update');
            Route::post('/cms/media', [CmsController::class, 'storeMedia'])->name('cms.media.store');
            Route::post('/cms/enquiries/{bookingEnquiry}', [CmsController::class, 'updateEnquiry'])->name('cms.enquiries.update');
            Route::get('/cms/{type}/{content}/preview', [CmsController::class, 'preview'])->name('cms.preview');
            Route::post('/cms/{type}/{content}/publish', [CmsController::class, 'publish'])->name('cms.publish');
            Route::delete('/cms/{type}/{content}/publish', [CmsController::class, 'unpublish'])->name('cms.unpublish');
            Route::delete('/cms/{type}/{content}', [CmsController::class, 'destroy'])->name('cms.destroy');
        });
    });

    Route::prefix('technician')->name('technician.')->middleware('role:TECHNICIAN')->group(function (): void {
        Route::get('/', [TechnicianPortalController::class, 'index'])->name('dashboard');
        Route::get('/jobs/{job}', [TechnicianPortalController::class, 'show'])->name('jobs.show');
        Route::post('/jobs/{job}/workflow', [TechnicianPortalController::class, 'workflow'])->name('jobs.workflow.store');
        Route::post('/jobs/{job}/service-cost', [TechnicianPortalController::class, 'serviceCost'])->name('jobs.service-cost.store');
        Route::post('/jobs/{job}/payment-collections', [TechnicianPaymentCollectionController::class, 'store'])->name('jobs.payment-collections.store');
        Route::get('/jobs/{job}/invoice', [TechnicianPortalController::class, 'invoice'])->name('jobs.invoice.pdf');
        Route::post('/jobs/{job}/transitions', [TechnicianPortalController::class, 'transition'])->name('jobs.transitions.store');
        Route::post('/jobs/{job}/evidence', [TechnicianPortalController::class, 'storeEvidence'])->name('jobs.evidence.store');
        Route::post('/jobs/{job}/checklist/{item}', [TechnicianPortalController::class, 'checklist'])->name('jobs.checklist.update');
        Route::post('/jobs/{job}/parts', [TechnicianPortalController::class, 'consumePart'])->name('jobs.parts.store');
        Route::post('/jobs/{job}/parts/{consumption}/return', [TechnicianPortalController::class, 'returnPart'])->name('jobs.parts.return');
        Route::post('/attendance', [TechnicianPortalController::class, 'attendance'])->name('attendance.store');
        Route::post('/leave-requests', [TechnicianPortalController::class, 'storeLeave'])->name('leave-requests.store');
        Route::get('/payout-lines/{payoutLine}/payslip', [TechnicianPortalController::class, 'payslip'])->name('payout-lines.payslip');
        Route::post('/payout-lines/{payoutLine}/disputes', [TechnicianPortalController::class, 'disputePayout'])->name('payout-lines.disputes.store');
    });

    Route::prefix('customer')->name('customer.')->middleware('role:CUSTOMER')->group(function (): void {
        Route::get('/', [CustomerPortalController::class, 'index'])->name('dashboard');
        Route::post('/assets', [CustomerPortalController::class, 'storeAsset'])->name('assets.store');
        Route::post('/bookings', [CustomerPortalController::class, 'storeBooking'])->name('bookings.store');
        Route::get('/jobs/{job}/tracking', [CustomerPortalController::class, 'tracking'])->name('jobs.tracking');
        Route::post('/feedback', [CustomerPortalController::class, 'storeFeedback'])->name('feedback.store');
        Route::post('/notification-preferences', [CustomerPortalController::class, 'storePreference'])->name('notification-preferences.store');
        Route::get('/invoices/{invoice}/pdf', [CustomerPortalController::class, 'invoice'])->name('invoices.pdf');
        Route::post('/invoices/{invoice}/gateway-order', [CustomerPortalController::class, 'gatewayOrder'])->name('invoices.gateway-order');
        Route::post('/payments/{payment}/confirm', [CustomerPortalController::class, 'confirmPayment'])->name('payments.confirm');
        Route::get('/warranties/{warranty}/certificate', [CustomerPortalController::class, 'warranty'])->name('warranties.certificate');
    });
});
