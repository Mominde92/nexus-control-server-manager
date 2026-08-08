<?php

declare(strict_types=1);

namespace App\Http\Requests\Hosting;

use App\Enums\HostingStatus;
use App\Enums\ServerType;
use App\Enums\SslCertificate;
use App\Http\Requests\Concerns\BuildsFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field contract for a hosting record, shared by create and update.
 *
 * Authorisation deliberately lives in HostingPolicy (invoked from the
 * controller) rather than here: the policy is the single place that answers
 * "may this user touch hostings?", and it is reused by the export and
 * credential endpoints which have no form request.
 */
abstract class HostingRequest extends FormRequest
{
    use BuildsFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['title'];
    }

    /**
     * Value rules only — presence is contributed by the Store/Update subclass.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function valueRules(): array
    {
        return [
            'title' => ['string', 'max:255'],

            // Either a catalogued provider, or the ad-hoc vendor fields. The
            // service clears the ad-hoc pair when an id is supplied.
            'provider_id' => ['integer', Rule::exists('providers', 'id')->whereNull('deleted_at')],
            'provider_name' => ['string', 'max:255'],
            'provider_url' => ['url:http,https', 'max:255'],

            'server_type' => ['string', Rule::in(ServerType::values())],
            'server_location' => ['string', 'max:255'],
            'ip_address' => ['ip', 'max:45'],
            'control_panel_url' => ['url:http,https', 'max:255'],

            'username' => ['string', 'max:255'],
            'password' => ['string', 'max:255'],
            'ftp_username' => ['string', 'max:255'],
            'ftp_password' => ['string', 'max:255'],

            'purchase_date' => ['date', 'date_format:Y-m-d'],
            'expiry_date' => [
                'date',
                'date_format:Y-m-d',
                // Only comparable when both dates are in the same payload.
                ...($this->filled('purchase_date') ? ['after_or_equal:purchase_date'] : []),
            ],

            'plan' => ['string', 'max:255'],
            'price' => ['numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['string', 'size:3', 'alpha'],

            'project_id' => ['integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'client_id' => ['integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],

            'status' => ['string', Rule::in(HostingStatus::values())],
            'ssl_certificate' => ['string', Rule::in(SslCertificate::values())],

            'expiry_notification' => ['boolean'],
            'notification_days_before' => ['integer', 'between:1,365'],

            'description' => ['string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimStrings($this->all()));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_id.exists' => 'The selected provider no longer exists.',
            'project_id.exists' => 'The selected project no longer exists.',
            'client_id.exists' => 'The selected client no longer exists.',
            'ip_address.ip' => 'Enter a valid IPv4 or IPv6 address.',
            'currency.size' => 'Use a three-letter currency code, for example USD.',
            'currency.alpha' => 'Use a three-letter currency code, for example USD.',
            'expiry_date.after_or_equal' => 'The expiry date cannot fall before the purchase date.',
            'notification_days_before.between' => 'Remind between 1 and 365 days before expiry.',
            'status.in' => 'Choose a valid hosting status.',
            'server_type.in' => 'Choose a valid server type.',
            'ssl_certificate.in' => 'Choose a valid SSL certificate type.',
            'price.max' => 'The price is too large to store.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'provider_id' => 'provider',
            'project_id' => 'project',
            'client_id' => 'client',
            'ssl_certificate' => 'SSL certificate',
            'ip_address' => 'IP address',
            'control_panel_url' => 'control panel URL',
            'ftp_username' => 'FTP username',
            'ftp_password' => 'FTP password',
            'notification_days_before' => 'reminder window',
            'expiry_notification' => 'expiry reminders',
        ];
    }
}
