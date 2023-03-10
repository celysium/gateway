<?php

namespace Celysium\Gateway\Drivers;

use Celysium\Gateway\Contracts\PaymentInterface;
use Illuminate\Support\Arr;
use Celysium\Gateway\Contracts\RefundInterface;
use Celysium\Gateway\Exceptions\InvalidPaymentException;
use Celysium\Gateway\Exceptions\PurchaseFailedException;
use Celysium\Gateway\GatewayForm;
use Celysium\Gateway\Receipt;
use Carbon\Carbon;
use SoapClient;
use SoapFault;
use Celysium\Gateway\Payment;

class Behpardakht implements PaymentInterface, RefundInterface
{
    const SUCCESS_PAYMENT = 0;
    const INVALID_CLIENT = 21;
    const HAD_ALREADY_VERIFY_REQUESTED = 43;
    const SETTLED_TRANSACTION = 45;
    const REVERSE_TRANSACTION = 48;

    /**
     * Behpardakht constructor.
     *
     * @param Payment $payment
     */
    public function __construct(protected Payment $payment)
    {
    }

    /**
     * payment.
     *
     * @param callable $callback
     * @return RefundInterface
     *
     * @throws PurchaseFailedException
     * @throws SoapFault
     */

    public function purchase(callable $callback): PaymentInterface
    {
        $soap = $this->resolveSoapClientType();

        $data = [
            'terminalId' => $this->payment->config->terminalId,
            'userName' => $this->payment->config->username,
            'userPassword' => $this->payment->config->password,
            'callBackUrl' => $this->payment->config->callbackUrl,
            'amount' => $this->payment->amount,
            'localDate' => Carbon::now()->format('Ymd'),
            'localTime' => Carbon::now()->format('Gis'),
            'orderId' => $this->payment->id,
            'additionalData' => $this->payment->getParameter('additionalData', $this->payment->config->descripton),
            'payerId' => $this->payment->getParameter('payerId', 0)
        ];

        $this->payment->parameter([
            'orderId' => $this->payment->id,
            'additionalData' => $this->payment->getParameter('additionalData', $this->payment->config->descripton),
            'payerId' => $this->payment->getParameter('payerId', 0)
        ]);

        $response = $soap->bpPayRequest($data);

        if ($response->return == static::INVALID_CLIENT) {
            throw new PurchaseFailedException($this->translateStatus('21'), static::INVALID_CLIENT);
        }

        $data = explode(',', $response->return);

        if ($data[0] != "0") {
            throw new PurchaseFailedException($this->translateStatus($data[0]), (int)$data[0]);
        }

        $this->payment->transactionId($data[1]);

        $callback($this->payment);

        return $this;
    }

