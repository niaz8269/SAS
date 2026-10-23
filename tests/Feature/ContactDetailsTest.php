<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_contact_page_and_footer_show_the_official_contact_details(): void
    {
        $page = $this->get(route('contact'))->assertOk();
        foreach (['+86 176 4041 2827', 'cas-scholarships', 'enquiry@sas.cas.moe', 'admissions@sas.cas.moe', 'info@sas.cas.moe', 'images/contact/wechat-qr.jpg', 'images/contact/whatsapp-qr.jpg', 'https://wa.me/8617640412827'] as $text) {
            $page->assertSee($text, false);
        }
        $this->get(route('home'))->assertOk()->assertSee('enquiry@sas.cas.moe')->assertSee('WeChat: cas-scholarships');
    }

    public function test_mail_is_sent_from_the_no_reply_address_and_enquiry_alerts_go_to_the_enquiry_mailbox(): void
    {
        $this->assertSame('noreply@sas.cas.moe', config('mail.from.address'));
        $this->assertSame('enquiry@sas.cas.moe', config('sas.staff_notification_email'));
    }
}
