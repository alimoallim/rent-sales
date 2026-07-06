<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesReportFilterRequest;
use App\Models\SaleBuilding;
use App\Services\Sales\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $reportService) {}

    public function balance(SalesReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $report = $this->reportService->balanceReport(
            $request->integer('building_id') ?: null,
            $request->boolean('outstanding_only'),
        );

        return $this->respond($request, $report, 'sales-balance.csv', [
            'Client', 'Building', 'Unit', 'Sale price', 'Deposit', 'Paid', 'Balance', 'Payments',
        ], fn (array $row) => [
            $row['client_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['agreed_sale_price'],
            $row['deposit'],
            $row['paid_total'],
            $row['balance'],
            $row['payment_count'],
        ]);
    }

    public function incomeStatement(SalesReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $report = $this->reportService->incomeStatement(
            $request->integer('building_id') ?: null,
            $request->input('from'),
            $request->input('to'),
        );

        if ($request->string('format')->toString() === 'csv') {
            $rows = [
                ['Income total', $report['income_total']],
                ['Expense total', $report['expense_total']],
                ['Net balance', $report['net_balance']],
                [],
                ['Payments'],
                ['Client', 'Building', 'Amount', 'Discount', 'Date'],
            ];

            foreach ($report['payments'] as $payment) {
                $rows[] = [
                    $payment['client_name'],
                    $payment['building_name'],
                    $payment['amount'],
                    $payment['discount'],
                    $payment['paid_at'],
                ];
            }

            $rows[] = [];
            $rows[] = ['Expenses'];
            $rows[] = ['Name', 'Building', 'Amount', 'Date'];

            foreach ($report['expenses'] as $expense) {
                $rows[] = [
                    $expense['name'],
                    $expense['building_name'],
                    $expense['amount'],
                    $expense['expense_date'],
                ];
            }

            return $this->csvResponse('sales-income-statement.csv', $rows);
        }

        return response()->json($report);
    }

    public function cancelledClients(SalesReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $report = $this->reportService->cancelledClientsReport(
            $request->integer('building_id') ?: null,
            $request->input('from'),
            $request->input('to'),
        );

        return $this->respond($request, $report, 'sales-cancelled-clients.csv', [
            'Client', 'Building', 'Unit', 'Sale price', 'Deposit', 'Historical paid', 'Cancelled payments', 'Registered', 'Disabled at',
        ], fn (array $row) => [
            $row['client_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['agreed_sale_price'],
            $row['deposit'],
            $row['historical_paid_total'],
            $row['cancelled_payment_count'],
            $row['registration_date'],
            $row['disabled_at'],
        ]);
    }

    public function cancelledPayments(SalesReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $report = $this->reportService->cancelledPaymentsReport(
            $request->integer('building_id') ?: null,
            $request->input('from'),
            $request->input('to'),
        );

        return $this->respond($request, $report, 'sales-cancelled-payments.csv', [
            'Client', 'Building', 'Unit', 'Amount', 'Discount', 'Reference', 'Bank', 'Paid at', 'Cancelled at', 'Cancelled by',
        ], fn (array $row) => [
            $row['client_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['amount'],
            $row['discount'],
            $row['invoice_reference'],
            $row['bank'],
            $row['paid_at'],
            $row['cancelled_at'],
            $row['cancelled_by_name'],
        ]);
    }

    public function paymentHistory(SalesReportFilterRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $report = $this->reportService->paymentHistory(
            $request->integer('building_id') ?: null,
            $request->integer('client_id') ?: null,
            $request->input('from'),
            $request->input('to'),
            $request->boolean('include_cancelled'),
        );

        return $this->respond($request, $report, 'sales-payment-history.csv', [
            'Date', 'Client', 'Building', 'Unit', 'Reference', 'Bank', 'Amount', 'Discount', 'Status',
        ], fn (array $row) => [
            $row['paid_at'],
            $row['client_name'],
            $row['building_name'],
            $row['unit_label'],
            $row['invoice_reference'],
            $row['bank'],
            $row['amount'],
            $row['discount'],
            $row['status'],
        ]);
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

            return $this->csvResponse($filename, array_merge([$headers], $rows));
        }

        return response()->json($report);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function csvResponse(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