    protected function isServerProtocolHttpTwo(): bool
    {
        return isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] == "HTTP/2.0";
    }

    protected function makeStreamContextForHttpTwo()
    {
        return stream_context_create([
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )]
        );
    }

    /**
     * Pay the Invoice
     *
     * @return GatewayForm
     */
    public function pay(): GatewayForm
    {
        $payUrl = $this->payment->config->apiPaymentUrl;

        $data = [
            'driver' => $this->payment->driver,
            'RefId' => $this->payment->transactionId,
        ];

        //set mobileNo for get user cards
        if ($mobile = $this->payment->getParameter('mobile')) {
            $data['MobileNo'] = $mobile;
        }

        return new GatewayForm($payUrl, $data, 'POST');
    }

    /**
     * Verify payment
     *
     * @param array $request
     * @return Receipt
     *
     * @throws InvalidPaymentException
     * @throws SoapFault
     */
    public function verify(array $request): Receipt
    {
        $resCode = $request['ResCode'];
        if ($resCode != '0') {
            throw new InvalidPaymentException($this->translateStatus($resCode), $resCode);
        }

        $data = [
            'terminalId' => $this->payment->config->terminalId,
            'userName' => $this->payment->config->username,
            'userPassword' => $this->payment->config->password,
            'orderId' => $this->payment->id,
            'saleOrderId' => $this->payment->getParameter('SaleOrderId'),
            'saleReferenceId' => $this->payment->getParameter('SaleReferenceId')
        ];

        $soap = $this->resolveSoapClientType();

        // step1: verify request
        $verifyResponse = (int)$soap->bpVerifyRequest($data)->return;

        if ($verifyResponse != static::SUCCESS_PAYMENT) {
            // rollback money and throw exception
            // avoid rollback if request was already verified
            if ($verifyResponse != static::HAD_ALREADY_VERIFY_REQUESTED) {
                $soap->bpReversalRequest($data);
            }
            throw new InvalidPaymentException($this->translateStatus($verifyResponse), $verifyResponse);
        }

        // step2: settle request
        $settleResponse = $soap->bpSettleRequest($data)->return;
        if ($settleResponse != static::SUCCESS_PAYMENT) {
            // rollback money and throw exception
            // avoid rollback if request was already settled/reversed
            if ($settleResponse != static::SETTLED_TRANSACTION && $settleResponse != static::REVERSE_TRANSACTION) {
                $soap->bpReversalRequest($data);
            }
            throw new InvalidPaymentException($this->translateStatus($settleResponse), $settleResponse);
        }

        $receipt = new Receipt($data['saleReferenceId']);
        $receipt->parameter([
            "RefId" => $request['RefId'],
            "SaleOrderId" => $request['SaleOrderId'],
            "CardHolderPan" => $request['CardHolderPan'],
            "CardHolderInfo" => $request['CardHolderInfo'],
            "SaleReferenceId" => $request['SaleReferenceId'],
        ]);

        return $receipt;
    }

    protected function resolveSoapClientType(): SoapClient
    {
        if ($this->isServerProtocolHttpTwo()) {
            return new SoapClient($this->payment->config->apiPurchaseUrl, [
                'stream_context' => $this->makeStreamContextForHttpTwo()
            ]);
        }

        return new SoapClient($this->payment->config->apiPurchaseUrl);
    }

    /**
     * @throws SoapFault
     * @throws InvalidPaymentException
     */
    public function refund(): Receipt
    {
        $soap = $this->resolveSoapClientType();

        $data = [
            'terminalId' => $this->payment->config->terminalId,
            'userName' => $this->payment->config->username,
            'userPassword' => $this->payment->config->password,
            'orderId' => $this->payment->id,
            'saleOrderId' => $this->payment->getParameter('saleOrderId'),
            'saleReferenceId' => $this->payment->getParameter('saleReferenceId'),
            'refundAmount' => $this->payment->amount,
        ];

        $response = $soap->bpRefundRequest($data);
        $data = explode(',', $response->return);

        if ($data[0] != "0") {
            throw new InvalidPaymentException($this->translateStatus($data[0]));
        }


        $receipt = new Receipt($data[1]);

        $receipt->parameter([
            'status' => $data[0],
            'ReferenceId' => $data[1],
        ]);

        return $receipt;
    }

    /**
     * Convert status to a readable message.
     *
     * @param $status
     * @return string
     */
    private function translateStatus($status): string
    {
        return match ($status) {
            '0' => 'تراکنش با موفقیت انجام شد',
            '11' => 'شماره کارت نامعتبر است',
            '12' => 'موجودی کافی نیست',
            '13' => 'رمز نادرست است',
            '14' => 'تعداد دفعات وارد کردن رمز بیش از حد مجاز است',
            '15' => 'کارت نامعتبر است',
            '16' => 'دفعات برداشت وجه بیش از حد مجاز است',
            '17' => 'کاربر از انجام تراکنش منصرف شده است',
            '18' => 'تاریخ انقضای کارت گذشته است',
            '19' => 'مبلغ برداشت وجه بیش از حد مجاز است',
            '111' => 'صادر کننده کارت نامعتبر است',
            '112' => 'خطای سوییچ صادر کننده کارت',
            '113' => 'پاسخی از صادر کننده کارت دریافت نشد',
            '114' => 'دارنده کارت مجاز به انجام این تراکنش نیست',
            '21' => 'پذیرنده نامعتبر است',
            '23' => 'خطای امنیتی رخ داده است',
            '24' => 'اطلاعات کاربری پذیرنده نامعتبر است',
            '25' => 'مبلغ نامعتبر است',
            '31' => 'پاسخ نامعتبر است',
            '32' => 'فرمت اطلاعات وارد شده صحیح نمی‌باشد',
            '33' => 'حساب نامعتبر است',
            '34' => 'خطای سیستمی',
            '35' => 'تاریخ نامعتبر است',
            '41' => 'شماره درخواست تکراری است',
            '42' => 'تراکنش Sale یافت نشد',
            '43' => 'قبلا درخواست Verify داده شده است',
            '44' => 'درخواست Verify یافت نشد',
            '45' => 'تراکنش Settle شده است',
            '46' => 'تراکنش Settle نشده است',
            '47' => 'تراکنش Settle یافت نشد',
            '48' => 'تراکنش Reverse شده است',
            '412' => 'شناسه قبض نادرست است',
            '413' => 'شناسه پرداخت نادرست است',
            '414' => 'سازمان صادر کننده قبض نامعتبر است',
            '415' => 'زمان جلسه کاری به پایان رسیده است',
            '416' => 'خطا در ثبت اطلاعات',
            '417' => 'شناسه پرداخت کننده نامعتبر است',
            '418' => 'اشکال در تعریف اطلاعات مشتری',
            '419' => 'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است',
            '421' => 'IP نامعتبر است',
            '51' => 'تراکنش تکراری است',
            '54' => 'تراکنش مرجع موجود نیست',
            '55' => 'تراکنش نامعتبر است',
            '61' => 'خطا در واریز',
            '62' => 'مسیر بازگشت به سایت در دامنه ثبت شده برای پذیرنده قرار ندارد',
            '98' => 'سقف استفاده از رمز ایستا به پایان رسیده است',
            default => 'خطای ناشناخته رخ داده است.',
        };
    }
}
