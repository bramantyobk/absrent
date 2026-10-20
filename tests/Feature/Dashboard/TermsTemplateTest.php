<?php

use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\TermsTemplate;

it('rejects guests, customers, and operators on the terms endpoints', function () {
    $this->get(route('dashboard.terms.edit'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('dashboard.terms.edit'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->get(route('dashboard.terms.edit'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->put(route('dashboard.terms.update'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->getJson(route('api.v1.dashboard.terms.show'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->putJson(route('api.v1.dashboard.terms.update'))->assertForbidden();
});

it('shows the default template with placeholders and the menu only for admins', function () {
    CompanyProfile::factory()->create(['terms_template' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.terms.edit'))
        ->assertOk()
        ->assertSee('SURAT PERJANJIAN SEWA MENYEWA KENDARAAN')
        ->assertSee('data-template-placeholder="{vehicles}"', false)
        ->assertDontSee('data-template-placeholder="{order_code}"', false)
        ->assertSee(route('dashboard.terms.edit'), false);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertDontSee(route('dashboard.terms.edit'), false);
});

it('saves a custom template and treats an empty one as the default', function () {
    $company = CompanyProfile::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route('dashboard.terms.update'), ['terms_template' => "# Pasal 1\r\nPenyewa {name}"])
        ->assertRedirect(route('dashboard.terms.edit'));

    expect($company->refresh()->terms_template)->toBe("# Pasal 1\nPenyewa {name}");

    $this->put(route('dashboard.terms.update'), ['terms_template' => ''])->assertSessionHasNoErrors();

    expect($company->refresh()->terms_template)->toBeNull()
        ->and(app(TermsTemplate::class)->for($company))->toBe(TermsTemplate::DEFAULT_TEMPLATE);
});

it('rejects unknown placeholders, the order code placeholder, and templates that are too long', function (string $template, string $message) {
    CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.terms.update'), ['terms_template' => $template])
        ->assertSessionHasErrors(['terms_template' => $message]);
})->with([
    'unknown placeholder' => ['Halo {nama}', 'Placeholder tidak dikenal: {nama}.'],
    'order code is not available yet' => ['Pesanan {order_code}', 'Placeholder tidak dikenal: {order_code}.'],
    'too long' => [str_repeat('a', 20001), 'Template maksimal 20.000 karakter.'],
]);

it('splits rendered text into headings and paragraphs without trusting html', function () {
    $blocks = app(TermsTemplate::class)->blocks("# Pasal 1\n\nBaris satu\nBaris dua\n\n\n<b>Tebal</b>");

    expect($blocks)->toBe([
        ['type' => 'heading', 'text' => 'Pasal 1'],
        ['type' => 'paragraph', 'text' => "Baris satu\nBaris dua"],
        ['type' => 'paragraph', 'text' => '<b>Tebal</b>'],
    ]);
});

it('reads and updates the template through the api', function () {
    $company = CompanyProfile::factory()->create(['terms_template' => null]);
    $this->actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.dashboard.terms.show'))
        ->assertOk()
        ->assertJsonPath('data.uses_default_template', true)
        ->assertJsonPath('data.terms_template', TermsTemplate::DEFAULT_TEMPLATE)
        ->assertJsonPath('data.placeholders.{vehicles}', 'Daftar kendaraan beserta plat nomor');

    $this->putJson(route('api.v1.dashboard.terms.update'), ['terms_template' => 'Isi {name}'])
        ->assertOk()
        ->assertJsonPath('data.uses_default_template', false)
        ->assertJsonPath('data.terms_template', 'Isi {name}');

    $this->putJson(route('api.v1.dashboard.terms.update'), ['terms_template' => 'Isi {nama}'])->assertUnprocessable();
    $this->putJson(route('api.v1.dashboard.terms.update'), ['terms_template' => null])->assertOk()->assertJsonPath('data.uses_default_template', true);

    expect($company->refresh()->terms_template)->toBeNull();
});
