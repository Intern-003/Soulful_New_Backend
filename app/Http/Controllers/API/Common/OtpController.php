<?php
namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Services\MailService;

class OtpController extends Controller
{
    // ----------------------------
    // Generate OTP
    // ----------------------------
    private function generateOtp($identifier, $type)
    {
        $otp = rand(100000, 999999);

        $existing = Otp::where('identifier', $identifier)
            ->where('type', $type)
            ->first();

        if ($existing && now()->lt($existing->created_at->addSeconds(30))) {
            abort(response()->json(['message' => 'Wait before retry'], 429));
        }

        Otp::updateOrCreate(
            [
                'identifier' => $identifier,
                'type' => $type
            ],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        return $otp;
    }

    // ----------------------------
    // Send OTP
    // ----------------------------
   public function sendOtp(Request $request)
{
    $request->validate([
        'identifier' => 'required',
        'type' => 'required|in:email,phone',
    ]);

    $otp = $this->generateOtp($request->identifier, $request->type);

    // 🔥 EMAIL OTP
    if ($request->type === 'email') {
        MailService::sendOtpEmail($request->identifier, $otp);
    }

    // 📱 PHONE OTP (later integrate Twilio/Fast2SMS)
    if ($request->type === 'phone') {
        // SMS gateway integration here
    }

    return response()->json([
        'message' => 'OTP sent successfully'
    ]);
}
    // ----------------------------
    // Internal Verify (REUSABLE)
    // ----------------------------
    public function verifyOtpInternal($identifier, $type, $otpInput)
    {
        $record = Otp::where('identifier', $identifier)
            ->where('type', $type)
            ->first();

        if (!$record) {
            return ['status' => false, 'message' => 'OTP not found'];
        }

        if ($record->otp != $otpInput) {
            return ['status' => false, 'message' => 'Invalid OTP'];
        }

        if (now()->gt($record->expires_at)) {
            $record->delete(); // cleanup
            return ['status' => false, 'message' => 'OTP expired'];
        }

        $record->delete(); // 🔥 delete after success

        return ['status' => true];
    }
}