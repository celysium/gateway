<?php

namespace Celysium\Gateway\Drivers;

use Celysium\Gateway\Contracts\PaymentInterface;
use Celysium\Gateway\Contracts\RefundInterface;
use Celysium\Gateway\Exceptions\InvalidPaymentException;
use Celysium\Gateway\Exceptions\PurchaseFailedException;
use Celysium\Gateway\GatewayForm;
use Celysium\Gateway\Payment;
use Celysium\Gateway\Receipt;
use SoapClient;
use SoapFault;

class Saman implements PaymentInterface, RefundInterface
{
    /**
     * Behpardakht constructor.
     *
     * @param Payment $payment
     */
    public function __construct(protected Payment $payment)
    {
    }

    /**
     * Purchase Invoice.
     *
     * @return string
     *
     * @throws PurchaseFailedException
     * @throws SoapFault
     */
    public function purchase(callable $callback): PaymentInterface
    {
        $data = array(
            'MID' => $this->payment->config->merchantId,
            'ResNum' => $this->payment->id,
            'Amount' => $this->payment->amount,
        );

        //set CellNumber for get user cards
        if ($mobile = $this->payment->mobile) {
            $data['CellNumber'] = $mobile;
        }

        $this->payment->parameter('ResNum', $data['ResNum']);


        $soap = new SoapClient(
            $this->payment->config->apiPurchaseUrl
        );

        $response = $soap->RequestToken($data['MID'], $data['ResNum'], $data['Amount'], $data['CellNumber']);

        $status = (int)$response;

        if ($status < 0) {
            $this->purchaseFailed($response);
        }

        $this->payment->transactionId($response);

        $callback($this->payment);

        return $this;
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
            'Token' => $this->payment->transactionId,
            'RedirectUrl' => $this->payment->config->callbackUrl,
        ];
        return new GatewayForm($payUrl, $data, 'POST');
    }

    /**
     * Verify payment
     *
     *
     * @throws InvalidPaymentException
     * @throws SoapFault
     */
    public function verify(array $request): Receipt
    {
        $data = array(
            'RefNum' => $request['RefNum'],
            'merchantId' => $this->payment->config->merchantId,
        );

        $soap = new SoapClient($this->payment->config->apiVerificationUrl);
        $status = (int)$soap->VerifyTransaction($data['RefNum'], $data['merchantId']);

        if ($status < 0) {
            $this->notVerified($status);
        }

        $receipt = new Receipt($data['RefNum']);
        $receipt->parameter([
            'traceNo' => $request['TraceNo'],
            'referenceNo' => $request['RRN'],
            'transactionId' => $request['RefNum'],
            'cardNo' => $request['SecurePan'],
        ]);

        return $receipt;
    }

    /**
     * Trigger an exception
     *
     * @param $status
     *
     * @throws PurchaseFailedException
     */
    protected function purchaseFailed($status)
    {
        $message = match ($status) {
            -1 => ' تراکنش توسط خریدار کنسل شده است.',
            -6 => 'سند قبال برگشت کامل یافته است. یا خارج از زمان 30 دقیقه ارسال شده است.',
            79 => 'مبلغ سند برگشتی، از مبلغ تراکنش اصلی بیشتر است.',
            12 => 'درخواست برگشت یک تراکنش رسیده است، در حالی که تراکنش اصلی پیدا نمی شود.',
            14 => 'شماره کارت نامعتبر است.',
            15 => 'چنین صادر کننده کارتی وجود ندارد.',
            33 => 'از تاریخ انقضای کارت گذشته است و کارت دیگر معتبر نیست.',
            38 => 'رمز کارت 3 مرتبه اشتباه وارد شده است در نتیجه کارت غیر فعال خواهد شد.',
            55 => 'خریدار رمز کارت را اشتباه وارد کرده است.',
            61 => 'مبلغ بیش از سقف برداشت می باشد.',
            93 => 'تراکنش Authorize شده است (شماره PIN و PAN درست هستند) ولی امکان سند خوردن وجود ندارد.',
            68 => 'تراکنش در شبکه بانکی Timeout خورده است.',
            34 => 'خریدار یا فیلد CVV2 و یا فیلد ExpDate را اشتباه وارد کرده است (یا اصال وارد نکرده است).',
            51 => 'موجودی حساب خریدار، کافی نیست.',
            84 => 'سیستم بانک صادر کننده کارت خریدار، در وضعیت عملیاتی نیست.',
            96 => 'کلیه خطاهای دیگر بانکی باعث ایجاد چنین خطایی می گردد.',
            default => 'خطای ناشناخته ای رخ داده است.',
        };
        throw new PurchaseFailedException($message);
    }

    /**
     * Trigger an exception
     *
     * @param $status
     *
     * @throws InvalidPaymentException
     */
    private function notVerified($status)
    {
        $message = match ($status) {
            -1 => 'خطا در پردازش اطلاعات ارسالی (مشکل در یکی از ورودی ها و ناموفق بودن فراخوانی متد برگشت تراکنش)',
            -3 => 'ورودیها حاوی کارکترهای غیرمجاز میباشند.',
            -4 => 'کلمه عبور یا کد فروشنده اشتباه است (Merchant Authentication Failed)',
            -6 => 'سند قبال برگشت کامل یافته است. یا خارج از زمان 30 دقیقه ارسال شده است.',
            -7 => 'رسید دیجیتالی تهی است.',
            -8 => 'طول ورودیها بیشتر از حد مجاز است.',
            -9 => 'وجود کارکترهای غیرمجاز در مبلغ برگشتی.',
            -10 => 'رسید دیجیتالی به صورت Base64 نیست (حاوی کاراکترهای غیرمجاز است)',
            -11 => 'طول ورودیها ک تر از حد مجاز است.',
            -12 => 'مبلغ برگشتی منفی است.',
            -13 => 'مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت نخوردهی رسید دیجیتالی است.',
            -14 => 'چنین تراکنشی تعریف نشده است.',
            -15 => 'مبلغ برگشتی به صورت اعشاری داده شده است.',
            -16 => 'خطای داخلی سیستم',
            -17 => 'برگشت زدن جزیی تراکنش مجاز نمی باشد.',
            -18 => 'IP Address فروشنده نا معتبر است و یا رمز تابع بازگشتی (reverseTransaction) اشتباه است.',
            default => 'خطای ناشناخته ای رخ داده است.',
        };
        throw new InvalidPaymentException($message);
    }

    /**
     * @throws SoapFault
     * @throws InvalidPaymentException
     */
    public function refund(): Receipt
    {
        $data = [
            'username' => $this->payment->config->username,
            'password' => $this->payment->config->password,
            'refNum' => $this->payment->transactionId,
            'resNum' => $this->payment->getParameter('saleOrderId'),
            'transactionTermId' => $this->payment->config->transactionTermId,
            'refundTermId' => $this->payment->config->refundTermId,
            'amount' => $this->payment->amount,
            'requestId' => $this->payment->id,
            'exeTime' => 0,
            'email' => $this->payment->getParameter('email', ''),
            'cellNumber' => $this->payment->getParameter('mobile', ''),
            'documentDescription' => $this->payment->getParameter('description', '')
        ];

        $soap = new SoapClient($this->payment->config->apiRefundUrl);

        if($data['amount'] < 150000000) {
            $response = $soap->Refund_Reg($data);
        }
        else {
            $response = $soap->Refund_Reg_breakable($data);
        }

        if ($response->ErrorCode != 0) {
            $this->notRefunded($response->ErrorCode);
        }

        $receipt = new Receipt($response->ReferenceId);

        $receipt->parameter([
            'ActionName' => $response->ActionName,
            'RequestStatus' => $response->RequestStatus,
            'Description' => $response->Description,
            'ErrorMessage' => $response->ErrorMessage,
            'ErrorCode' => $response->ErrorCode,
            'ReferenceId' => $response->ReferenceId,
        ]);

        return $receipt;
    }

    /**
     * Trigger an exception
     *
     * @param $status
     * @throws InvalidPaymentException
     */
    private function notRefunded($status)
    {
        $message = match ($status) {
            -1 => 'خطای ناشناخته',
            1 => 'پارامتر ورودی سرویس صحیح نمی باشد',
            2 => 'نام کاربری با رمز عبور صحیح نمی باشد',
            3 => 'تراکنشی با مشخصات ارسال شده قبلا ثبت نشده است',
            4 => 'این درخواست قبلا ثبت شده است',
            5 => 'جمع مبالغ درخواست های استرداد از مبلغ اصلی تراکنش بیشتر است',
            6 => 'درخواست استرداد نهایی شده و امکان تغییر آن وجود ندارد',
            11 => 'در خواست دیگری با همین مشخصات در حال ثبت می باشد لطفا مجددا تلاش نمایید',
            13 => 'اجازه اجرای درخواست وجود ندارد لطفا با ادمین سیستم تماس حاصل فرمایید',
            default => 'خطای ناشناخته ای رخ داده است.',
        };
        throw new InvalidPaymentException($message);
    }
}
