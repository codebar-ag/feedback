<?php

declare(strict_types=1);

use App\Geo\CloudflareGeoLocator;
use App\Geo\GeoLocation;
use App\Geo\MaxMindGeoLocator;
use App\Geo\NullGeoLocator;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use GeoIp2\Model\Country;
use GeoIp2\ProviderInterface;
use Illuminate\Http\Request;

it('returns nothing without a source', function (): void {
    expect((new NullGeoLocator)->locate(new Request, '203.0.113.7'))->toBeNull();
});

it('reads the Cloudflare headers', function (): void {
    $request = new Request;
    $request->headers->add(['CF-IPCountry' => 'CH', 'CF-IPCity' => 'Meilen', 'CF-Postal-Code' => '8706']);

    expect((new CloudflareGeoLocator)->locate($request, '203.0.113.7'))
        ->toEqual(new GeoLocation('CH', 'Meilen', '8706'));
});

it('ignores unknown Cloudflare countries', function (): void {
    $request = new Request;
    $request->headers->add(['CF-IPCountry' => 'XX']);

    expect((new CloudflareGeoLocator)->locate($request, '203.0.113.7'))->toBeNull();
});

function cityProvider(?City $city): ProviderInterface
{
    return new class($city) implements ProviderInterface
    {
        public function __construct(private ?City $city) {}

        public function country(string $ipAddress): Country
        {
            throw new LogicException('unused');
        }

        public function city(string $ipAddress): City
        {
            return $this->city ?? throw new AddressNotFoundException('not found');
        }
    };
}

it('looks up a local MaxMind database', function (): void {
    $city = new City([
        'country' => ['iso_code' => 'CH'],
        'city' => ['names' => ['en' => 'Meilen']],
        'postal' => ['code' => '8706'],
    ]);

    expect((new MaxMindGeoLocator(fn (): ProviderInterface => cityProvider($city)))->locate(new Request, '203.0.113.7'))
        ->toEqual(new GeoLocation('CH', 'Meilen', '8706'));
});

it('returns nothing for unknown addresses or a missing database', function (): void {
    expect((new MaxMindGeoLocator(fn (): ProviderInterface => cityProvider(null)))->locate(new Request, '10.0.0.1'))->toBeNull()
        ->and((new MaxMindGeoLocator(fn (): ?ProviderInterface => null))->locate(new Request, '10.0.0.1'))->toBeNull()
        ->and((new MaxMindGeoLocator(fn (): ProviderInterface => cityProvider(new City([]))))->locate(new Request, '10.0.0.1'))->toBeNull();
});
