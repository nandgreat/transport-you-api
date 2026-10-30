<?php

namespace App\Models;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'seat_number',
        'passenger_name',
        'passenger_phone',
        'qr_code',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The string encoded inside the QR image (the scannable ticket code,
     * derived from the booking's special reference).
     */
    public function qrPayload(): string
    {
        return (string) $this->qr_code;
    }

    /**
     * Raw PNG bytes of the QR code — used to embed the image inline in emails.
     */
    public function qrPng(): string
    {
        return (new QRCode(self::qrOptions()))->render($this->qrPayload());
    }

    /**
     * QR code as a base64 PNG data URI — convenient for API responses / web.
     */
    public function qrDataUri(): string
    {
        $options = self::qrOptions();
        $options->outputBase64 = true;

        return (new QRCode($options))->render($this->qrPayload());
    }

    private static function qrOptions(): QROptions
    {
        return new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale'           => 6,
            'outputBase64'    => false,
            'quietzoneSize'   => 2,
        ]);
    }
}
