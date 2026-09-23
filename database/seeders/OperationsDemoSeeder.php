<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\BookingChannel;
use App\Enums\BookingStatus;
use App\Enums\ContentStatus;
use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Enums\StockLocationType;
use App\Enums\StockMovementType;
use App\Enums\UserStatus;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\BookingEnquiry;
use App\Models\CmsOffer;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\NotificationTemplate;
use App\Models\ReportSchedule;
use App\Models\ServiceReminder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TaxProfile;
use App\Models\TechnicianProfile;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class OperationsDemoSeeder extends Seeder
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', config('acserv.demo_login.workspace'))->firstOrFail();
        $this->tenantContext->set($tenant->getKey());

        try {
            $branch = $tenant->branches()->firstOrFail();
            $branch->update(['latitude' => 19.0760000, 'longitude' => 72.8777000]);
            $technician = User::factory()->for($tenant)->create([
                'first_name' => 'Demo', 'last_name' => 'Technician',
                'email' => config('acserv.demo_login.technician_email'), 'phone' => '+919999999901',
                'role' => Role::Technician, 'status' => UserStatus::Active,
            ]);
            $customerUser = User::factory()->for($tenant)->create([
                'first_name' => 'Demo', 'last_name' => 'Customer',
                'email' => config('acserv.demo_login.customer_email'), 'phone' => '+919999999902',
                'role' => Role::Customer, 'status' => UserStatus::Active,
            ]);
            $customer = Customer::query()->firstOrFail();
            $customer->update(['user_id' => $customerUser->getKey()]);
            $asset = $customer->assets()->firstOrFail();

            TechnicianProfile::query()->create([
                'user_id' => $technician->getKey(), 'branch_id' => $branch->getKey(),
                'skills' => ['AC servicing', 'Repair', 'Installation'], 'service_zones' => ['Mumbai'],
                'is_available' => true, 'max_daily_jobs' => 6,
            ]);

            $booking = Booking::query()->create([
                'customer_id' => $customer->getKey(), 'customer_user_id' => $customerUser->getKey(),
                'asset_id' => $asset->getKey(), 'branch_id' => $branch->getKey(),
                'booking_number' => 'BK-DEMO-001', 'channel' => BookingChannel::CustomerPwa,
                'status' => BookingStatus::Converted, 'service_type' => 'Preventive AC servicing',
                'complaint' => 'Cooling performance has reduced.',
                'preferred_start_at' => now()->addDay()->setHour(10),
                'service_address' => $customer->service_address,
            ]);
            $job = Job::query()->create([
                'booking_id' => $booking->getKey(), 'customer_id' => $customer->getKey(),
                'asset_id' => $asset->getKey(), 'branch_id' => $branch->getKey(),
                'job_number' => 'JOB-DEMO-001', 'status' => JobStatus::Assigned,
                'priority' => 'NORMAL', 'service_type' => $booking->service_type,
                'description' => $booking->complaint, 'service_address' => $customer->service_address,
                'scheduled_at' => now()->addDay()->setHour(10), 'estimated_minutes' => 90,
            ]);
            $job->assignments()->create([
                'technician_id' => $technician->getKey(),
                'assigned_by' => $tenant->users()->where('role', Role::Owner)->value('id'),
                'status' => AssignmentStatus::Assigned, 'assigned_at' => now(),
            ]);
            foreach (['Inspect electrical connections', 'Clean filters and coils', 'Verify cooling performance'] as $order => $label) {
                $job->checklistItems()->create(['label' => $label, 'is_required' => true, 'sort_order' => $order]);
            }

            $tax = TaxProfile::query()->create(['name' => 'GST 18%', 'sac_code' => '998719', 'tax_rate' => 18, 'is_default' => true]);
            $item = InventoryItem::query()->create([
                'tax_profile_id' => $tax->getKey(), 'sku' => 'CAP-35UF', 'name' => 'AC Capacitor 35uF',
                'unit' => 'PCS', 'unit_cost' => 320, 'sale_price' => 550, 'reorder_level' => 5,
                'track_serials' => false, 'is_active' => true,
            ]);
            $location = StockLocation::query()->create([
                'branch_id' => $branch->getKey(), 'code' => 'WH-MAIN', 'name' => 'Main Warehouse',
                'type' => StockLocationType::Warehouse, 'is_active' => true,
            ]);
            StockMovement::query()->create([
                'inventory_item_id' => $item->getKey(), 'to_location_id' => $location->getKey(),
                'type' => StockMovementType::Opening, 'quantity' => 25, 'unit_cost' => 320,
                'reference' => 'DEMO-OPENING', 'moved_at' => now(),
            ]);

            $invoice = Invoice::query()->create([
                'customer_id' => $customer->getKey(), 'tax_profile_id' => $tax->getKey(),
                'invoice_number' => 'INV-DEMO-001', 'status' => DocumentStatus::Sent,
                'currency' => 'INR', 'issued_on' => today(), 'due_on' => today()->addDays(7),
                'subtotal' => 1000, 'tax_total' => 180, 'grand_total' => 1180,
                'paid_total' => 0, 'balance_due' => 1180,
            ]);
            $invoice->lines()->create([
                'description' => 'Preventive AC service', 'hsn_code' => '998719', 'quantity' => 1,
                'unit_price' => 1000, 'discount' => 0, 'tax_rate' => 18, 'line_total' => 1180, 'sort_order' => 0,
            ]);

            $this->seedContent();
            $this->seedTemplates();

            ServiceReminder::query()->create([
                'customer_id' => $customer->getKey(), 'asset_id' => $asset->getKey(),
                'type' => 'NEXT_SERVICE', 'due_on' => today()->addMonths(3),
                'notify_on' => today()->addMonths(3)->subDays(7), 'status' => 'PENDING',
            ]);
            BookingEnquiry::query()->create([
                'name' => 'Website Lead', 'phone' => '+919999999903', 'email' => 'lead@example.test',
                'source' => 'WEBSITE', 'status' => 'NEW', 'service_type' => 'AC repair',
            ]);
            ReportSchedule::query()->create([
                'name' => 'Weekly operations summary', 'report_type' => 'jobs', 'frequency' => 'WEEKLY',
                'recipients' => [config('acserv.demo_login.owner_email')], 'is_active' => true, 'next_run_at' => now()->addWeek(),
            ]);
            AttendanceRecord::query()->create([
                'user_id' => $technician->getKey(), 'branch_id' => $branch->getKey(),
                'attendance_date' => today(), 'checked_in_at' => now()->startOfDay()->addHours(9),
                'status' => 'PRESENT', 'worked_minutes' => 0,
            ]);

            $this->call(CommerceDemoSeeder::class);
        } finally {
            $this->tenantContext->clear();
        }
    }

    private function seedContent(): void
    {
        CmsPage::query()->create([
            'slug' => 'about-us', 'title' => 'About ACServ',
            'excerpt' => 'Professional air-conditioning service with transparent operations.',
            'body' => 'ACServ combines trained field technicians, documented service evidence, and responsive customer care.',
            'status' => ContentStatus::Published, 'published_at' => now(),
        ]);
        CmsPost::query()->create([
            'slug' => 'when-to-service-your-ac', 'title' => 'When should you service your AC?',
            'excerpt' => 'Simple signs that your air conditioner needs professional attention.',
            'body' => 'Reduced cooling, unusual noise, higher electricity use, and water leakage are common signs that an AC needs service.',
            'status' => ContentStatus::Published, 'published_at' => now(),
        ]);
        foreach ([
            ['ac-servicing', 'AC servicing', 'Deep cleaning and performance checks.', 799],
            ['ac-repair', 'AC repair', 'Diagnosis and repair for common AC faults.', 499],
            ['ac-installation', 'AC installation', 'Safe installation with commissioning checks.', 1499],
        ] as [$slug, $title, $summary, $price]) {
            CmsService::query()->create([
                'slug' => $slug, 'title' => $title, 'summary' => $summary, 'body' => $summary,
                'starting_price' => $price, 'status' => ContentStatus::Published, 'published_at' => now(),
            ]);
        }
        CmsOffer::query()->create([
            'title' => 'Season-ready AC service', 'body' => 'Save 10% on preventive servicing this month.',
            'discount_type' => 'PERCENT', 'discount_value' => 10, 'starts_on' => today(),
            'ends_on' => today()->addMonth(), 'status' => ContentStatus::Published, 'published_at' => now(),
        ]);
        Testimonial::query()->create([
            'customer_name' => 'Aarav Sharma', 'rating' => 5,
            'quote' => 'The technician arrived on time and documented every step clearly.',
            'status' => ContentStatus::Published, 'published_at' => now(),
        ]);
    }

    private function seedTemplates(): void
    {
        foreach ([
            'booking.created' => ['Booking received', 'Booking {{booking_number}} is received for {{preferred_start_at}}.'],
            'job.assigned' => ['New job assigned', 'Job {{job_number}} has been assigned for {{scheduled_at}}.'],
            'job.status_changed' => ['Job status updated', 'Job {{job_number}} is now {{status}}.'],
            'feedback.received' => ['Customer feedback requires attention', 'Job {{job_number}} received a {{rating}} star rating.'],
            'service.reminder' => ['Service reminder', 'Your {{type}} is due on {{due_on}}.'],
            'leave.reviewed' => ['Leave request updated', 'Your leave request is now {{status}}.'],
            'payout.processed' => ['Payout statement ready', 'Payout {{cycle_number}} is ready. Net amount: INR {{net_amount}}.'],
            'invoice.paid' => ['Payment received', 'Payment for invoice {{invoice_number}} was received.'],
        ] as $event => [$subject, $body]) {
            foreach (NotificationChannel::cases() as $channel) {
                NotificationTemplate::query()->create([
                    'event' => $event, 'channel' => $channel, 'locale' => 'en_IN',
                    'subject' => $subject, 'body' => $body, 'is_active' => true,
                ]);
            }
        }
    }
}
