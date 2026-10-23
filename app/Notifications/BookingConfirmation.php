<?php

namespace App\Notifications;

use App\Enums\CourseType;
use App\Models\Booking;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Booking confirmation to the booker (US-29): courses, dates,
 * participants, extras and price.
 */
class BookingConfirmation extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('lines.priceTable.sports', 'lines.sport', 'lines.students', 'lines.lessonDays', 'addOns.addOn');
        $school = $booking->school;

        $mail = (new MailMessage)
            ->subject(__('Your booking :number at :school', ['number' => $booking->number, 'school' => $school->name]))
            ->greeting(__('Hello :name,', ['name' => $booking->booker_name]))
            ->line(__('thank you for your booking. Here is your summary.'));

        foreach ($booking->lines as $line) {
            $when = $line->course_type === CourseType::Group
                ? $line->lessonDays->map(fn ($d) => $d->date->isoFormat('dd D.M.'))->implode(', ').' · '.$line->timeslot_key->label()
                : $line->start_date->isoFormat('dddd, L').' · '.substr($line->start_time, 0, 5).'–'.$line->endTime();

            $mail->line('**'.$line->priceTable->displayName().'** ('.$line->sport->name.')')
                ->line($when)
                ->line(__('Participants').': '.$line->students->map->fullName()->implode(', '))
                ->line(__('Price').': '.Money::format($line->price_cents));
        }

        foreach ($booking->addOns as $extra) {
            $mail->line($extra->quantity.' × '.$extra->addOn->name.': '.Money::format($extra->total_cents));
        }

        if ($booking->discount_cents > 0) {
            $mail->line(__('Voucher :code', ['code' => $booking->voucher_code]).': −'.Money::format($booking->discount_cents));
        }

        return $mail
            ->line('**'.__('Total').': '.Money::format($booking->total_cents).'**')
            ->line(__('Payment: :method', ['method' => $booking->payment_method->label()]))
            ->salutation(__('See you on the slopes!')."\n\n".$school->name);
    }
}
