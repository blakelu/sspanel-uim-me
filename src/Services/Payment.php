<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Utils\ClassHelper;
use Psr\Http\Message\ResponseInterface;
use function bccomp;
use function in_array;
use function is_numeric;
use function time;

final class Payment
{
    public static function getAllPaymentMap(): array
    {
        $payments = [];

        $helper = new ClassHelper();
        $class_list = $helper->getClassesByNamespace('\\App\\Services\\Gateway\\');

        foreach ($class_list as $class) {
            if (get_parent_class($class) === 'App\\Services\\Gateway\\Base') {
                $payments[] = $class;
            }
        }

        return $payments;
    }

    public static function getPaymentsEnabled(): array
    {
        return array_values(array_filter(Payment::getAllPaymentMap(), static function ($payment) {
            return $payment::_enable();
        }));
    }

    public static function getPaymentMap(): array
    {
        $result = [];

        foreach (self::getPaymentsEnabled() as $payment) {
            $result[$payment::_name()] = $payment;
        }

        return $result;
    }

    public static function getPaymentByName($name): ?string
    {
        $all = self::getPaymentMap();

        return $all[$name] ?? null;
    }

    public static function notify($request, $response, $args): ResponseInterface
    {
        $payment = self::getPaymentByName($args['type']);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->notify($request, $response, $args);
        }

        return $response->withStatus(404);
    }

    public static function returnHTML($request, $response, $args): ResponseInterface
    {
        $payment = self::getPaymentByName($args['type']);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->getReturnHTML($request, $response, $args);
        }

        return $response->withStatus(404);
    }

    public static function purchase($request, $response, $args): ResponseInterface
    {
        $freeInvoiceResponse = self::completeFreeInvoice($request, $response);

        if ($freeInvoiceResponse !== null) {
            return $freeInvoiceResponse;
        }

        $payment = self::getPaymentByName($args['type']);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->purchase($request, $response, $args);
        }

        return $response->withStatus(404);
    }

    /**
     * Complete both new and legacy zero-value invoices without sending them to a payment gateway.
     */
    private static function completeFreeInvoice($request, $response): ?ResponseInterface
    {
        $invoiceId = $request->getParam('invoice_id');

        if (! is_numeric($invoiceId)) {
            return null;
        }

        $user = Auth::getUser();
        [$status, $orderId] = DB::connection()->transaction(static function () use ($invoiceId, $user): array {
            $invoice = (new Invoice())->where('user_id', $user->id)->lockForUpdate()->find($invoiceId);

            if ($invoice === null || bccomp((string) $invoice->price, '0', 2) !== 0) {
                return ['gateway', null];
            }

            if (! $invoice->acceptsPayment() && $invoice->status !== 'paid_gateway') {
                return ['invalid_invoice', null];
            }

            $order = (new Order())->where('user_id', $user->id)->lockForUpdate()->find($invoice->order_id);

            if ($order === null || ! in_array($order->status, ['pending_payment', 'pending_activation', 'activated'], true)) {
                return ['invalid_order', null];
            }

            if ($invoice->status !== 'paid_gateway') {
                $invoice->status = 'paid_gateway';
                $invoice->update_time = time();
                $invoice->pay_time = time();
                $invoice->save();
            }

            if ($order->status === 'pending_payment') {
                $order->status = 'pending_activation';
                $order->update_time = time();
                $order->save();
            }

            return ['paid', (int) $order->id];
        }, 3);

        if ($status === 'gateway') {
            return null;
        }

        if ($status !== 'paid') {
            return $response->withJson([
                'ret' => 0,
                'msg' => $status === 'invalid_invoice' ? '账单状态不允许支付' : '订单状态不允许支付',
            ]);
        }

        OrderActivation::activate($orderId);

        return $response->withHeader('HX-Redirect', '/user/invoice/' . $invoiceId . '/view');
    }
}
