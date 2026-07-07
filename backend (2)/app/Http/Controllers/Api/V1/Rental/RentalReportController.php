<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\IncomeStatementRequest;
use App\Http\Requests\Rental\ReportFilterRequest;
use App\Models\RentalBuilding;
use App\Services\Rental\RentalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalReportController extends Controller
{
    public function __construct(private readonly RentalReportService $reports) {}

    public function tenantBalances(ReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', RentalBuilding::class);

        $report = $this->reports->tenantBalances(
            $request->integer('building_id') ?: null,
            $request->boolean('outstanding_only'),
        );

        return $this->respond($request, $report, 'tenant-balances.csv', [
            'Tenant', 'Building', 'Unit', 'Monthly rent', 'Service', 'Charge periods', 'Charged', 'Paid', 'Balance',
        ], fn (array $row) => [
            $row['tenant_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['monthly_rent'],
            $row['service_amount'],
            $row['charge_count'],
            $row['charged_amount'],
            $row['paid_amount'],
            $row['balance'],
        ]);
    }

    public function paymentHistory(ReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', RentalBuilding::class);

        $report = $this->reports->paymentHistory(
            $request->integer('building_id') ?: null,
            $request->integer('tenant_id') ?: null,
            $request->input('from'),
            $request->input('to'),
            $request->boolean('include_voided'),
        );

        return $this->respond($request, $report, 'payment-history.csv', [
            'Date', 'Tenant', 'Building', 'Invoice', 'Amount', 'Discount', 'Status',
        ], fn (array $row) => [
            $row['paid_at'],
            $row['tenant_name'],
            $row['building_name'],
            $row['invoice_reference'],
            $row['amount'],
            $row['discount'],
            $row['status'],
        ]);
    }

    public function chargeSummary(ReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', RentalBuilding::class);

        $report = $this->reports->chargeSummary(
            $request->integer('building_id') ?: null,
            $request->integer('tenant_id') ?: null,
            $request->integer('billing_month') ?: null,
            $request->integer('billing_year') ?: null,
        );

        return $this->respond($request, $report, 'charge-summary.csv', [
            'Period', 'Tenant', 'Building', 'Unit', 'Rent', 'Service', 'Total', 'Purpose',
        ], fn (array $row) => [
            "{$row['billing_month']}/{$row['billing_year']}",
            $row['tenant_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['rent_amount'],
            $row['service_amount'],
            $row['total_amount'],
            $row['purpose'],
        ]);
    }

    public function incomeStatement(IncomeStatementRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', RentalBuilding::class);

        $mode = $request->string('mode')->toString() ?: 'unified';

        $report = $this->reports->incomeStatement(
            $request->integer('building_id'),
            $request->integer('billing_month'),
            $request->integer('billing_year'),
            $mode,
        );

        if ($request->string('format')->toString() === 'csv') {
            $lines = $report['lines'];

            return $this->csvResponse('income-statement.csv', ['Line item', 'Amount'], [
                ['Rent collections', $lines['rent_collections']],
                ['Service income', $lines['service_income']],
                ['Shareholder deductions', $lines['shareholder_deductions']],
                ['Rent net', $lines['rent_net']],
                ['Water income', $lines['water_income']],
                ['Service + water subtotal', $lines['service_water_subtotal']],
                ['Expenses', $lines['expenses']],
                ['Payroll', $lines['payroll']],
                ['Electricity', $lines['electricity']],
                ['Nairobi water', $lines['nairobi_water']],
                ['Expense subtotal', $lines['expense_subtotal']],
                ['Service + water net', $lines['service_water_net']],
                ['Net balance in hand', $lines['net_balance_in_hand']],
            ]);
        }

        return response()->json($report);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<string>  $headers
     * @param  callable(array<string, mixed>): list<mixed>  $rowMapper
     */
    private function respond(
        Request $request,
        array $report,
        string $filename,
        array $headers,
        callable $rowMapper,
    ): JsonResponse|StreamedResponse {
        if ($request->string('format')->toString() === 'csv') {
            $rows = array_map($rowMapper, $report['rows']);

            return $this->csvResponse($filename, $headers, $rows);
        }

        return response()->json($report);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
