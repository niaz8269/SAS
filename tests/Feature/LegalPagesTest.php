<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_privacy_and_refund_pages_are_complete_and_use_the_real_contact_details(): void
    {
        $this->get(route('legal.show', 'terms'))->assertOk()->assertSee('Terms and conditions')->assertSee('Information, not a guarantee')->assertSee('Governing law and disputes')->assertSee('info@sas.cas.moe');
        $this->get(route('legal.show', 'privacy'))->assertOk()->assertSee('What we collect')->assertSee('Your choices and rights')->assertSee('How long we keep it')->assertSee('Cookies');
        $this->get(route('legal.show', 'refunds'))->assertOk()->assertSee('Cancellation and refund policy')->assertSee('enquiry@sas.cas.moe')->assertSee('What is not refundable');
    }

    public function test_the_fees_are_stated_the_same_way_on_services_terms_and_refund_pages(): void
    {
        foreach (['services.index', null] as $r) {
            $page = $r ? $this->get(route($r)) : $this->get(route('legal.show', 'refunds'));
            $page->assertOk()->assertSee('US$100 per application', false)->assertSee('US$50 is refunded if the application is rejected', false)->assertSee('US$500', false);
        }
        $this->get(route('legal.show', 'terms'))->assertSee('US$500', false)->assertSee('agency service charge', false);
    }

    public function test_the_draft_notice_is_shown_until_the_owner_approves_the_texts(): void
    {
        $this->get(route('legal.show', 'terms'))->assertSee('Draft for owner review');
        config(['sas.legal_approved' => true]);
        $this->get(route('legal.show', 'terms'))->assertDontSee('Draft for owner review');
    }
}
