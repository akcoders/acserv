<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutCycleRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\PayoutCycle;
use App\Models\PayoutDispute;
use App\Models\PayoutLine;
use App\Models\TechnicianScorecard;
use App\Models\User;
use App\Services\Billing\PdfDocument;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workforce\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WorkforceController extends Controller
{
    public function index(): View
    {
        $tenantId = request()->user()->tenant_id;

        return view('admin.workforce.index', [
            'employees' => User::query()->forTenant($tenantId)
                ->whereIn('role', [Role::Admin, Role::Manager, Role::Dispatcher, Role::Technician, Role::Accountant])
                ->with(['employmentProfile.manager', 'technicianProfile.branch'])
                ->orderBy('first_name')->get(),
            'managers' => User::query()->forTenant($tenantId)->whereIn('role', [Role::Owner, Role::Admin, Role::Manager])->orderBy('first_name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'attendance' => AttendanceRecord::query()->with('user:id,first_name,last_name')->orderByDesc('attendance_date')->limit(50)->get(),
            'leaveRequests' => LeaveRequest::query()->with('user:id,first_name,last_name')->orderByDesc('created_at')->limit(50)->get(),
            'payoutCycles' => PayoutCycle::query()->withCount('lines')->orderByDesc('starts_on')->limit(20)->get(),
            'payoutLines' => PayoutLine::query()->with(['user:id,first_name,last_name', 'cycle:id,cycle_number,starts_on,ends_on'])->latest()->limit(100)->get(),
            'scorecards' => TechnicianScorecard::query()->with('user:id,first_name,last_name')->orderByDesc('period_ends_on')->orderByDesc('score')->limit(20)->get(),
            'payoutDisputes' => PayoutDispute::query()->with(['user:id,first_name,last_name', 'payoutLine.cycle'])->where('status', 'OPEN')->latest()->get(),
        ]);
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest, NotificationDispatcher $notifications): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageWorkforce(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in([LeaveStatus::Approved->value, LeaveStatus::Rejected->value])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($leaveRequest, $data, $request): void {
            $lockedRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->getKey());
            abort_unless($lockedRequest->status === LeaveStatus::Pending, 422, 'This leave request has already been reviewed.');

            if ($data['status'] === LeaveStatus::Approved->value) {
                $balance = LeaveBalance::query()->firstOrCreate([
                    'user_id' => $lockedRequest->user_id,
                    'year' => $lockedRequest->starts_on->year,
                    'leave_type' => $lockedRequest->leave_type,
                ], ['opening_days' => config('acserv.workforce.leave_opening_days', 12), 'accrued_days' => 0, 'used_days' => 0]);
                $available = (float) $balance->opening_days + (float) $balance->accrued_days - (float) $balance->used_days;
                abort_if($available < (float) $lockedRequest->days, 422, 'Insufficient leave balance.');
                $balance->increment('used_days', (float) $lockedRequest->days);
            }

            $lockedRequest->update([
                ...$data,
                'reviewed_by' => $request->user()->getKey(),
                'reviewed_at' => now(),
            ]);
        });
        $leaveRequest->load('user');
        $notifications->queue('leave.reviewed', $leaveRequest->user, ['status' => $data['status']]);

        return response()->json(['message' => 'Leave request reviewed.', 'reload' => true]);
    }

    public function storePayout(StorePayoutCycleRequest $request, PayoutService $payouts): JsonResponse
    {
        $data = $request->validated();
        $cycle = $payouts->generate($data['type'], $data['starts_on'], $data['ends_on'], $data);

        return response()->json(['message' => 'Payout cycle generated.', 'cycle' => $cycle, 'reload' => true], 201);
    }

    public function payslip(PayoutCycle $payoutCycle, string $line, PdfDocument $pdf): Response
    {
        $payoutLine = $payoutCycle->lines()->with('user.employmentProfile')->findOrFail($line);
        $profile = $payoutLine->user->employmentProfile;

        return response($pdf->make('ACServ Payslip', [
            'Cycle: '.$payoutCycle->cycle_number,
            'Employee: '.$payoutLine->user->name,
            'Employee code: '.($profile?->employee_code ?? '-'),
            'Designation / grade: '.($profile?->designation ?? '-').' / '.($profile?->pay_grade ?? '-'),
            'Period: '.$payoutCycle->starts_on->format('d M Y').' - '.$payoutCycle->ends_on->format('d M Y'),
            'Completed jobs: '.$payoutLine->job_count,
            'Worked hours: '.$payoutLine->worked_hours,
            'Salary component: INR '.number_format((float) ($payoutLine->details['salary_amount'] ?? 0), 2),
            'Job earnings: INR '.number_format((float) ($payoutLine->details['job_amount'] ?? 0), 2),
            'Hourly earnings: INR '.number_format((float) ($payoutLine->details['hour_amount'] ?? 0), 2),
            'Base earnings: INR '.$payoutLine->base_amount,
            'Incentive: INR '.$payoutLine->incentive_amount,
            'Penalty: INR '.$payoutLine->penalty_amount,
            'Deductions: INR '.$payoutLine->deduction_amount,
            'Net payout: INR '.$payoutLine->net_amount,
        ]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payslip-'.$payoutLine->getKey().'.pdf"',
        ]);
    }

    public function resolveDispute(Request $request, PayoutDispute $payoutDispute): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageWorkforce(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['RESOLVED', 'REJECTED'])],
            'resolution' => ['required', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($payoutDispute, $request, $data): void {
            $locked = PayoutDispute::query()->lockForUpdate()->findOrFail($payoutDispute->getKey());
            abort_unless($locked->status === 'OPEN', 422, 'This dispute is already closed.');
            $locked->update([
                ...$data,
                'resolved_by' => $request->user()->getKey(),
                'resolved_at' => now(),
            ]);
            $locked->payoutLine()->update(['status' => PayoutStatus::Processed]);
        });

        return response()->json(['message' => 'Payout dispute resolved.', 'reload' => true]);
    }
}
