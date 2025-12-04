<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verifyUrl;

    public function __construct(User $user, $verifyUrl)
    {
        $this->user = $user;
        $this->verifyUrl = $verifyUrl;
    }

    public function build()
    {
        return $this->subject('Xác nhận tài khoản của bạn - BookHomeStay')
            ->view('emails.verify')
            ->with([
                'user' => $this->user,
                'url' => $this->verifyUrl,
            ]);
    }
}
