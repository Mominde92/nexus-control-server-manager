<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\RegistrarStatus;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @var class-string<Domain>
     */
    protected $model = Domain::class;

    /**
     * @var array<int, string>
     */
    private const array TLDS = [
        'com', 'com', 'com', 'net', 'org', 'io', 'co', 'dev', 'app', 'de', 'co.uk', 'nl',
    ];

    /**
     * @var array<int, string>
     */
    private const array PLANS = [
        '1 year registration', '2 year registration', '3 year registration',
        '1 year + privacy', '5 year registration', 'Premium transfer',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = $this->faker->dateTimeBetween('-5 years', '-1 month');
        $expiry = $this->expiryDate();
        $status = $this->statusFor($expiry);

        $providerId = $this->maybeId('providers', 75);
        $registrar = $this->faker->company();

        return [
            'name' => $this->uniqueName(),
            'provider_id' => $providerId,
            'provider_name' => $providerId === null ? $registrar.' Registrar' : null,
            'provider_url' => $providerId === null ? 'https://'.$this->faker->domainName() : null,
            'domain_type' => $this->faker->randomElement(DomainType::values()),
            'hosting_id' => $this->maybeId('hostings', 60),
            'username' => $this->faker->boolean(70) ? $this->faker->userName() : null,
            'password' => $this->faker->boolean(70) ? $this->faker->password(12, 24) : null,
            'purchase_date' => $purchase->format('Y-m-d'),
            'expiry_date' => $expiry,
            'price' => $this->faker->randomFloat(2, 6, 220),
            'currency' => $this->faker->randomElement(['USD', 'USD', 'EUR', 'GBP']),
            'plan' => $this->faker->randomElement(self::PLANS),
            'registrar_url' => 'https://'.$this->faker->domainName(),
            'registrar_username' => $this->faker->boolean(80) ? $this->faker->safeEmail() : null,
            'registrar_password' => $this->faker->boolean(80) ? $this->faker->password(12, 24) : null,
            'registrar_status' => $this->registrarStatusFor($expiry),
            'project_id' => $this->maybeId('projects', 55),
            'client_id' => $this->maybeId('clients', 65),
            'status' => $status,
            'auto_renewal' => $this->faker->boolean(55),
            'whois_protection' => $this->faker->boolean(45),
            'expiry_notification' => $this->faker->boolean(90),
            'notification_days_before' => $this->faker->randomElement([7, 14, 30, 30, 60, 90]),
            'description' => $this->faker->boolean(35) ? $this->faker->sentence(8) : null,
        ];
    }

    /**
     * Lower case and unique, matching the storage rule the service enforces.
     */
    private function uniqueName(): string
    {
        return mb_strtolower(
            $this->faker->unique()->domainWord().'-'.$this->faker->numberBetween(1, 9999)
            .'.'.$this->faker->randomElement(self::TLDS),
        );
    }

    /**
     * Same spread as hostings: some expired, a healthy slice inside the 30-day
     * warning window, the rest in the future.
     */
    private function expiryDate(): ?string
    {
        $bucket = $this->faker->numberBetween(1, 100);

        return match (true) {
            $bucket <= 4 => null,
            $bucket <= 22 => $this->faker->dateTimeBetween('-18 months', '-1 day')->format('Y-m-d'),
            $bucket <= 48 => $this->faker->dateTimeBetween('today', '+30 days')->format('Y-m-d'),
            $bucket <= 70 => $this->faker->dateTimeBetween('+31 days', '+150 days')->format('Y-m-d'),
            default => $this->faker->dateTimeBetween('+5 months', '+5 years')->format('Y-m-d'),
        };
    }

    private function statusFor(?string $expiry): string
    {
        if ($expiry !== null && $expiry < now()->toDateString()) {
            return DomainStatus::Expired->value;
        }

        return $this->faker->randomElement([
            DomainStatus::Active->value,
            DomainStatus::Active->value,
            DomainStatus::Active->value,
            DomainStatus::Pending->value,
            DomainStatus::Suspended->value,
        ]);
    }

    private function registrarStatusFor(?string $expiry): string
    {
        if ($expiry !== null && $expiry < now()->toDateString()) {
            return $this->faker->randomElement([
                RegistrarStatus::Expired->value,
                RegistrarStatus::Redemption->value,
            ]);
        }

        return $this->faker->randomElement([
            RegistrarStatus::Active->value,
            RegistrarStatus::Active->value,
            RegistrarStatus::Locked->value,
            RegistrarStatus::PendingTransfer->value,
            RegistrarStatus::Inactive->value,
        ]);
    }

    /**
     * A random existing id from $table, or null a share of the time.
     */
    private function maybeId(string $table, int $chancePercent): ?int
    {
        if (! $this->faker->boolean($chancePercent)) {
            return null;
        }

        $id = DB::table($table)->whereNull('deleted_at')->inRandomOrder()->value('id');

        return $id === null ? null : (int) $id;
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'status' => DomainStatus::Expired->value,
            'registrar_status' => RegistrarStatus::Expired->value,
        ]);
    }

    public function expiringWithin(int $days = 30): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => $this->faker->dateTimeBetween('today', "+{$days} days")->format('Y-m-d'),
            'status' => DomainStatus::Active->value,
            'registrar_status' => RegistrarStatus::Active->value,
            'expiry_notification' => true,
            'notification_days_before' => max($days, 30),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => DomainStatus::Active->value,
            'registrar_status' => RegistrarStatus::Active->value,
            'expiry_date' => $this->faker->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
        ]);
    }

    public function autoRenewing(): static
    {
        return $this->state(fn (): array => [
            'auto_renewal' => true,
            'whois_protection' => true,
        ]);
    }
}
