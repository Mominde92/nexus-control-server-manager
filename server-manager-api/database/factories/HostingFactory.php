<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HostingStatus;
use App\Enums\ServerType;
use App\Enums\SslCertificate;
use App\Models\Hosting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Hosting>
 */
class HostingFactory extends Factory
{
    /**
     * @var class-string<Hosting>
     */
    protected $model = Hosting::class;

    /**
     * Plans seen in the wild, so seeded data reads like a real estate rather
     * than lorem ipsum.
     *
     * @var array<int, string>
     */
    private const array PLANS = [
        'Starter', 'Business', 'Business Pro', 'Cloud Startup',
        'WordPress Unlimited', 'VPS 4GB', 'VPS 8GB', 'Dedicated Xeon E-2336',
        'Reseller Bronze', 'Reseller Gold',
    ];

    /**
     * @var array<int, string>
     */
    private const array LOCATIONS = [
        'Frankfurt, DE', 'Amsterdam, NL', 'London, UK', 'Ashburn, US-VA',
        'Dallas, US-TX', 'Singapore, SG', 'Sydney, AU', 'Dubai, AE',
        'Mumbai, IN', 'São Paulo, BR',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = $this->faker->dateTimeBetween('-3 years', '-1 month');
        $expiry = $this->expiryDate();
        $status = $this->statusFor($expiry);

        // Provider may be catalogued or ad-hoc, never both — the same rule the
        // service enforces on write.
        $providerId = $this->maybeId('providers', 70);
        $vendor = $this->faker->company();

        return [
            'title' => $this->faker->domainWord().'-'.$this->faker->randomElement(['web', 'app', 'api', 'cdn', 'mail']),
            'provider_id' => $providerId,
            'provider_name' => $providerId === null ? $vendor.' Hosting' : null,
            'provider_url' => $providerId === null ? 'https://'.$this->faker->domainName() : null,
            'server_type' => $this->faker->randomElement(ServerType::values()),
            'server_location' => $this->faker->randomElement(self::LOCATIONS),
            'ip_address' => $this->faker->boolean(85)
                ? $this->faker->ipv4()
                : $this->faker->ipv6(),
            'control_panel_url' => 'https://'.$this->faker->domainName().':2083',
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(12, 24),
            'ftp_username' => $this->faker->boolean(60) ? $this->faker->userName() : null,
            'ftp_password' => $this->faker->boolean(60) ? $this->faker->password(12, 24) : null,
            'purchase_date' => $purchase->format('Y-m-d'),
            'expiry_date' => $expiry,
            'plan' => $this->faker->randomElement(self::PLANS),
            'price' => $this->faker->randomFloat(2, 3, 480),
            'currency' => $this->faker->randomElement(['USD', 'USD', 'EUR', 'GBP']),
            'project_id' => $this->maybeId('projects', 55),
            'client_id' => $this->maybeId('clients', 65),
            'status' => $status,
            'ssl_certificate' => $this->faker->randomElement(SslCertificate::values()),
            'expiry_notification' => $this->faker->boolean(85),
            'notification_days_before' => $this->faker->randomElement([7, 14, 30, 30, 60, 90]),
            'description' => $this->faker->boolean(40) ? $this->faker->sentence(10) : null,
        ];
    }

    /**
     * A deliberate spread across the expiry windows the dashboard highlights:
     * roughly a fifth already expired, a quarter inside the 30-day warning
     * window, the rest comfortably in the future.
     */
    private function expiryDate(): ?string
    {
        $bucket = $this->faker->numberBetween(1, 100);

        return match (true) {
            $bucket <= 5 => null,
            $bucket <= 25 => $this->faker->dateTimeBetween('-14 months', '-1 day')->format('Y-m-d'),
            $bucket <= 50 => $this->faker->dateTimeBetween('today', '+30 days')->format('Y-m-d'),
            $bucket <= 70 => $this->faker->dateTimeBetween('+31 days', '+120 days')->format('Y-m-d'),
            default => $this->faker->dateTimeBetween('+4 months', '+3 years')->format('Y-m-d'),
        };
    }

    private function statusFor(?string $expiry): string
    {
        if ($expiry !== null && $expiry < now()->toDateString()) {
            return HostingStatus::Expired->value;
        }

        return $this->faker->randomElement([
            HostingStatus::Active->value,
            HostingStatus::Active->value,
            HostingStatus::Active->value,
            HostingStatus::Pending->value,
            HostingStatus::Suspended->value,
        ]);
    }

    /**
     * A random existing id from $table, or null $nullPercent of the time.
     *
     * Reads the table directly rather than calling a related factory so that
     * seeding attaches hostings to records that already exist, and degrades to
     * null (a valid state) when the catalogue is empty.
     */
    private function maybeId(string $table, int $chancePercent): ?int
    {
        if (! $this->faker->boolean($chancePercent)) {
            return null;
        }

        $id = DB::table($table)->whereNull('deleted_at')->inRandomOrder()->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Already past its expiry date.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'status' => HostingStatus::Expired->value,
        ]);
    }

    /**
     * Expiring inside the given window — the state the reminder command and the
     * dashboard both key off.
     */
    public function expiringWithin(int $days = 30): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => $this->faker->dateTimeBetween('today', "+{$days} days")->format('Y-m-d'),
            'status' => HostingStatus::Active->value,
            'expiry_notification' => true,
            'notification_days_before' => max($days, 30),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => HostingStatus::Active->value,
            'expiry_date' => $this->faker->dateTimeBetween('+6 months', '+2 years')->format('Y-m-d'),
        ]);
    }

    /**
     * No credentials on file, to exercise the `has_password` flags.
     */
    public function withoutCredentials(): static
    {
        return $this->state(fn (): array => [
            'username' => null,
            'password' => null,
            'ftp_username' => null,
            'ftp_password' => null,
        ]);
    }
}
