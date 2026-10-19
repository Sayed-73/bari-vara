<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** Printable monthly report + CSV for the logged-in landlord (feature: reports). */
class ReportController extends Controller
{
    public function month(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/owner/login');
        }

        abort_unless(! $user->is_admin && $user->hasFeature('reports'), 403);

        try {
            $month = Carbon::createFromFormat('!Y-m', (string) $request->query('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $end = $month->copy()->endOfMonth();

        $charges = Charge::query()
            ->with(['lease.unit.property', 'lease.tenant'])
            ->whereDate('period', $month->toDateString())
            ->orderBy('lease_id')
            ->orderBy('type')
            ->get();

        if ($request->query('format') === 'csv') {
            return $this->csv($charges, $month);
        }

        $expenses = $user->hasFeature('expense_tracking')
            ? (float) Expense::query()
                ->whereDate('spent_on', '>=', $month->toDateString())
                ->whereDate('spent_on', '<=', $end->toDateString())
                ->sum('amount')
            : null;

        $collected = (float) Payment::query()
            ->whereBetween('paid_at', [$month->copy()->startOfDay(), $end])
            ->sum('amount');

        return view('reports.month', [
            'user' => $user,
            'month' => $month,
            'charges' => $charges,
            'billed' => (float) $charges->sum('amount'),
            'due' => (float) $charges->sum(fn (Charge $charge) => $charge->dueAmount()),
            'collected' => $collected,
            'expenses' => $expenses,
        ]);
    }

    private function csv($charges, Carbon $month)
    {
        $rows = $charges->map(fn (Charge $charge) => [
            $charge->lease?->unit?->property?->name,
            $charge->lease?->unit?->name,
            $charge->lease?->tenant?->name,
            Charge::TYPES[$charge->type] ?? $charge->type,
            $charge->amount,
            $charge->paid_amount,
            $charge->dueAmount(),
            $charge->status,
            $charge->due_on->format('Y-m-d'),
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla correctly

            fputcsv($out, ['Building', 'Unit', 'Tenant', 'Type', 'Amount', 'Paid', 'Due', 'Status', 'Due date']);

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'csvSafe'], $row));
            }

            fclose($out);
        }, 'bills-' . $month->format('Y-m') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet formula injection from tenant-typed text such as "=cmd". */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
