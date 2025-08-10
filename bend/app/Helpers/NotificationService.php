<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;
// use Aws\Sns\SnsClient; // Uncomment this if you plan to use AWS SNS later

class NotificationService
{
    public static function send($to, $subject, $message)
    {
        // ✅ EMAIL (current)
        Log::info("$subject");
        Log::info("To: $to");
        Log::info("Message: $message");

        // Optional: Mail::to($to)->send(new ReminderMail(...));

        /*
        // 🔄 SMS (uncomment below to switch to SMS via AWS SNS)

        // $sns = new SnsClient([
        //     'region' => env('AWS_DEFAULT_REGION'),
        //     'version' => '2010-03-31',
        //     'credentials' => [
        //         'key' => env('AWS_ACCESS_KEY_ID'),
        //         'secret' => env('AWS_SECRET_ACCESS_KEY'),
        //     ],
        // ]);

        // try {
        //     $sns->publish([
        //         'Message' => $message,
        //         'PhoneNumber' => $to, // Must be E.164 format, e.g., +639171234567
        //     ]);
        //     Log::info("SMS sent to $to");
        // } catch (\Exception $e) {
        //     Log::error("Failed to send SMS to $to: " . $e->getMessage());
        // }
        */
    }
}
