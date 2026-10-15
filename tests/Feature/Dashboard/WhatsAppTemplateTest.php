<?php

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\WhatsAppLink;
use App\Services\WhatsAppTemplate;

it('rejects guests, customers, and operators on the template endpoints', function () {
    $this->put(route('dashboard.company.whatsapp-template.update'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->put(route('dashboard.company.whatsapp-template.update'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->put(route('dashboard.company.whatsapp-template.update'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->putJson(route('api.v1.dashboard.company.whatsapp-template.update'))->assertForbidden();
});

it('shows the default template with placeholders on the company page', function () {
    CompanyProfile::factory()->create(['whatsapp_confirmation_template' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.company.edit'))
        ->assertOk()
        ->assertSee('Template Pesan WhatsApp')
        ->assertSee('Halo, saya {name} mau konfirmasi pembayaran:', false)
        ->assertSee('data-template-placeholder="{order_code}"', false);
});

it('saves a custom template and treats an empty one as the default', function () {
    $company = CompanyProfile::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route('dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => "Konfirmasi {order_code} dari {name}\r\nTotal {total_price}"])
        ->assertRedirect(route('dashboard.company.edit'));

    expect($company->refresh()->whatsapp_confirmation_template)->toBe("Konfirmasi {order_code} dari {name}\nTotal {total_price}");

    $this->put(route('dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => ''])->assertSessionHasNoErrors();

    expect($company->refresh()->whatsapp_confirmation_template)->toBeNull()
        ->and(app(WhatsAppTemplate::class)->for($company))->toBe(WhatsAppTemplate::DEFAULT_TEMPLATE);
});

it('rejects unknown placeholders, a missing order code, and templates that are too long', function (string $template, string $message) {
    CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => $template])
        ->assertSessionHasErrors(['whatsapp_confirmation_template' => $message]);
})->with([
    'unknown placeholder' => ['Halo {nama}, pesanan {order_code}', 'Placeholder tidak dikenal: {nama}.'],
    'missing order code' => ['Halo {name}', 'Template wajib memuat {order_code}.'],
    'too long' => [str_repeat('a', 990).' {order_code}', 'Template maksimal 1000 karakter.'],
]);

it('builds the confirmation link from the saved template', function () {
    $company = CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000', 'whatsapp_confirmation_template' => "{order_code} - {account_holder}\n{name}"]);
    $order = Order::factory()->create(['code' => 'ABS-TPL00001']);
    $order->setRelation('transaction', null);

    $url = app(WhatsAppLink::class)->toOperator($order->load('user'), $company);

    expect($url)->toBe('https://wa.me/6281200000000?text='.rawurlencode("ABS-TPL00001 - -\n{$order->user->name}"));
});

it('updates the template through the api, including resetting to the default', function () {
    $company = CompanyProfile::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->putJson(route('api.v1.dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => 'Pesanan {order_code}'])
        ->assertOk()
        ->assertJsonPath('data.whatsapp_confirmation_template', 'Pesanan {order_code}')
        ->assertJsonPath('data.uses_default_whatsapp_template', false);

    $this->putJson(route('api.v1.dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => null])
        ->assertOk()
        ->assertJsonPath('data.uses_default_whatsapp_template', true);

    $this->putJson(route('api.v1.dashboard.company.whatsapp-template.update'), ['whatsapp_confirmation_template' => 'Halo'])->assertUnprocessable();

    expect($company->refresh()->whatsapp_confirmation_template)->toBeNull();
});
