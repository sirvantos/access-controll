<?php

declare(strict_types=1);

use App\Modules\Tenancy\PublicApi\CompanyContext;
use Tests\Support\Tenancy\IsolationProbe;

beforeEach(function (): void {
    IsolationProbe::ensureSchema();
    registerIsolationProbeRoutes();
});

it('assigns an acme post to acme and hides a globex probe as not found', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $admin = acmeAdmin(['company_id' => $acme->id]);
    $context = app(CompanyContext::class);

    $globexProbe = $context->run($globex->id, fn (): IsolationProbe => IsolationProbe::query()->create([
        'company_id' => $globex->id,
        'name' => 'Aigerim Sarsenova',
    ]));

    signedInAs($admin)
        ->postJson('/_test/company/isolation-probes', [
            'name' => 'Acme probe',
            'company_id' => $globex->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme probe');

    $created = $context->run(
        $acme->id,
        fn (): IsolationProbe => IsolationProbe::query()->where('name', 'Acme probe')->sole(),
    );

    expect($created->company_id)->toBe($acme->id);

    $missingId = 9_999_993;
    $probeResponses = [];

    foreach ([$globexProbe->id, $missingId] as $probeId) {
        $probeResponses[] = signedInAs($admin)->getJson('/_test/company/isolation-probes?id='.$probeId);
    }

    expect($probeResponses[0]->status())->toBe(404)
        ->and($probeResponses[1]->status())->toBe(404);
    $probeResponses[0]->assertExactJson($probeResponses[1]->json());

    $list = signedInAs($admin)
        ->getJson('/_test/company/isolation-probes')
        ->assertOk()
        ->json('data');

    $search = signedInAs($admin)
        ->getJson('/_test/company/isolation-probes?search=Sarsenova')
        ->assertOk()
        ->json('data');

    $names = array_column($list, 'name');

    expect($names)->toContain('Acme probe')
        ->and($names)->not->toContain('Aigerim Sarsenova')
        ->and($search)->toBe([])
        ->and($context->run($globex->id, fn (): string => $globexProbe->refresh()->name))->toBe('Aigerim Sarsenova');
});
