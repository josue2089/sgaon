<?php

namespace App\Http\Controllers;

use App\Models\ChargePaymentRequest;
use App\Models\MakeupRequestAttachment;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sirve comprobantes y reposos del disco privado solo a quien puede verlos. #438 */
class PrivateFileController extends Controller
{
    public function paymentProof(Request $request, ChargePaymentRequest $paymentRequest): StreamedResponse
    {
        abort_unless(PrivateFiles::canAccessStudent($request->user(), $paymentRequest->student), 403);

        return PrivateFiles::inline($paymentRequest->proof_path, $paymentRequest->proof_original_name);
    }

    public function makeupAttachment(Request $request, MakeupRequestAttachment $attachment): StreamedResponse
    {
        abort_unless(PrivateFiles::canAccessStudent($request->user(), $attachment->makeupRequest?->student), 403);

        return PrivateFiles::inline($attachment->file_path, $attachment->original_name);
    }
}
