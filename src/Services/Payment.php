<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Utils\ClassHelper;
use Psr\Http\Message\ResponseInterface;
use function in_array;
use function is_numeric;
use function round;
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

        return $all[$name];
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
        $invoice = (new Invoice())->where('id', $invoiceId)->where('user_id', $user->id)->first();

        if ($invoice === null || round((float) $invoice->price, 2) > 0.0) {
            return null;
        }

        if (! in_array($invoice->status, ['unpaid', 'partially_paid', 'paid_gateway'], true)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '账单状态不允许支付',
            ]);
        }

        $order = (new Order())->where('id', $invoice->order_id)->where('user_id', $user->id)->first();

        $payableOrderStatuses = ['pending_payment', 'pending_activation', 'activated'];

        if ($order === null || ! in_array($order->status, $payableOrderStatuses, true)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '订单状态不允许支付',
            ]);
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

        OrderActivation::activate((int) $order->id);

        return $response->withHeader('HX-Redirect', '/user/invoice/' . $invoice->id . '/view');
    }
}
